<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SmsService
{
    protected $provider;
    protected $config;

    public function __construct()
    {
        $company = auth()->user()?->company;
        
        if ($company) {
            $smsSettings = $company->settings['sms'] ?? null;
            if ($smsSettings) {
                $this->provider = $smsSettings['provider'] ?? 'netgsm';
                $this->config = $smsSettings;
                
                // Şifreyi decrypt et
                if (isset($this->config['password'])) {
                    try {
                        $this->config['password'] = decrypt($this->config['password']);
                    } catch (\Exception $e) {
                        Log::error('SMS password decrypt failed: ' . $e->getMessage());
                    }
                }
            }
        }
    }

    /**
     * SMS gönder
     */
    public function send(string $phone, string $message, ?string $provider = null): array
    {
        $provider = $provider ?? $this->provider ?? 'netgsm';

        if (!$this->config) {
            throw new \Exception('SMS ayarları yapılandırılmamış');
        }

        return match ($provider) {
            'netgsm' => $this->sendViaNetGSM($phone, $message),
            'iletimerkezi' => $this->sendViaIletiMerkezi($phone, $message),
            'twilio' => $this->sendViaTwilio($phone, $message),
            'custom' => $this->sendViaCustom($phone, $message),
            default => throw new \Exception("Desteklenmeyen SMS provider: {$provider}"),
        };
    }

    /**
     * NetGSM ile SMS gönder
     */
    protected function sendViaNetGSM(string $phone, string $message): array
    {
        $username = $this->config['username'] ?? '';
        $password = $this->config['password'] ?? '';
        $sender = $this->config['sender'] ?? '';

        // Telefon numarasını temizle (0 ile başlıyorsa kaldır, +90 ekle)
        $phone = $this->cleanPhoneNumber($phone);
        if (strpos($phone, '90') !== 0) {
            $phone = '90' . ltrim($phone, '0');
        }

        $url = 'https://api.netgsm.com.tr/sms/send/get';
        
        $params = [
            'usercode' => $username,
            'password' => $password,
            'gsmno' => $phone,
            'message' => $message,
            'msgheader' => $sender,
        ];

        try {
            $response = Http::timeout(10)->get($url, $params);
            $body = $response->body();

            // NetGSM response formatı: "00" başarılı, diğerleri hata kodu
            if (strpos($body, '00') === 0) {
                return [
                    'success' => true,
                    'message' => 'SMS başarıyla gönderildi',
                    'provider' => 'netgsm',
                    'response' => $body,
                ];
            } else {
                throw new \Exception('NetGSM hatası: ' . $body);
            }
        } catch (\Exception $e) {
            Log::error('NetGSM SMS gönderim hatası: ' . $e->getMessage());
            throw new \Exception('SMS gönderilemedi: ' . $e->getMessage());
        }
    }

    /**
     * İleti Merkezi ile SMS gönder
     */
    protected function sendViaIletiMerkezi(string $phone, string $message): array
    {
        $username = $this->config['username'] ?? '';
        $password = $this->config['password'] ?? '';
        $sender = $this->config['sender'] ?? '';

        $phone = $this->cleanPhoneNumber($phone);
        if (strpos($phone, '90') !== 0) {
            $phone = '90' . ltrim($phone, '0');
        }

        $url = 'https://api.iletimerkezi.com/v1/send/sms';
        
        $data = [
            'request' => [
                'authentication' => [
                    'username' => $username,
                    'password' => $password,
                ],
                'order' => [
                    'sender' => $sender,
                    'message' => [
                        'text' => $message,
                        'receipents' => [
                            'number' => [$phone],
                        ],
                    ],
                ],
            ],
        ];

        try {
            $response = Http::timeout(10)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post($url, $data);

            $result = $response->json();

            if (isset($result['response']['status']['code']) && $result['response']['status']['code'] == 200) {
                return [
                    'success' => true,
                    'message' => 'SMS başarıyla gönderildi',
                    'provider' => 'iletimerkezi',
                    'response' => $result,
                ];
            } else {
                $errorMsg = $result['response']['status']['message'] ?? 'Bilinmeyen hata';
                throw new \Exception('İleti Merkezi hatası: ' . $errorMsg);
            }
        } catch (\Exception $e) {
            Log::error('İleti Merkezi SMS gönderim hatası: ' . $e->getMessage());
            throw new \Exception('SMS gönderilemedi: ' . $e->getMessage());
        }
    }

    /**
     * Twilio ile SMS gönder
     */
    protected function sendViaTwilio(string $phone, string $message): array
    {
        $accountSid = $this->config['username'] ?? '';
        $authToken = $this->config['password'] ?? '';
        $from = $this->config['sender'] ?? '';

        $phone = $this->cleanPhoneNumber($phone);
        if (strpos($phone, '+') !== 0) {
            $phone = '+' . $phone;
        }

        $url = "https://api.twilio.com/2010-04-01/Accounts/{$accountSid}/Messages.json";

        try {
            $response = Http::timeout(10)
                ->withBasicAuth($accountSid, $authToken)
                ->asForm()
                ->post($url, [
                    'From' => $from,
                    'To' => $phone,
                    'Body' => $message,
                ]);

            $result = $response->json();

            if (isset($result['sid'])) {
                return [
                    'success' => true,
                    'message' => 'SMS başarıyla gönderildi',
                    'provider' => 'twilio',
                    'sid' => $result['sid'],
                ];
            } else {
                $errorMsg = $result['message'] ?? 'Bilinmeyen hata';
                throw new \Exception('Twilio hatası: ' . $errorMsg);
            }
        } catch (\Exception $e) {
            Log::error('Twilio SMS gönderim hatası: ' . $e->getMessage());
            throw new \Exception('SMS gönderilemedi: ' . $e->getMessage());
        }
    }

    /**
     * Custom API ile SMS gönder
     */
    protected function sendViaCustom(string $phone, string $message): array
    {
        $apiUrl = $this->config['api_url'] ?? '';
        
        if (empty($apiUrl)) {
            throw new \Exception('Custom API URL yapılandırılmamış');
        }

        $phone = $this->cleanPhoneNumber($phone);

        try {
            $response = Http::timeout(10)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer ' . ($this->config['password'] ?? ''),
                ])
                ->post($apiUrl, [
                    'phone' => $phone,
                    'message' => $message,
                    'sender' => $this->config['sender'] ?? '',
                ]);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'message' => 'SMS başarıyla gönderildi',
                    'provider' => 'custom',
                    'response' => $response->json(),
                ];
            } else {
                throw new \Exception('Custom API hatası: ' . $response->body());
            }
        } catch (\Exception $e) {
            Log::error('Custom SMS gönderim hatası: ' . $e->getMessage());
            throw new \Exception('SMS gönderilemedi: ' . $e->getMessage());
        }
    }

    /**
     * Telefon numarasını temizle
     */
    protected function cleanPhoneNumber(string $phone): string
    {
        // Boşluk, tire, parantez gibi karakterleri temizle
        $phone = preg_replace('/[^0-9+]/', '', $phone);
        
        // + işaretini kaldır
        $phone = ltrim($phone, '+');
        
        return $phone;
    }
}


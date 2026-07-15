<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\ActivityLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class CompanySettingsController extends BaseController
{
    /**
     * Tüm ayarları getir
     */
    public function index(): JsonResponse
    {
        $company = auth()->user()->company;

        if (! $company) {
            return $this->notFound('Firma bulunamadı');
        }

        $settings = $company->settings ?? [];

        return $this->success([
            'smtp' => $settings['smtp'] ?? null,
            'sms' => $settings['sms'] ?? null,
            'general' => $settings['general'] ?? [
                'timezone' => 'Europe/Istanbul',
                'language' => 'tr',
                'date_format' => 'd/m/Y',
                'currency' => 'TRY',
                'working_days' => [1, 2, 3, 4, 5],
                'default_work_start' => '09:00',
                'default_work_end' => '18:00',
                'late_tolerance_minutes' => 15,
            ],
            'notifications' => $settings['notifications'] ?? [
                'email_enabled' => true,
                'email_leave_requests' => true,
                'email_approvals' => true,
                'email_reminders' => true,
                'email_reports' => false,
                'sms_enabled' => false,
                'sms_leave_requests' => false,
                'sms_approvals' => false,
                'sms_reminders' => false,
                'push_enabled' => true,
                'push_leave_requests' => true,
                'push_approvals' => true,
                'push_reminders' => true,
            ],
            'integrations' => $settings['integrations'] ?? null,
        ]);
    }

    /**
     * Ayarları güncelle
     */
    public function update(Request $request): JsonResponse
    {
        $company = auth()->user()->company;

        if (! $company) {
            return $this->notFound('Firma bulunamadı');
        }

        $validated = $request->validate([
            'smtp' => 'sometimes|array',
            'smtp.host' => 'required_with:smtp|string|max:255',
            'smtp.port' => 'required_with:smtp|integer|min:1|max:65535',
            'smtp.encryption' => 'required_with:smtp|in:tls,ssl,none',
            'smtp.username' => 'required_with:smtp|string|max:255',
            'smtp.password' => 'required_with:smtp|string|max:255',
            'smtp.from_address' => 'required_with:smtp|email|max:255',
            'smtp.from_name' => 'required_with:smtp|string|max:255',

            'sms' => 'sometimes|array',
            'sms.provider' => 'required_with:sms|in:netgsm,iletimerkezi,twilio,custom',
            'sms.username' => 'required_with:sms|string|max:255',
            'sms.password' => 'required_with:sms|string|max:255',
            'sms.sender' => 'required_with:sms|string|max:20',
            'sms.api_url' => 'nullable|url|max:500',

            'general' => 'sometimes|array',
            'general.timezone' => 'sometimes|string|max:100',
            'general.language' => 'sometimes|in:tr,en',
            'general.date_format' => 'sometimes|string|max:20',
            'general.currency' => 'sometimes|string|max:10',
            'general.working_days' => 'sometimes|array',
            'general.working_days.*' => 'integer|min:0|max:6',
            'general.default_work_start' => 'sometimes|date_format:H:i',
            'general.default_work_end' => 'sometimes|date_format:H:i',
            'general.late_tolerance_minutes' => 'sometimes|integer|min:0|max:180',

            'notifications' => 'sometimes|array',
            'notifications.email_enabled' => 'sometimes|boolean',
            'notifications.email_leave_requests' => 'sometimes|boolean',
            'notifications.email_approvals' => 'sometimes|boolean',
            'notifications.email_reminders' => 'sometimes|boolean',
            'notifications.email_reports' => 'sometimes|boolean',
            'notifications.sms_enabled' => 'sometimes|boolean',
            'notifications.sms_leave_requests' => 'sometimes|boolean',
            'notifications.sms_approvals' => 'sometimes|boolean',
            'notifications.sms_reminders' => 'sometimes|boolean',
            'notifications.push_enabled' => 'sometimes|boolean',
            'notifications.push_leave_requests' => 'sometimes|boolean',
            'notifications.push_approvals' => 'sometimes|boolean',
            'notifications.push_reminders' => 'sometimes|boolean',

            'integrations' => 'sometimes|array',
            'integrations.webhook_url' => 'nullable|url|max:500',
            'integrations.api_key' => 'nullable|string|max:255',
        ]);

        $settings = $company->settings ?? [];
        $oldSettings = $settings;

        // Ayarları merge et
        foreach ($validated as $key => $value) {
            if (is_array($value)) {
                $settings[$key] = array_merge($settings[$key] ?? [], $value);
            } else {
                $settings[$key] = $value;
            }
        }

        // SMTP şifresini encrypt et (eğer varsa)
        if (isset($settings['smtp']['password']) && ! empty($settings['smtp']['password'])) {
            $settings['smtp']['password'] = encrypt($settings['smtp']['password']);
        } elseif (isset($oldSettings['smtp']['password'])) {
            // Şifre değiştirilmediyse eski şifreyi koru
            $settings['smtp']['password'] = $oldSettings['smtp']['password'];
        }

        // SMS şifresini encrypt et (eğer varsa)
        if (isset($settings['sms']['password']) && ! empty($settings['sms']['password'])) {
            $settings['sms']['password'] = encrypt($settings['sms']['password']);
        } elseif (isset($oldSettings['sms']['password'])) {
            // Şifre değiştirilmediyse eski şifreyi koru
            $settings['sms']['password'] = $oldSettings['sms']['password'];
        }

        $oldValues = $company->toArray();
        $company->update(['settings' => $settings]);

        return $this->success($settings, 'Ayarlar güncellendi');
    }

    /**
     * SMTP test mail gönder
     */
    public function testSmtp(Request $request): JsonResponse
    {
        $company = auth()->user()->company;

        if (! $company) {
            return $this->notFound('Firma bulunamadı');
        }

        $request->validate([
            'to' => 'required|email',
        ]);

        $settings = $company->settings['smtp'] ?? null;

        if (! $settings) {
            return $this->error('SMTP ayarları yapılandırılmamış', 400);
        }

        try {
            // Şifreyi decrypt et
            $password = decrypt($settings['password']);

            // Geçici mail config
            config([
                'mail.mailers.smtp.host' => $settings['host'],
                'mail.mailers.smtp.port' => $settings['port'],
                'mail.mailers.smtp.encryption' => $settings['encryption'] === 'none' ? null : $settings['encryption'],
                'mail.mailers.smtp.username' => $settings['username'],
                'mail.mailers.smtp.password' => $password,
                'mail.from.address' => $settings['from_address'],
                'mail.from.name' => $settings['from_name'],
            ]);

            Mail::raw('Bu bir test e-postasıdır. SMTP ayarlarınız doğru çalışıyor.', function ($message) use ($request, $settings) {
                $message->to($request->to)
                    ->from($settings['from_address'], $settings['from_name'])
                    ->subject('SMTP Test E-postası - '.$company->name);
            });

            ActivityLog::log(
                'test_smtp',
                $company,
                "SMTP test maili gönderildi: {$request->to}",
                null,
                ['to' => $request->to]
            );

            return $this->success(null, 'Test e-postası başarıyla gönderildi');
        } catch (\Exception $e) {
            ActivityLog::log(
                'test_smtp',
                $company,
                "SMTP test maili başarısız: {$e->getMessage()}",
                null,
                ['to' => $request->to],
                false,
                $e->getMessage()
            );

            return $this->error('SMTP test başarısız: '.$e->getMessage(), 400);
        }
    }

    /**
     * SMS test gönder
     */
    public function testSms(Request $request): JsonResponse
    {
        $company = auth()->user()->company;

        if (! $company) {
            return $this->notFound('Firma bulunamadı');
        }

        $request->validate([
            'phone' => 'required|string|max:20',
        ]);

        $settings = $company->settings['sms'] ?? null;

        if (! $settings) {
            return $this->error('SMS ayarları yapılandırılmamış', 400);
        }

        try {
            $smsService = app(\App\Services\SmsService::class);
            $result = $smsService->send($request->phone, 'Bu bir test SMS mesajıdır. SMS ayarlarınız doğru çalışıyor.');

            ActivityLog::log(
                'test_sms',
                $company,
                "SMS test mesajı gönderildi: {$request->phone}",
                null,
                ['phone' => $request->phone, 'result' => $result]
            );

            return $this->success($result, 'Test SMS başarıyla gönderildi');
        } catch (\Exception $e) {
            ActivityLog::log(
                'test_sms',
                $company,
                "SMS test mesajı başarısız: {$e->getMessage()}",
                null,
                ['phone' => $request->phone],
                false,
                $e->getMessage()
            );

            return $this->error('SMS test başarısız: '.$e->getMessage(), 400);
        }
    }
}

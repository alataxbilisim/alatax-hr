<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Şifre sıfırlama — SPA reset URL'sine yönlendirir (Laravel web route kullanmaz).
 */
class ResetPasswordNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $token) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = $this->resetUrl($notifiable);

        return (new MailMessage)
            ->subject(config('app.name').' — Şifre Sıfırlama')
            ->markdown('emails.reset-password', [
                'url' => $url,
                'user' => $notifiable,
                'expireMinutes' => (int) config('auth.passwords.'.config('auth.defaults.passwords').'.expire', 60),
            ]);
    }

    /**
     * Kullanıcı tipine göre doğru SPA'nın /reset-password sayfası.
     */
    protected function resetUrl(object $notifiable): string
    {
        $base = rtrim($this->frontendBaseUrl($notifiable), '/');
        $email = $notifiable instanceof User ? $notifiable->getEmailForPasswordReset() : (string) $notifiable->email;

        return $base.'/reset-password?'.http_build_query([
            'token' => $this->token,
            'email' => $email,
        ]);
    }

    protected function frontendBaseUrl(object $notifiable): string
    {
        if ($notifiable instanceof User) {
            if ($notifiable->isSuperAdmin()) {
                return (string) config('app.frontend_urls.superadmin');
            }

            // Portal personeli (employee kaydı varsa)
            if ($notifiable->relationLoaded('employee')
                ? $notifiable->employee !== null
                : $notifiable->employee()->exists()) {
                return (string) config('app.frontend_urls.portal');
            }
        }

        return (string) config('app.frontend_urls.company', config('app.frontend_url'));
    }
}

<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Admin tarafından şifre sıfırlandığında kullanıcıya bildirim.
 */
class PasswordResetByAdmin extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public string $newPassword,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: config('app.name').' — Şifreniz Sıfırlandı',
        );
    }

    public function content(): Content
    {
        $base = $this->frontendBaseUrl();
        $loginUrl = rtrim($base, '/').'/login';

        return new Content(
            markdown: 'emails.password-reset-by-admin',
            with: [
                'user' => $this->user,
                'newPassword' => $this->newPassword,
                'loginUrl' => $loginUrl,
            ],
        );
    }

    protected function frontendBaseUrl(): string
    {
        if ($this->user->isSuperAdmin()) {
            return (string) config('app.frontend_urls.superadmin');
        }

        if ($this->user->employee()->exists()) {
            return (string) config('app.frontend_urls.portal');
        }

        return (string) config('app.frontend_urls.company', config('app.frontend_url'));
    }
}

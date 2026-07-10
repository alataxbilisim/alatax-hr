<?php

namespace App\Mail;

use App\Models\Company;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Personel portal daveti — geçici şifre + portal giriş linki.
 */
class EmployeeInvitation extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public Employee $employee,
        public string $temporaryPassword,
        public ?string $invitationToken = null,
    ) {}

    public function envelope(): Envelope
    {
        $companyName = $this->user->company?->name ?? config('app.name');

        return new Envelope(
            subject: __('messages.mail.employee_invitation_subject', ['company' => $companyName]),
        );
    }

    public function content(): Content
    {
        $portalBase = rtrim((string) config('app.frontend_urls.portal', 'http://localhost:3003'), '/');
        $loginUrl = $portalBase.'/login';
        $inviteUrl = $this->invitationToken
            ? $portalBase.'/invite/'.$this->invitationToken
            : null;

        /** @var Company|null $company */
        $company = $this->user->company;

        return new Content(
            markdown: 'emails.employee-invitation',
            with: [
                'user' => $this->user,
                'employee' => $this->employee,
                'company' => $company,
                'temporaryPassword' => $this->temporaryPassword,
                'loginUrl' => $loginUrl,
                'inviteUrl' => $inviteUrl,
            ],
        );
    }
}

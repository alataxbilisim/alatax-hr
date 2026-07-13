<?php

namespace App\Mail;

use App\Models\Company;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class UserInvitation extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Company $company,
        public string $email,
        public string $invitationToken,
        public ?string $role = null
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('messages.mail.invitation_subject', ['company' => $this->company->name]),
        );
    }

    public function content(): Content
    {
        $base = rtrim((string) config('app.frontend_urls.company', config('app.frontend_url', 'http://localhost:3002')), '/');
        $invitationUrl = $base.'/invite/'.$this->invitationToken;

        return new Content(
            markdown: 'emails.user-invitation',
            with: [
                'company' => $this->company,
                'email' => $this->email,
                'invitationUrl' => $invitationUrl,
                'role' => $this->role,
                'companyLogoUrl' => $this->company->logo
                    ? asset('storage/'.$this->company->logo)
                    : null,
            ],
        );
    }
}

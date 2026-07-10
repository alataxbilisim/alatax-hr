<?php

namespace App\Mail;

use App\Models\Company;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class UserInvitation extends Mailable
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
            subject: $this->company->name . ' - Sistem Daveti',
        );
    }

    public function content(): Content
    {
        $invitationUrl = config('app.frontend_url', 'http://localhost:3000') . '/invite/' . $this->invitationToken;

        return new Content(
            view: 'emails.user-invitation',
            with: [
                'company' => $this->company,
                'email' => $this->email,
                'invitationUrl' => $invitationUrl,
                'role' => $this->role,
            ],
        );
    }
}


<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ResetPasswordMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $resetUrl,
        public string $name,
        public int $expiresInMinutes
    ) {}

    public function envelope(): Envelope
    {
        $from = config('mail.from');
        return new Envelope(
            subject: 'Reset Your Password – ' . config('app.name'),
            from: new Address($from['address'] ?? 'noreply@example.com', $from['name'] ?? config('app.name')),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.reset-password'
        );
    }
}

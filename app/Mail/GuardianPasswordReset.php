<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class GuardianPasswordReset extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public readonly string $token,
    ) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your Guardian Portal password reset link',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        $url = route('guardian.password.reset', ['token' => $this->token]);

        return new Content(
            view: 'mail.guardian-password-reset',
            with: ['resetUrl' => $url],
        );
    }
}

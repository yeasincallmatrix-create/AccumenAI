<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EmailOtpMail extends Mailable implements ShouldQueue, ShouldBeEncrypted
{
    use Queueable, SerializesModels;

    public function __construct(public string $code, public string $maskedEmail)
    {
        $this->onConnection(config('queue.default', 'database'));
        $this->onQueue(config('notifications.delivery.queue', 'notifications'));
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your verification code',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.email-otp',
            with: [
                'code' => $this->code,
                'maskedEmail' => $this->maskedEmail,
            ],
        );
    }

    public function build()
    {
        // Fallback for older Laravel without Mailables
        return $this->subject('Your verification code')
            ->view('emails.email-otp')
            ->with(['code' => $this->code, 'maskedEmail' => $this->maskedEmail]);
    }
}

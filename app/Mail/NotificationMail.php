<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Plain-text, framework-standard mailable used by the notification engine.
 * Rendered subject/body are stored on the notification log before dispatch, so
 * the mailable itself is intentionally stateless. It is NOT queueable — the
 * SendNotificationJob already provides the queue boundary, so the email is sent
 * synchronously within the job and the log status stays accurate.
 */
class NotificationMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(public string $subjectText, public string $bodyText) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->subjectText);
    }

    public function content(): Content
    {
        return new Content(text: 'mail.notification');
    }
}

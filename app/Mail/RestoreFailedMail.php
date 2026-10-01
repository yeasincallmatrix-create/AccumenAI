<?php

namespace App\Mail;

use App\Models\RestoreLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class RestoreFailedMail extends Mailable implements ShouldQueue, ShouldBeEncrypted
{
    use Queueable, SerializesModels;

    public function __construct(public RestoreLog $log, public string $error)
    {
        $this->onConnection(config('queue.default', 'database'));
        $this->onQueue(config('notifications.delivery.queue', 'notifications'));
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your restore failed',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.restore.failed',
            with: [
                'log'   => $this->log,
                'error' => $this->error,
            ],
        );
    }

    public function build()
    {
        return $this->subject('Your restore failed')
            ->view('emails.restore.failed')
            ->with([
                'log'   => $this->log,
                'error' => $this->error,
            ]);
    }
}

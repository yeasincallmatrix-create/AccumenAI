<?php

namespace App\Mail;

use App\Models\Backup;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BackupFailedMail extends Mailable implements ShouldQueue, ShouldBeEncrypted
{
    use Queueable, SerializesModels;

    public function __construct(public Backup $backup, public string $error)
    {
        $this->onConnection(config('queue.default', 'database'));
        $this->onQueue(config('notifications.delivery.queue', 'notifications'));
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your backup failed',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.backup.failed',
            with: [
                'backup' => $this->backup,
                'error'  => $this->error,
            ],
        );
    }

    public function build()
    {
        return $this->subject('Your backup failed')
            ->view('emails.backup.failed')
            ->with([
                'backup' => $this->backup,
                'error'  => $this->error,
            ]);
    }
}

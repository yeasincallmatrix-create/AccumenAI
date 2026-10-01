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

class BackupCompleteMail extends Mailable implements ShouldQueue, ShouldBeEncrypted
{
    use Queueable, SerializesModels;

    public function __construct(public Backup $backup)
    {
        $this->onConnection(config('queue.default', 'database'));
        $this->onQueue(config('notifications.delivery.queue', 'notifications'));
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your backup completed successfully',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.backup.complete',
            with: [
                'backup' => $this->backup,
                'sizeMb' => number_format($this->backup->size_bytes / 1048576, 2),
            ],
        );
    }

    public function build()
    {
        return $this->subject('Your backup completed successfully')
            ->view('emails.backup.complete')
            ->with([
                'backup' => $this->backup,
                'sizeMb' => number_format($this->backup->size_bytes / 1048576, 2),
            ]);
    }
}

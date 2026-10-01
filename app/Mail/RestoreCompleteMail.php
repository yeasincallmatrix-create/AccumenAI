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

class RestoreCompleteMail extends Mailable implements ShouldQueue, ShouldBeEncrypted
{
    use Queueable, SerializesModels;

    public function __construct(public RestoreLog $log)
    {
        $this->onConnection(config('queue.default', 'database'));
        $this->onQueue(config('notifications.delivery.queue', 'notifications'));
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your restore completed successfully',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.restore.complete',
            with: ['log' => $this->log],
        );
    }

    public function build()
    {
        return $this->subject('Your restore completed successfully')
            ->view('emails.restore.complete')
            ->with(['log' => $this->log]);
    }
}

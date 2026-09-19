<?php

namespace App\Events;

use App\Models\LabIntegration\LabMessage;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Phase 6 scaffold: fired after an analyzer result is stored.
 *
 * With BROADCAST_CONNECTION=log (current) this is a log-only no-op.
 * Reverb install + private channel auth arrive in Phase 9; the polling
 * dashboard (Phase 5) is unaffected. No listeners attached in v1 —
 * listeners must be idempotent and must not throw exceptions.
 */
class LabAnalyzerResultStored implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public LabMessage $message) {}

    public function broadcastOn(): array
    {
        return [
            new Channel('institute.'.$this->message->institute_id.'.lab-analyzer'),
            new Channel('lab-analyzer.'.$this->message->analyzer_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'result.stored';
    }

    public function broadcastWith(): array
    {
        // Accession + counts only — never patient PII on the wire.
        return [
            'message_id' => $this->message->id,
            'analyzer_id' => $this->message->analyzer_id,
            'accession_number' => $this->message->accession_number,
        ];
    }
}

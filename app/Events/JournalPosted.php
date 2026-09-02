<?php

namespace App\Events;

use App\Models\Journal;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired after a journal is successfully posted.
 *
 * Listeners must be idempotent and must not throw exceptions that
 * could roll back the posting transaction.
 */
class JournalPosted
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Journal $journal,
        public readonly int $instituteId,
        public readonly ?int $branchId,
        public readonly int $actorId,
    ) {}
}

<?php

namespace App\Listeners;

use App\Events\JournalPosted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

/**
 * Logs journal posting events for observability.
 * Idempotent — pure logging, no side effects.
 */
class LogJournalPosted implements ShouldQueue
{
    public function handle(JournalPosted $event): void
    {
        Log::info('JournalPosted event', [
            'journal_id' => $event->journal->id,
            'journal_no' => $event->journal->journal_no,
            'type' => $event->journal->type,
            'institute_id' => $event->instituteId,
            'branch_id' => $event->branchId,
        ]);
    }
}

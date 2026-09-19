<?php

namespace App\Services\LabIntegration;

use App\Jobs\ProcessAnalyzerMessage;
use App\Models\LabIntegration\LabMessage;
use App\Models\Medical\ClinicalAuditLog;

class DeadLetterService
{
    /**
     * Retry a dead-letter message (dispatch fresh job).
     */
    public function retry(LabMessage $message, int $userId, ?string $notes = null): bool
    {
        if (! $message->isDeadLetter() && $message->status !== 'error') {
            return false;
        }

        $message->update([
            'status' => 'received',
            'attempts' => 0,
            'resolution_status' => 'retried',
            'resolution_notes' => $notes,
            'resolved_by' => $userId,
            'resolved_at' => now(),
            'error_code' => null,
            'error_message' => null,
        ]);

        ProcessAnalyzerMessage::dispatch($message->id, $message->analyzer_id);
        $this->audit($message, 'dead_letter.retried', ['new' => ['resolution_notes' => $notes]]);

        return true;
    }

    /**
     * Mark as manually resolved (admin found sample / fixed mapping).
     */
    public function resolveManually(LabMessage $message, int $userId, string $notes): bool
    {
        $message->update([
            'resolution_status' => 'resolved_manual',
            'resolution_notes' => $notes,
            'resolved_by' => $userId,
            'resolved_at' => now(),
        ]);
        $this->audit($message, 'dead_letter.resolved_manual', ['new' => ['resolution_notes' => $notes]]);

        return true;
    }

    /**
     * Discard with reason (no retry).
     */
    public function discard(LabMessage $message, int $userId, string $reason): bool
    {
        $message->update([
            'status' => 'dead',
            'resolution_status' => 'discarded',
            'resolution_notes' => $reason,
            'resolved_by' => $userId,
            'resolved_at' => now(),
        ]);
        $this->audit($message, 'dead_letter.discarded', ['new' => ['resolution_notes' => $reason]]);

        return true;
    }

    /**
     * Escalate for pathologist review.
     */
    public function escalate(LabMessage $message, int $userId, string $reason): bool
    {
        $message->update([
            'resolution_status' => 'escalated',
            'resolution_notes' => $reason,
            'resolved_by' => $userId,
            'resolved_at' => now(),
        ]);
        $this->audit($message, 'dead_letter.escalated', ['new' => ['resolution_notes' => $reason]]);

        return true;
    }

    protected function audit(LabMessage $message, string $action, array $options = []): void
    {
        try {
            ClinicalAuditLog::record($message, $action, $options);
        } catch (\Throwable $e) {
            \Log::warning('DeadLetterService audit failed', ['action' => $action, 'error' => $e->getMessage()]);
        }
    }
}

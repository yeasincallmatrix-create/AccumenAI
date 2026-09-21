<?php

namespace App\Jobs;

use App\Models\LabIntegration\LabAnalyzer;
use App\Models\LabIntegration\LabMessage;
use App\Services\LabIntegration\AnalyzerAdapterRegistry;
use App\Services\LabIntegration\LabResultIngestService;
use App\Support\TenantContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessAnalyzerMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;
    public int $backoff = 30;

    public function __construct(public int $messageId, public int $analyzerId) {}

    public function handle(
        AnalyzerAdapterRegistry $registry,
        LabResultIngestService $ingest
    ): void {
        $message = LabMessage::withoutGlobalScopes()->find($this->messageId);
        $analyzer = LabAnalyzer::withoutGlobalScopes()->find($this->analyzerId);

        if (! $message || ! $analyzer) {
            \Log::warning('ProcessAnalyzerMessage: missing message or analyzer', [
                'message_id' => $this->messageId,
                'analyzer_id' => $this->analyzerId,
            ]);

            return;
        }

        // Scope all downstream writes to the message's institute
        // (queue workers run without request tenant context).
        TenantContext::set($message->institute_id);

        try {
            // Idempotency: if already stored, skip
            if ($message->status === 'stored') {
                return;
            }

            // Resolve adapter
            $adapter = $registry->resolve($analyzer->adapter_key, $analyzer->adapter_version);
            if (! $adapter) {
                throw new \RuntimeException("Adapter not found: {$analyzer->adapter_key}:{$analyzer->adapter_version}");
            }

            // Parse (adapter wraps Phase 2 parser + enrich)
            $parsed = $adapter->parse($message->raw_payload, $analyzer);

            $message->update([
                'parsed_json' => $parsed,
                'status' => 'parsed',
                'attempts' => $message->attempts + 1,
                'last_attempted_at' => now(),
            ]);

            // Ingest
            $result = $ingest->ingest($analyzer, $message->fresh());

            \Log::info('ProcessAnalyzerMessage: ingest complete', [
                'message_id' => $message->id,
                'status' => $result['status'],
                'result_count' => count($result['lab_result_ids']),
            ]);
        } catch (\Throwable $e) {
            // Re-fetch attempt count defensively (update may have failed).
            $message->refresh();
            $message->update([
                'attempts' => $message->attempts + 1,
                'last_attempted_at' => now(),
                'error_code' => 'PROCESSING_ERROR',
                'error_message' => substr($e->getMessage(), 0, 500),
            ]);

            // Dead-letter after max attempts
            if ($message->attempts >= $this->tries) {
                $message->update(['status' => 'dead']);
            }

            \Log::error('ProcessAnalyzerMessage: failed', [
                'message_id' => $message->id,
                'error' => $e->getMessage(),
            ]);

            throw $e; // let Laravel retry
        } finally {
            TenantContext::clear();
        }
    }
}

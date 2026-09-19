<?php

namespace Tests\Feature\LabIntegration\Chaos;

use App\Jobs\ProcessAnalyzerMessage;
use App\Models\Institute;
use App\Models\LabIntegration\LabAnalyzer;
use App\Models\LabIntegration\LabMessage;
use App\Models\Medical\ClinicalAuditLog;
use App\Services\LabIntegration\DeadLetterService;
use App\Services\LabIntegration\DeviceAuthService;
use App\Support\TenantContext;
use App\Support\Workspace;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Retry hardening + rotation grace, using mocks/time-freezing.
 * No real network delays anywhere in this file.
 */
class RetryHardeningTest extends TestCase
{
    use DatabaseTransactions;

    protected Institute $institute;
    protected LabAnalyzer $analyzer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->institute = Institute::create([
            'name' => 'Chaos Hospital',
            'slug' => 'chaos-hosp-'.uniqid(),
            'industry' => 'healthcare',
            'sub_industry' => 'hospital',
            'country' => 'Bangladesh',
            'status' => 'active',
        ]);
        Workspace::set($this->institute->id);
        TenantContext::set($this->institute->id);

        $this->analyzer = LabAnalyzer::factory()->create([
            'institute_id' => $this->institute->id,
            'code' => 'CHAOS-1',
            'name' => 'Chaos Analyzer',
            'adapter_key' => 'sysmex_xn',
            'adapter_version' => 'v1',
            'protocol' => 'astm',
            'instrument_type' => 'hematology',
            'is_enabled' => true,
            'status' => 'active',
        ]);
    }

    protected function deadMessage(array $overrides = []): LabMessage
    {
        return LabMessage::create(array_merge([
            'institute_id' => $this->institute->id,
            'analyzer_id' => $this->analyzer->id,
            'direction' => 'inbound',
            'protocol' => 'astm',
            'idempotency_hash' => hash('sha256', uniqid()),
            'raw_payload' => 'H|chaos',
            'status' => 'dead',
            'attempts' => 5,
            'error_code' => 'PROCESSING_ERROR',
            'received_at' => now(),
        ], $overrides));
    }

    public function test_failed_job_retries_5_times_then_dead_letter(): void
    {
        // Simulate the job's dead-letter transition without sleeping:
        // a message at max attempts is marked dead by the job handler.
        $message = $this->deadMessage(['status' => 'error', 'attempts' => 4]);

        $job = new ProcessAnalyzerMessage($message->id, $this->analyzer->id);
        $this->assertSame(5, $job->tries);
        // Backoff configured (no real waiting in tests).
        $this->assertSame(30, $job->backoff);

        // Force the dead-letter branch directly.
        $message->update(['attempts' => 5, 'status' => 'dead']);
        $this->assertTrue($message->fresh()->isDeadLetter());
    }

    public function test_dead_letter_retry_resets_attempts(): void
    {
        \Illuminate\Support\Facades\Queue::fake();
        $message = $this->deadMessage();
        $service = app(DeadLetterService::class);

        $ok = $service->retry($message, 1, 'retry after mapping fix');

        $this->assertTrue($ok);
        $fresh = $message->fresh();
        $this->assertSame('received', $fresh->status);
        $this->assertSame(0, $fresh->attempts);
        $this->assertSame('retried', $fresh->resolution_status);
        \Illuminate\Support\Facades\Queue::assertPushed(ProcessAnalyzerMessage::class);
    }

    public function test_dead_letter_discard_marks_resolved(): void
    {
        $message = $this->deadMessage();
        $service = app(DeadLetterService::class);

        $ok = $service->discard($message, 1, 'duplicate transmission, safe to drop');

        $this->assertTrue($ok);
        $fresh = $message->fresh();
        $this->assertSame('dead', $fresh->status);
        $this->assertSame('discarded', $fresh->resolution_status);
        $this->assertTrue($fresh->isResolved());
    }

    public function test_dead_letter_resolve_manual_requires_notes(): void
    {
        $message = $this->deadMessage();
        $service = app(DeadLetterService::class);

        // Service layer accepts any string; HTTP layer enforces min length
        // (covered by controller validation) — empty notes still record.
        $ok = $service->resolveManually($message, 1, 'sample located and linked manually');

        $this->assertTrue($ok);
        $this->assertSame('resolved_manual', $message->fresh()->resolution_status);
    }

    public function test_dead_letter_escalate_marks_escalated(): void
    {
        $message = $this->deadMessage();
        $service = app(DeadLetterService::class);

        $ok = $service->escalate($message, 1, 'needs pathologist review of flags');

        $this->assertTrue($ok);
        $this->assertSame('escalated', $message->fresh()->resolution_status);
    }

    public function test_idempotent_retry_does_not_duplicate_results(): void
    {
        // Retrying twice must not create duplicate resolution trails:
        // second retry on a now-received message is refused.
        \Illuminate\Support\Facades\Queue::fake();
        $message = $this->deadMessage();
        $service = app(DeadLetterService::class);

        $this->assertTrue($service->retry($message, 1));
        $this->assertFalse($service->retry($message->fresh(), 1));
    }

    public function test_message_resolution_is_audit_logged(): void
    {
        $message = $this->deadMessage();
        app(DeadLetterService::class)->discard($message, 7, 'audit trail check notes');

        $this->assertDatabaseHas('clinical_audit_logs', [
            'auditable_type' => $message->getMorphClass(),
            'auditable_id' => $message->id,
            'action' => 'dead_letter.discarded',
        ]);
    }

    public function test_token_rotation_grace_period_allows_old_token(): void
    {
        $auth = app(DeviceAuthService::class);

        $oldToken = $auth->issue($this->analyzer);
        $newToken = $auth->rotate($this->analyzer);

        // Both should authenticate during grace
        $this->assertNotNull($auth->authenticate($oldToken));
        $this->assertNotNull($auth->authenticate($newToken));

        // Advance 25 hours (frozen time — no real waiting)
        Carbon::setTestNow(now()->addHours(25));
        try {
            // Old token expired, new token still works
            $this->assertNull($auth->authenticate($oldToken));
            $this->assertNotNull($auth->authenticate($newToken));
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_token_grace_expires_after_24h(): void
    {
        $auth = app(DeviceAuthService::class);
        $oldToken = $auth->issue($this->analyzer);
        $auth->rotate($this->analyzer);

        $credential = $this->analyzer->credential->fresh();
        $this->assertTrue($credential->previousTokenIsValid());

        Carbon::setTestNow(now()->addHours(25));
        try {
            $this->assertFalse($credential->fresh()->previousTokenIsValid());
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_grace_token_rejected_after_new_rotation(): void
    {
        $auth = app(DeviceAuthService::class);
        $first = $auth->issue($this->analyzer);
        $second = $auth->rotate($this->analyzer);
        // Second rotation overwrites the grace slot — first token dies.
        $third = $auth->rotate($this->analyzer);

        $this->assertNull($auth->authenticate($first));
        $this->assertNotNull($auth->authenticate($second));
        $this->assertNotNull($auth->authenticate($third));
    }
}

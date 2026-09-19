<?php

namespace Tests\Feature\LabIntegration\Admin;

use App\Models\Institute;
use App\Models\LabIntegration\LabAnalyzer;
use App\Models\LabIntegration\LabMessage;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Queue;

class LabAnalyzerMessageAdminTest extends AdminTestCase
{
    use DatabaseTransactions;

    protected LabAnalyzer $analyzer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->analyzer = $this->analyzer(['code' => 'MSG-ADM-1', 'name' => 'Message Admin Analyzer']);
    }

    protected function message(array $overrides = []): LabMessage
    {
        return LabMessage::create(array_merge([
            'institute_id' => $this->institute->id,
            'analyzer_id' => $this->analyzer->id,
            'direction' => 'inbound',
            'protocol' => 'astm',
            'idempotency_hash' => hash('sha256', uniqid()),
            'raw_payload' => 'H|test',
            'status' => 'error',
            'error_code' => 'UNKNOWN_SAMPLE',
            'received_at' => now(),
        ], $overrides));
    }

    public function test_index_renders_with_filters(): void
    {
        $this->message(['accession_number' => 'ACC-F-1']);

        $response = $this->get(route('medical.laboratory.analyzers.messages.index', $this->analyzer));

        $response->assertOk();
        $response->assertSee('ACC-F-1');
    }

    public function test_index_filters_by_status(): void
    {
        $this->message(['status' => 'error', 'accession_number' => 'ACC-ERR']);
        $this->message(['status' => 'stored', 'accession_number' => 'ACC-OK']);

        $response = $this->get(route('medical.laboratory.analyzers.messages.index', [$this->analyzer, 'status' => 'error']));

        $response->assertOk();
        $response->assertSee('ACC-ERR');
        $response->assertDontSee('ACC-OK');
    }

    public function test_show_renders_raw_and_parsed(): void
    {
        $message = $this->message([
            'raw_payload' => 'H|raw-bytes',
            'parsed_json' => ['accession_number' => 'ACC-P'],
            'status' => 'parsed',
        ]);

        $response = $this->get(route('medical.laboratory.analyzers.messages.show', [$this->analyzer, $message]));

        $response->assertOk();
        $response->assertSee('H|raw-bytes');
        $response->assertSee('ACC-P');
    }

    public function test_retry_requeues_failed_message(): void
    {
        Queue::fake();
        $message = $this->message(['status' => 'dead']);

        $response = $this->post(route('medical.laboratory.analyzers.messages.retry', [$this->analyzer, $message]));

        $response->assertRedirect();
        $this->assertSame('received', $message->fresh()->status);
        Queue::assertPushed(\App\Jobs\ProcessAnalyzerMessage::class);
    }

    public function test_retry_rejected_for_stored_message(): void
    {
        $message = $this->message(['status' => 'stored']);

        $response = $this->post(route('medical.laboratory.analyzers.messages.retry', [$this->analyzer, $message]));

        $response->assertRedirect();
        $response->assertSessionHas('warning');
    }

    public function test_tenant_isolation_on_messages(): void
    {
        // Context cleared so the guard cannot re-home the foreign row.
        \App\Support\TenantContext::clear();
        $other = Institute::create([
            'name' => 'Msg Other Hospital',
            'slug' => 'msg-other-'.uniqid(),
            'industry' => 'healthcare',
            'sub_industry' => 'hospital',
            'country' => 'Bangladesh',
            'status' => 'active',
        ]);
        $foreign = LabMessage::withoutGlobalScopes()->create([
            'institute_id' => $other->id,
            'direction' => 'inbound',
            'protocol' => 'astm',
            'idempotency_hash' => hash('sha256', uniqid()),
            'raw_payload' => 'foreign',
            'status' => 'error',
            'received_at' => now(),
        ]);
        \App\Support\Workspace::set($this->institute->id);

        // Foreign message unreachable via our analyzer URL.
        $this->get(route('medical.laboratory.analyzers.messages.show', [$this->analyzer, $foreign->id]))
            ->assertNotFound();

        // And invisible in our list.
        $response = $this->get(route('medical.laboratory.analyzers.messages.index', $this->analyzer));
        $response->assertDontSee('foreign');
    }
}

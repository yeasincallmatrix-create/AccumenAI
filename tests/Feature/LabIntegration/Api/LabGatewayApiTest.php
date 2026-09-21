<?php

namespace Tests\Feature\LabIntegration\Api;

use App\Models\Institute;
use App\Models\LabIntegration\LabAnalyzer;
use App\Models\LabIntegration\LabMessage;
use App\Models\LabIntegration\LabWorklist;
use App\Services\LabIntegration\DeviceAuthService;
use App\Services\LabIntegration\HmacSignature;
use App\Support\TenantContext;
use App\Support\Workspace;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class LabGatewayApiTest extends TestCase
{
    use DatabaseTransactions;

    protected LabAnalyzer $analyzer;
    protected string $token;
    protected Institute $institute;

    protected function setUp(): void
    {
        parent::setUp();

        $this->institute = Institute::create([
            'name' => 'Gateway API Hospital',
            'slug' => 'gateway-api-'.uniqid(),
            'industry' => 'healthcare',
            'sub_industry' => 'hospital',
            'country' => 'Bangladesh',
            'status' => 'active',
        ]);
        Workspace::set($this->institute->id);
        TenantContext::set($this->institute->id);

        $this->analyzer = LabAnalyzer::factory()->create([
            'institute_id' => $this->institute->id,
            'code' => 'GW-API-1',
            'name' => 'Gateway API Analyzer',
            'manufacturer' => 'Sysmex',
            'model' => 'XN-550',
            'adapter_key' => 'sysmex_xn',
            'adapter_version' => 'v1',
            'protocol' => 'astm',
            'instrument_type' => 'hematology',
            'capabilities' => ['result_upload' => true, 'worklist' => true, 'query' => false, 'bidirectional' => true],
            'is_enabled' => true,
            'status' => 'active',
        ]);

        $this->token = app(DeviceAuthService::class)->issue($this->analyzer);
    }

    protected function signedHeaders(string $body): array
    {
        $ts = time();
        $sig = HmacSignature::compute($body, $ts, $this->token);

        return [
            'Authorization' => 'Bearer '.$this->token,
            'X-Lab-Timestamp' => (string) $ts,
            'X-Lab-Signature' => $sig,
            'Content-Type' => 'application/json',
        ];
    }

    protected function postResults(string $payload, ?string $messageId = null, array $headers = []): \Illuminate\Testing\TestResponse
    {
        $body = ['payload' => $payload];
        if ($messageId !== null) {
            $body['message_id'] = $messageId;
        }
        if (empty($headers)) {
            $headers = $this->signedHeaders(json_encode($body));
        }

        return $this->withHeaders($headers)->postJson('/api/lab-gateway/results', $body);
    }

    protected function samplePayload(): string
    {
        return file_get_contents(base_path('tests/Fixtures/LabIntegration/Sysmex/xn550_astm_cbc_5part.txt'));
    }

    // === Authentication ===

    public function test_results_endpoint_requires_token(): void
    {
        $response = $this->postJson('/api/lab-gateway/results', ['payload' => 'x']);

        $response->assertStatus(401);
        $response->assertJsonPath('code', 'MISSING_TOKEN');
    }

    public function test_results_endpoint_rejects_invalid_token(): void
    {
        $response = $this->withHeaders(['Authorization' => 'Bearer invalid-token-value-1234567890'])
            ->postJson('/api/lab-gateway/results', ['payload' => 'x']);

        $response->assertStatus(401);
        $response->assertJsonPath('code', 'INVALID_TOKEN');
    }

    public function test_results_endpoint_rejects_missing_signature(): void
    {
        $response = $this->withHeaders(['Authorization' => 'Bearer '.$this->token])
            ->postJson('/api/lab-gateway/results', ['payload' => 'x']);

        $response->assertStatus(401);
        $response->assertJsonPath('code', 'MISSING_SIGNATURE');
    }

    public function test_results_endpoint_rejects_bad_signature(): void
    {
        $body = ['payload' => 'x'];
        $headers = [
            'Authorization' => 'Bearer '.$this->token,
            'X-Lab-Timestamp' => (string) time(),
            'X-Lab-Signature' => 'deadbeef',
            'Content-Type' => 'application/json',
        ];

        $this->withHeaders($headers)->postJson('/api/lab-gateway/results', $body)->assertStatus(401)
            ->assertJsonPath('code', 'INVALID_SIGNATURE');
    }

    public function test_results_endpoint_rejects_stale_timestamp(): void
    {
        $body = ['payload' => 'x'];
        $json = json_encode($body);
        $stale = time() - 3600;
        $headers = [
            'Authorization' => 'Bearer '.$this->token,
            'X-Lab-Timestamp' => (string) $stale,
            'X-Lab-Signature' => HmacSignature::compute($json, $stale, $this->token),
            'Content-Type' => 'application/json',
        ];

        $this->withHeaders($headers)->postJson('/api/lab-gateway/results', $body)->assertStatus(401)
            ->assertJsonPath('code', 'INVALID_SIGNATURE');
    }

    public function test_results_endpoint_accepts_valid_signed_request(): void
    {
        $this->postResults($this->samplePayload(), 'MSG-API-1')->assertStatus(202)
            ->assertJsonPath('status', 'accepted');
    }

    // === Ingest ===

    public function test_result_creates_lab_message_row(): void
    {
        $payload = $this->samplePayload();
        $response = $this->postResults($payload, 'MSG-API-2');

        $response->assertStatus(202);
        // NOTE: QUEUE_CONNECTION=sync in testing, so the job may already
        // have processed the message — assert identity columns, not status.
        $this->assertDatabaseHas('lab_messages', [
            'id' => $response->json('message_id'),
            'analyzer_id' => $this->analyzer->id,
            'institute_id' => $this->institute->id,
            'direction' => 'inbound',
        ]);
        // Raw payload stored intact (modulo framework TrimStrings on the
        // outer edges — interior bytes byte-identical).
        $this->assertSame(
            rtrim($payload),
            LabMessage::find($response->json('message_id'))->raw_payload
        );
    }

    public function test_result_dispatches_process_job(): void
    {
        Queue::fake();

        $this->postResults($this->samplePayload(), 'MSG-API-3')->assertStatus(202);

        Queue::assertPushed(\App\Jobs\ProcessAnalyzerMessage::class);
    }

    public function test_duplicate_payload_returns_duplicate_status(): void
    {
        $payload = $this->samplePayload();

        $first = $this->postResults($payload, 'MSG-API-4');
        $first->assertStatus(202);

        $second = $this->postResults($payload, 'MSG-API-4-DIFFERENT-ID');
        $second->assertStatus(200);
        $second->assertJsonPath('status', 'duplicate');
        $this->assertSame($first->json('message_id'), $second->json('message_id'));
    }

    public function test_result_stores_raw_payload_intact(): void
    {
        $payload = "H|^&|||SYSMEX^XN-550\r\nR|1|^^^WBC|7.2|10^3/uL|4.0-11.0|N||F";
        $response = $this->postResults($payload);

        $response->assertStatus(202);
        $this->assertSame($payload, LabMessage::find($response->json('message_id'))->raw_payload);
    }

    // === Health ===

    public function test_health_endpoint_returns_analyzer_info(): void
    {
        $response = $this->withHeaders(['Authorization' => 'Bearer '.$this->token])
            ->postJson('/api/lab-gateway/health', ['gateway_version' => '1.0.0']);

        $response->assertStatus(200);
        $response->assertJsonPath('status', 'ok');
        $response->assertJsonPath('analyzer.id', $this->analyzer->id);
        $response->assertJsonPath('analyzer.code', 'GW-API-1');
    }

    public function test_health_endpoint_updates_last_seen_at(): void
    {
        $this->assertNull($this->analyzer->fresh()->last_seen_at);

        $this->withHeaders(['Authorization' => 'Bearer '.$this->token])
            ->postJson('/api/lab-gateway/health', []);

        $this->assertNotNull($this->analyzer->fresh()->last_seen_at);
    }

    // === Worklist ===

    public function test_worklist_endpoint_returns_empty_for_no_pending(): void
    {
        $response = $this->withHeaders(['Authorization' => 'Bearer '.$this->token])
            ->getJson('/api/lab-gateway/worklist');

        $response->assertStatus(200);
        $response->assertJsonPath('count', 0);
    }

    public function test_worklist_endpoint_requires_worklist_capability(): void
    {
        $this->analyzer->update(['capabilities' => ['result_upload' => true, 'worklist' => false]]);

        $response = $this->withHeaders(['Authorization' => 'Bearer '.$this->token])
            ->getJson('/api/lab-gateway/worklist');

        $response->assertStatus(400);
        $response->assertJsonPath('code', 'WORKLIST_NOT_SUPPORTED');
    }

    public function test_ack_endpoint_marks_worklist_acked(): void
    {
        $worklist = LabWorklist::create([
            'institute_id' => $this->institute->id,
            'analyzer_id' => $this->analyzer->id,
            'order_snapshot' => ['accession_number' => 'ACC-WL-1'],
            'tests_snapshot' => [['code' => 'CBC']],
            'status' => 'pending',
        ]);

        // GET marks pending → sent; then ACK.
        $this->withHeaders(['Authorization' => 'Bearer '.$this->token])
            ->getJson('/api/lab-gateway/worklist')->assertStatus(200);

        $response = $this->withHeaders(['Authorization' => 'Bearer '.$this->token])
            ->postJson('/api/lab-gateway/ack', ['worklist_id' => $worklist->id]);

        $response->assertStatus(200);
        $response->assertJsonPath('status', 'acked');
        $this->assertSame('acked', $worklist->fresh()->status);
    }

    // === Tenant Isolation ===

    public function test_analyzer_from_tenant_a_cannot_post_to_tenant_b(): void
    {
        // Token is bound to analyzer A's institute; payloads always land
        // in the analyzer's own institute — never a caller-supplied one.
        $other = Institute::create([
            'name' => 'Other Tenant Hosp',
            'slug' => 'other-tenant-'.uniqid(),
            'industry' => 'healthcare',
            'sub_industry' => 'hospital',
            'country' => 'Bangladesh',
            'status' => 'active',
        ]);

        $response = $this->postResults($this->samplePayload());

        $response->assertStatus(202);
        $message = LabMessage::find($response->json('message_id'));
        $this->assertSame($this->institute->id, (int) $message->institute_id);
        $this->assertNotSame($other->id, (int) $message->institute_id);
    }

    public function test_device_token_scoped_to_single_analyzer(): void
    {
        $otherAnalyzer = LabAnalyzer::factory()->create([
            'institute_id' => $this->institute->id,
            'adapter_key' => 'sysmex_xn',
            'protocol' => 'astm',
        ]);

        // Our token resolves to OUR analyzer only.
        $response = $this->withHeaders(['Authorization' => 'Bearer '.$this->token])
            ->postJson('/api/lab-gateway/health', []);

        $response->assertJsonPath('analyzer.id', $this->analyzer->id);
        $this->assertNotSame($otherAnalyzer->id, $response->json('analyzer.id'));
    }

    public function test_revoked_token_rejected(): void
    {
        app(DeviceAuthService::class)->revoke($this->analyzer);

        $this->withHeaders(['Authorization' => 'Bearer '.$this->token])
            ->postJson('/api/lab-gateway/health', [])
            ->assertStatus(401)
            ->assertJsonPath('code', 'INVALID_TOKEN');
    }
}

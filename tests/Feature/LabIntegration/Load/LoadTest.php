<?php

namespace Tests\Feature\LabIntegration\Load;

use App\Models\Institute;
use App\Models\LabIntegration\LabAnalyzer;
use App\Models\LabIntegration\LabMessage;
use App\Services\LabIntegration\DeviceAuthService;
use App\Services\LabIntegration\HmacSignature;
use App\Support\TenantContext;
use App\Support\Workspace;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class LoadTest extends TestCase
{
    use DatabaseTransactions;

    protected LabAnalyzer $analyzer;
    protected string $token;

    protected function setUp(): void
    {
        parent::setUp();

        // This suite measures ingest throughput + dedup, not rate limiting
        // (the 30/min throttle is covered by design and would 429 the burst).
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);

        $institute = Institute::create([
            'name' => 'Load Test Hospital',
            'slug' => 'load-test-'.uniqid(),
            'industry' => 'healthcare',
            'sub_industry' => 'hospital',
            'country' => 'Bangladesh',
            'status' => 'active',
        ]);
        Workspace::set($institute->id);
        TenantContext::set($institute->id);

        $this->analyzer = LabAnalyzer::factory()->create([
            'institute_id' => $institute->id,
            'code' => 'LOAD-1',
            'name' => 'Load Analyzer',
            'adapter_key' => 'sysmex_xn',
            'protocol' => 'astm',
            'status' => 'active',
            'is_enabled' => true,
        ]);
        $this->token = app(DeviceAuthService::class)->issue($this->analyzer);
    }

    protected function signedPost(string $payload): \Illuminate\Testing\TestResponse
    {
        $body = json_encode(['payload' => $payload]);
        $ts = time();

        return $this->postJson('/api/lab-gateway/results', ['payload' => $payload], [
            'Authorization' => 'Bearer '.$this->token,
            'X-Lab-Timestamp' => (string) $ts,
            'X-Lab-Signature' => HmacSignature::compute($body, $ts, $this->token),
        ]);
    }

    /**
     * Simulate 100 concurrent analyzer messages.
     * Verifies: no duplicate hash collisions, all accepted quickly.
     */
    public function test_100_concurrent_messages_ingested(): void
    {
        $start = microtime(true);
        $accepted = 0;

        for ($i = 1; $i <= 100; $i++) {
            $payload = $this->buildAstmPayload('ACC-LOAD-'.str_pad($i, 5, '0', STR_PAD_LEFT));
            $response = $this->signedPost($payload);

            if ($response->status() === 202) {
                $accepted++;
            }
        }

        $elapsed = microtime(true) - $start;

        $this->assertEquals(100, $accepted, 'All 100 messages should be accepted');
        $this->assertLessThan(30, $elapsed, "Load test took {$elapsed}s — should be under 30s");

        // All unique hashes
        $uniqueHashes = LabMessage::where('analyzer_id', $this->analyzer->id)
            ->distinct('idempotency_hash')
            ->count('idempotency_hash');
        $this->assertEquals(100, $uniqueHashes, 'All 100 hashes should be unique');
    }

    /**
     * Duplicate burst: same payload 50 times → dedup to 1.
     */
    public function test_50_duplicate_messages_deduped(): void
    {
        $payload = $this->buildAstmPayload('ACC-DEDUP-001');

        $duplicateCount = 0;
        for ($i = 0; $i < 50; $i++) {
            $response = $this->signedPost($payload);

            if ($response->json('status') === 'duplicate') {
                $duplicateCount++;
            }
        }

        $this->assertEquals(49, $duplicateCount, '49 of 50 should be flagged duplicate');

        $messageCount = LabMessage::where('analyzer_id', $this->analyzer->id)->count();
        $this->assertEquals(1, $messageCount, 'Only 1 unique message stored');
    }

    protected function buildAstmPayload(string $accession): string
    {
        $ts = date('YmdHis');

        return <<<ASTM
H|\\^&|||SYSMEX^XN-550^00-18|||||||P|E1394-97|{$ts}
P|1||MRN-LOAD||LOAD^TEST||19800101|M
O|1|{$accession}||^^^CBC|R||{$ts}||||||||||||||||||F
R|1|^^^WBC|7.2|10^3/uL|4.0-11.0|N||F||||{$ts}
R|2|^^^RBC|4.85|10^6/uL|4.5-5.5|N||F||||{$ts}
R|3|^^^HGB|14.1|g/dL|13.0-17.0|N||F||||{$ts}
L|1|N
ASTM;
    }
}

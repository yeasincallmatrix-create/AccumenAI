<?php

namespace Tests\Feature\LabIntegration\Simulator;

use App\Models\Institute;
use App\Models\LabIntegration\LabAnalyzer;
use App\Services\LabIntegration\DeviceAuthService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class E2ESimulatorTest extends TestCase
{
    use DatabaseTransactions;

    protected function simulatorPath(): string
    {
        return base_path('simulator/src/index.js');
    }

    protected function nodeAvailable(): bool
    {
        $out = shell_exec('node --version 2>&1');

        return $out && str_starts_with(trim($out), 'v');
    }

    public function test_simulator_generates_astm_payload(): void
    {
        if (! $this->nodeAvailable()) {
            $this->markTestSkipped('Node.js not available in test environment.');
        }

        $cmd = sprintf(
            'node %s --scenario=valid_cbc --transport=stdout 2>&1',
            escapeshellarg($this->simulatorPath())
        );
        $out = shell_exec($cmd);

        $this->assertStringContainsString('H|\\^&|', $out);
        $this->assertStringContainsString('R|1|^^^WBC|', $out);
    }

    public function test_simulator_deterministic_mode_produces_identical_output(): void
    {
        if (! $this->nodeAvailable()) {
            $this->markTestSkipped('Node.js not available.');
        }

        $cmd = sprintf(
            'node %s --scenario=batch_random_10 --transport=stdout --deterministic --seed=42 2>&1',
            escapeshellarg($this->simulatorPath())
        );
        $out1 = shell_exec($cmd);
        $out2 = shell_exec($cmd);

        $this->assertEquals($out1, $out2, 'Deterministic mode should produce identical output');
    }

    public function test_simulator_lists_all_scenarios(): void
    {
        if (! $this->nodeAvailable()) {
            $this->markTestSkipped('Node.js not available.');
        }

        $cmd = sprintf('node %s --list-scenarios 2>&1', escapeshellarg($this->simulatorPath()));
        $out = shell_exec($cmd);

        $this->assertStringContainsString('valid_cbc', $out);
        $this->assertStringContainsString('malformed_hl7', $out);
        $this->assertStringContainsString('duplicate_burst', $out);
    }

    /**
     * Full loop: simulator generates payload → test POSTs to API endpoint → assert stored.
     * (Full TCP loop requires a running gateway — tested separately in Phase 9.)
     */
    public function test_simulator_payload_accepted_by_gateway_api(): void
    {
        if (! $this->nodeAvailable()) {
            $this->markTestSkipped('Node.js not available.');
        }

        // Get payload from simulator
        $cmd = sprintf(
            'node %s --scenario=valid_cbc --transport=stdout 2>&1',
            escapeshellarg($this->simulatorPath())
        );
        $rawOut = shell_exec($cmd);

        // Extract the payload between markers
        preg_match('/-----BEGIN PAYLOAD-----\n(.*)\n-----END PAYLOAD-----/s', $rawOut, $m);
        $this->assertNotEmpty($m[1], 'Simulator should emit a payload');
        $payload = $m[1];

        // Setup analyzer + credential
        $institute = Institute::first();
        $analyzer = LabAnalyzer::factory()->create([
            'institute_id' => $institute->id,
            'adapter_key' => 'sysmex_xn',
            'adapter_version' => 'v1',
            'protocol' => 'astm',
            'status' => 'active',
            'is_enabled' => true,
        ]);
        $token = app(DeviceAuthService::class)->issue($analyzer);

        // Sign + post
        $ts = time();
        $sig = \App\Services\LabIntegration\HmacSignature::compute(
            json_encode(['payload' => $payload]),
            $ts,
            $token
        );

        $response = $this->postJson('/api/lab-gateway/results', [
            'payload' => $payload,
        ], [
            'Authorization' => 'Bearer '.$token,
            'X-Lab-Timestamp' => (string) $ts,
            'X-Lab-Signature' => $sig,
        ]);

        $response->assertStatus(202);
        // Sync queue in testing may already have processed the message
        // (unknown accession → quarantined); assert receipt, not state.
        $this->assertDatabaseHas('lab_messages', [
            'analyzer_id' => $analyzer->id,
            'direction' => 'inbound',
        ]);
    }
}

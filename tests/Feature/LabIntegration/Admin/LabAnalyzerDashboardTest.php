<?php

namespace Tests\Feature\LabIntegration\Admin;

use App\Models\LabIntegration\LabAnalyzer;
use App\Models\LabIntegration\LabMessage;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class LabAnalyzerDashboardTest extends AdminTestCase
{
    use DatabaseTransactions;

    protected function analyzer(array $overrides = []): LabAnalyzer
    {
        return LabAnalyzer::create(array_merge([
            'institute_id' => $this->institute->id,
            'code' => 'DASH-'.strtoupper(uniqid()),
            'name' => 'Dashboard Analyzer',
            'instrument_type' => 'hematology',
            'protocol' => 'astm',
            'adapter_key' => 'sysmex_xn',
            'connection_type' => 'tcp',
            'is_enabled' => true,
            'status' => 'active',
        ], $overrides));
    }

    public function test_dashboard_lists_analyzers(): void
    {
        $this->analyzer(['name' => 'Visible Analyzer One']);

        $response = $this->get(route('medical.laboratory.analyzers.dashboard'));

        $response->assertOk();
        $response->assertSee('Visible Analyzer One');
    }

    public function test_status_online_when_recently_seen(): void
    {
        $this->analyzer(['name' => 'Online Box', 'last_seen_at' => now()->subMinutes(2)]);

        $response = $this->get(route('medical.laboratory.analyzers.dashboard'));

        $response->assertOk();
        $response->assertSee('Online Box');
    }

    public function test_status_offline_when_never_seen(): void
    {
        $this->analyzer(['name' => 'Silent Box', 'last_seen_at' => null]);

        $response = $this->get(route('medical.laboratory.analyzers.dashboard'));

        $response->assertOk();
        $response->assertSee('Silent Box');
        $response->assertSee('Never');
    }

    public function test_dashboard_counts_failed_messages(): void
    {
        $analyzer = $this->analyzer();
        LabMessage::create([
            'institute_id' => $this->institute->id,
            'analyzer_id' => $analyzer->id,
            'direction' => 'inbound',
            'protocol' => 'astm',
            'idempotency_hash' => hash('sha256', uniqid()),
            'raw_payload' => 'x',
            'status' => 'dead',
            'received_at' => now(),
        ]);

        $response = $this->get(route('medical.laboratory.analyzers.dashboard'));

        $response->assertOk();
        $response->assertSee('Failed Messages');
    }

    public function test_dashboard_requires_authentication(): void
    {
        auth()->logout();

        $this->get(route('medical.laboratory.analyzers.dashboard'))->assertRedirect();
    }
}

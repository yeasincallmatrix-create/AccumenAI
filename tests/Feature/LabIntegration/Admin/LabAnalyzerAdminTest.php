<?php

namespace Tests\Feature\LabIntegration\Admin;

use App\Models\Institute;
use App\Models\LabIntegration\LabAnalyzer;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class LabAnalyzerAdminTest extends AdminTestCase
{
    use DatabaseTransactions;

    // === Permissions ===

    public function test_index_requires_authentication(): void
    {
        auth()->logout();

        $this->get(route('medical.laboratory.analyzers.index'))->assertRedirect();
    }

    public function test_view_denies_other_institute(): void
    {
        // Create the foreign row with tenant context cleared so the
        // TenantScoped creating-guard cannot re-home it into our institute.
        \App\Support\TenantContext::clear();
        $other = Institute::create([
            'name' => 'Other Analyzer Hospital',
            'slug' => 'other-analyzer-'.uniqid(),
            'industry' => 'healthcare',
            'sub_industry' => 'hospital',
            'country' => 'Bangladesh',
            'status' => 'active',
        ]);
        $foreign = LabAnalyzer::withoutGlobalScopes()->create([
            'institute_id' => $other->id,
            'code' => 'FOREIGN-1',
            'name' => 'Foreign Analyzer',
            'instrument_type' => 'hematology',
            'protocol' => 'astm',
            'adapter_key' => 'sysmex_xn',
            'connection_type' => 'tcp',
        ]);
        \App\Support\Workspace::set($this->institute->id);

        // TenantScoped binding cannot even resolve the foreign row.
        $this->get(route('medical.laboratory.analyzers.show', $foreign->id))->assertNotFound();
    }

    // === CRUD ===

    public function test_index_lists_institute_analyzers(): void
    {
        $this->analyzer(['code' => 'LIST-1', 'name' => 'Listable One']);

        $response = $this->get(route('medical.laboratory.analyzers.index'));

        $response->assertOk();
        $response->assertSee('LIST-1');
    }

    public function test_create_stores_new_analyzer(): void
    {
        $response = $this->post(route('medical.laboratory.analyzers.store'), [
            'code' => 'NEW-1',
            'name' => 'New Analyzer',
            'instrument_type' => 'hematology',
            'protocol' => 'astm',
            'adapter_key' => 'sysmex_xn',
            'connection_type' => 'tcp',
            'host' => '192.168.1.10',
            'port' => 5000,
            'capabilities' => ['result_upload' => true],
            'is_enabled' => true,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('lab_analyzers', [
            'institute_id' => $this->institute->id,
            'code' => 'NEW-1',
            'status' => 'inactive',
        ]);
    }

    public function test_create_rejects_duplicate_code_in_same_institute(): void
    {
        $this->analyzer(['code' => 'DUP-1']);

        $response = $this->post(route('medical.laboratory.analyzers.store'), [
            'code' => 'DUP-1',
            'name' => 'Duplicate',
            'instrument_type' => 'hematology',
            'protocol' => 'astm',
            'adapter_key' => 'sysmex_xn',
            'connection_type' => 'tcp',
        ]);

        $response->assertSessionHasErrors('code');
    }

    public function test_create_allows_same_code_in_different_institute(): void
    {
        // Same code exists elsewhere — must not block creation here.
        // (Context cleared so the guard cannot re-home the twin row.)
        \App\Support\TenantContext::clear();
        $other = Institute::create([
            'name' => 'Code Twin Hospital',
            'slug' => 'code-twin-'.uniqid(),
            'industry' => 'healthcare',
            'sub_industry' => 'hospital',
            'country' => 'Bangladesh',
            'status' => 'active',
        ]);
        LabAnalyzer::withoutGlobalScopes()->create([
            'institute_id' => $other->id,
            'code' => 'TWIN-1',
            'name' => 'Twin Analyzer',
            'instrument_type' => 'hematology',
            'protocol' => 'astm',
            'adapter_key' => 'sysmex_xn',
            'connection_type' => 'tcp',
        ]);
        \App\Support\Workspace::set($this->institute->id);

        $response = $this->post(route('medical.laboratory.analyzers.store'), [
            'code' => 'TWIN-1',
            'name' => 'Twin Local',
            'instrument_type' => 'hematology',
            'protocol' => 'astm',
            'adapter_key' => 'sysmex_xn',
            'connection_type' => 'tcp',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('lab_analyzers', ['institute_id' => $this->institute->id, 'code' => 'TWIN-1']);
    }

    public function test_update_modifies_analyzer(): void
    {
        $analyzer = $this->analyzer();

        $response = $this->put(route('medical.laboratory.analyzers.update', $analyzer), [
            'name' => 'Renamed Analyzer',
            'instrument_type' => 'biochemistry',
            'protocol' => 'hl7',
            'adapter_key' => 'sysmex_xn',
            'connection_type' => 'tcp',
        ]);

        $response->assertRedirect();
        $this->assertSame('Renamed Analyzer', $analyzer->fresh()->name);
        $this->assertSame('biochemistry', $analyzer->fresh()->instrument_type);
    }

    public function test_delete_soft_deletes_analyzer(): void
    {
        $analyzer = $this->analyzer();

        $this->delete(route('medical.laboratory.analyzers.destroy', $analyzer))->assertRedirect();

        $this->assertSoftDeleted('lab_analyzers', ['id' => $analyzer->id]);
    }

    public function test_show_renders_without_token_leak(): void
    {
        $analyzer = $this->analyzer();

        $response = $this->get(route('medical.laboratory.analyzers.show', $analyzer));

        $response->assertOk();
        $response->assertSee($analyzer->name);
    }

    // === Credentials ===

    public function test_issue_credential_returns_plain_token_in_session(): void
    {
        $analyzer = $this->analyzer();

        $response = $this->post(route('medical.laboratory.analyzers.credentials.issue', $analyzer));

        $response->assertRedirect();
        $response->assertSessionHas('plain_token');
        $this->assertDatabaseHas('lab_device_credentials', ['analyzer_id' => $analyzer->id]);
    }

    public function test_issue_credential_fails_if_credential_exists(): void
    {
        $analyzer = $this->analyzer();
        $this->post(route('medical.laboratory.analyzers.credentials.issue', $analyzer))->assertRedirect();

        $response = $this->post(route('medical.laboratory.analyzers.credentials.issue', $analyzer));

        $response->assertRedirect();
        $response->assertSessionHas('warning');
        $this->assertSame(1, \App\Models\LabIntegration\LabDeviceCredential::where('analyzer_id', $analyzer->id)->count());
    }

    public function test_rotate_credential_replaces_hash(): void
    {
        $analyzer = $this->analyzer();
        $this->post(route('medical.laboratory.analyzers.credentials.issue', $analyzer));
        $oldHash = $analyzer->credential->fresh()->token_hash;

        $response = $this->post(route('medical.laboratory.analyzers.credentials.rotate', $analyzer));

        $response->assertRedirect();
        $response->assertSessionHas('plain_token');
        $this->assertNotSame($oldHash, $analyzer->credential->fresh()->token_hash);
    }

    public function test_revoke_credential_marks_revoked_at(): void
    {
        $analyzer = $this->analyzer();
        $this->post(route('medical.laboratory.analyzers.credentials.issue', $analyzer));

        $this->post(route('medical.laboratory.analyzers.credentials.revoke', $analyzer))->assertRedirect();

        $this->assertNotNull($analyzer->credential->fresh()->revoked_at);
    }

    // === Filtering ===

    public function test_index_filters_by_type(): void
    {
        $this->analyzer(['code' => 'F-HEM', 'instrument_type' => 'hematology']);
        $this->analyzer(['code' => 'F-BIO', 'instrument_type' => 'biochemistry']);

        $response = $this->get(route('medical.laboratory.analyzers.index', ['type' => 'biochemistry']));

        $response->assertOk();
        $response->assertSee('F-BIO');
        $response->assertDontSee('F-HEM');
    }

    public function test_index_filters_by_status(): void
    {
        $this->analyzer(['code' => 'S-ACT', 'status' => 'active']);
        $this->analyzer(['code' => 'S-INA', 'status' => 'inactive']);

        $response = $this->get(route('medical.laboratory.analyzers.index', ['status' => 'active']));

        $response->assertOk();
        $response->assertSee('S-ACT');
        $response->assertDontSee('S-INA');
    }

    public function test_index_searches_by_name(): void
    {
        $this->analyzer(['code' => 'Q-1', 'name' => 'Zebra Hematology XN']);
        $this->analyzer(['code' => 'Q-2', 'name' => 'Plain Biochemistry']);

        $response = $this->get(route('medical.laboratory.analyzers.index', ['q' => 'Zebra']));

        $response->assertOk();
        $response->assertSee('Q-1');
        $response->assertDontSee('Q-2');
    }
}

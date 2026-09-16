<?php

namespace Tests\Feature;

use App\Models\Institute;
use App\Models\InstituteSetting;
use App\Models\Medical\Medicine;
use App\Models\Membership;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use App\Support\Workspace;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class DgdaToggleTest extends TestCase
{
    use DatabaseTransactions;

    private Institute $institute;
    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $this->institute = Institute::create([
            'name' => 'DGDA Toggle Test Hospital',
            'slug' => 'dgda-toggle-'.uniqid(),
            'industry' => 'healthcare',
            'sub_industry' => 'hospital',
            'country' => 'Bangladesh',
            'status' => 'active',
        ]);

        $this->owner = User::factory()->create([
            'account_type' => 'owner',
            'email_verified_at' => now(),
            'status' => 'active',
        ]);

        $roleId = Role::where('slug', 'institute-owner')->value('id');
        Membership::create([
            'user_id' => $this->owner->id,
            'institution_id' => $this->institute->id,
            'role_id' => $roleId,
            'status' => 'active',
        ]);

        $this->actingAs($this->owner, 'web');
        Workspace::set($this->institute->id);
    }

    private function enableMasterDgda(): void
    {
        Setting::set('medical.dgda.enabled', '1');
    }

    private function disableMasterDgda(): void
    {
        Setting::set('medical.dgda.enabled', '0');
    }

    public function test_toggle_off_shows_general_mode(): void
    {
        $this->disableMasterDgda();
        InstituteSetting::updateOrCreate(
            ['institute_id' => $this->institute->id],
            ['dgda_enabled' => false]
        );

        $this->assertFalse(\App\Support\DgdaContext::isEnabled($this->institute->id));
        $this->assertEquals('general', \App\Support\DgdaContext::mode($this->institute->id));
    }

    public function test_toggle_on_shows_hybrid_mode(): void
    {
        $this->enableMasterDgda();
        InstituteSetting::updateOrCreate(
            ['institute_id' => $this->institute->id],
            ['dgda_enabled' => true]
        );

        $this->assertTrue(\App\Support\DgdaContext::isEnabled($this->institute->id));
        $this->assertEquals('hybrid', \App\Support\DgdaContext::mode($this->institute->id));
    }

    public function test_master_off_disables_tenant_toggle(): void
    {
        $this->disableMasterDgda();
        InstituteSetting::updateOrCreate(
            ['institute_id' => $this->institute->id],
            ['dgda_enabled' => true]
        );

        // Master off overrides tenant on
        $this->assertFalse(\App\Support\DgdaContext::isEnabled($this->institute->id));
        $this->assertTrue(\App\Support\DgdaContext::isMasterEnabled() === false);
    }

    public function test_toggle_on_prompts_migration_if_custom_medicines_exist(): void
    {
        $this->enableMasterDgda();

        // Create custom medicine (no dgda_code)
        Medicine::create([
            'institute_id' => $this->institute->id,
            'brand_name' => 'Napa',
            'generic_name' => 'Paracetamol',
            'strength' => '500mg',
            'dosage_form' => 'Tablet',
            'unit' => 'pcs',
            'code' => 'MED-TEST-001',
            'is_active' => true,
        ]);

        // Toggle ON
        $response = $this->put(route('settings.dgda.update'), [
            'dgda_enabled' => '1',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('migrate_prompt');
        $this->assertEquals(1, session('migrate_prompt.custom_count'));
    }

    public function test_toggle_on_no_prompt_if_no_custom_medicines(): void
    {
        $this->enableMasterDgda();

        // No medicines exist
        $response = $this->put(route('settings.dgda.update'), [
            'dgda_enabled' => '1',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('status', 'DGDA integration enabled.');
        $response->assertSessionMissing('migrate_prompt');
    }

    public function test_toggle_off_preserves_existing_medicines(): void
    {
        $this->enableMasterDgda();
        InstituteSetting::updateOrCreate(
            ['institute_id' => $this->institute->id],
            ['dgda_enabled' => true]
        );

        Medicine::create([
            'institute_id' => $this->institute->id,
            'brand_name' => 'Seclo',
            'generic_name' => 'Omeprazole',
            'strength' => '20mg',
            'dosage_form' => 'Capsule',
            'unit' => 'pcs',
            'code' => 'MED-TEST-002',
            'dgda_code' => 'DGDA-001',
            'is_active' => true,
        ]);

        // Toggle OFF
        $this->put(route('settings.dgda.update'), ['dgda_enabled' => '0']);

        // Medicine still exists
        $this->assertDatabaseHas('medicines', [
            'institute_id' => $this->institute->id,
            'brand_name' => 'Seclo',
        ]);
    }

    public function test_dismiss_migrate_keeps_medicines_unchanged(): void
    {
        $countBefore = Medicine::where('institute_id', $this->institute->id)->count();

        $response = $this->post(route('settings.dgda.dismiss-migrate'));

        $response->assertRedirect();
        $response->assertSessionHas('status', 'Medicines left unchanged.');
        $this->assertEquals($countBefore, Medicine::where('institute_id', $this->institute->id)->count());
    }

    public function test_audit_log_records_toggle_change(): void
    {
        $this->enableMasterDgda();

        $this->put(route('settings.dgda.update'), ['dgda_enabled' => '1']);

        $this->assertDatabaseHas('audit_logs', [
            'institute_id' => $this->institute->id,
            'action' => 'dgda_enabled_changed',
            'module' => 'settings',
        ]);
    }
}

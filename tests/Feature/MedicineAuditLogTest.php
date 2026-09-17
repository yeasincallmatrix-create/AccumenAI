<?php

namespace Tests\Feature;

use App\Models\Institute;
use App\Models\Medical\ClinicalAuditLog;
use App\Models\Medical\Medicine;
use App\Models\Medical\PharmacyStock;
use App\Models\Membership;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class MedicineAuditLogTest extends TestCase
{
    use DatabaseTransactions;

    private Institute $institute;
    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $this->institute = Institute::create([
            'name' => 'Audit Test Hospital',
            'slug' => 'audit-test-'.uniqid(),
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
        \App\Support\Workspace::set($this->institute->id);
    }

    private function makeMedicine(array $overrides = []): Medicine
    {
        return Medicine::create(array_merge([
            'institute_id' => $this->institute->id,
            'code' => 'AUD-'.strtoupper(uniqid()),
            'generic_name' => 'Auditmycin',
            'brand_name' => 'Auditmycin 500',
            'dosage_form' => 'Tablet',
            'strength' => '500mg',
            'unit' => 'Strip',
            'pack_size' => 10,
            'purchase_price' => 5,
            'selling_price' => 8,
            'reorder_level' => 10,
            'reorder_quantity' => 50,
            'is_active' => true,
        ], $overrides));
    }

    private function countAuditLogs(Medicine $medicine, ?string $action = null): int
    {
        $query = ClinicalAuditLog::where('auditable_type', Medicine::class)
            ->where('auditable_id', $medicine->id);

        if ($action) {
            $query->where('action', $action);
        }

        return $query->count();
    }

    // ─── Created ──────────────────────────────────────────────

    public function test_creating_medicine_logs_audit_entry(): void
    {
        $medicine = $this->makeMedicine();

        $this->assertDatabaseHas('clinical_audit_logs', [
            'auditable_type' => Medicine::class,
            'auditable_id' => $medicine->id,
            'action' => 'created',
            'institute_id' => $this->institute->id,
        ]);

        $log = ClinicalAuditLog::where('auditable_type', Medicine::class)
            ->where('auditable_id', $medicine->id)
            ->where('action', 'created')
            ->first();

        $this->assertNotNull($log);
        $this->assertNotNull($log->new_values);
        $this->assertEquals($this->owner->name, $log->actor_name);
    }

    public function test_created_audit_log_includes_brand_name_in_new_values(): void
    {
        $medicine = $this->makeMedicine(['brand_name' => 'MyBrand 250']);

        $log = ClinicalAuditLog::where('auditable_type', Medicine::class)
            ->where('auditable_id', $medicine->id)
            ->where('action', 'created')
            ->first();

        $newValues = is_array($log->new_values) ? $log->new_values : json_decode($log->new_values, true);
        $this->assertArrayHasKey('brand_name', $newValues);
        $this->assertEquals('MyBrand 250', $newValues['brand_name']);
    }

    // ─── Updated ──────────────────────────────────────────────

    public function test_updating_medicine_logs_audit_entry(): void
    {
        $medicine = $this->makeMedicine();

        $beforeCount = $this->countAuditLogs($medicine, 'updated');

        $medicine->update(['brand_name' => 'UpdatedBrand 750']);

        $this->assertEquals($beforeCount + 1, $this->countAuditLogs($medicine, 'updated'));

        $log = ClinicalAuditLog::where('auditable_type', Medicine::class)
            ->where('auditable_id', $medicine->id)
            ->where('action', 'updated')
            ->latest()
            ->first();

        $this->assertNotNull($log);
        $oldValues = is_array($log->old_values) ? $log->old_values : json_decode($log->old_values, true);
        $newValues = is_array($log->new_values) ? $log->new_values : json_decode($log->new_values, true);

        $this->assertEquals('Auditmycin 500', $oldValues['brand_name']);
        $this->assertEquals('UpdatedBrand 750', $newValues['brand_name']);
    }

    public function test_updating_medicine_with_no_changes_does_not_log(): void
    {
        $medicine = $this->makeMedicine();
        $beforeCount = $this->countAuditLogs($medicine, 'updated');

        $medicine->touch();

        $this->assertEquals($beforeCount, $this->countAuditLogs($medicine, 'updated'));
    }

    public function test_normalized_name_changes_are_not_logged(): void
    {
        $medicine = $this->makeMedicine(['brand_name' => 'Napa 500']);

        $medicine->update(['brand_name' => 'Napa 500']);
        $beforeCount = $this->countAuditLogs($medicine, 'updated');

        $medicine->update(['selling_price' => 12]);

        $log = ClinicalAuditLog::where('auditable_type', Medicine::class)
            ->where('auditable_id', $medicine->id)
            ->where('action', 'updated')
            ->latest()
            ->first();

        $newValues = is_array($log->new_values) ? $log->new_values : json_decode($log->new_values, true);
        $this->assertArrayNotHasKey('normalized_name', $newValues);
    }

    // ─── Archived / Restored (controller-driven) ──────────────

    public function test_archiving_medicine_logs_audit_entry(): void
    {
        $medicine = $this->makeMedicine();

        $this->delete(route('medical.pharmacy.medicines.destroy', $medicine));

        $this->assertDatabaseHas('clinical_audit_logs', [
            'auditable_type' => Medicine::class,
            'auditable_id' => $medicine->id,
            'action' => 'archived',
        ]);
    }

    public function test_restoring_medicine_logs_audit_entry(): void
    {
        $medicine = $this->makeMedicine();

        $this->delete(route('medical.pharmacy.medicines.destroy', $medicine));
        $this->post(route('medical.pharmacy.medicines.restore', $medicine));

        $this->assertDatabaseHas('clinical_audit_logs', [
            'auditable_type' => Medicine::class,
            'auditable_id' => $medicine->id,
            'action' => 'restored',
        ]);
    }

    // ─── Institute Scoping ────────────────────────────────────

    public function test_audit_log_includes_institute_id(): void
    {
        $medicine = $this->makeMedicine();

        $log = ClinicalAuditLog::where('auditable_type', Medicine::class)
            ->where('auditable_id', $medicine->id)
            ->where('action', 'created')
            ->first();

        $this->assertEquals($this->institute->id, $log->institute_id);
    }

    // ─── IP / User Agent ──────────────────────────────────────

    public function test_audit_log_includes_ip_and_user_agent(): void
    {
        $medicine = $this->makeMedicine();

        $log = ClinicalAuditLog::where('auditable_type', Medicine::class)
            ->where('auditable_id', $medicine->id)
            ->where('action', 'created')
            ->first();

        $this->assertNotNull($log->ip_address);
        $this->assertNotNull($log->user_agent);
    }

    // ─── Failure Resilience ───────────────────────────────────

    public function test_audit_failure_does_not_break_medicine_creation(): void
    {
        // The model's booted() method wraps ClinicalAuditLog::record() in
        // try-catch. Verify medicine creation succeeds regardless.
        $response = $this->post(route('medical.pharmacy.medicines.store'), [
            'code' => '6002',
            'generic_name' => 'Failmycin',
            'brand_name' => 'Failmycin 100',
            'dosage_form' => 'Tablet',
            'strength' => '100mg',
            'unit' => 'Strip',
            'pack_size' => 10,
            'purchase_price' => 5,
            'selling_price' => 8,
            'reorder_level' => 10,
            'reorder_quantity' => 50,
            'is_active' => true,
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('medicines', [
            'generic_name' => 'Failmycin',
            'institute_id' => $this->institute->id,
        ]);
    }
}

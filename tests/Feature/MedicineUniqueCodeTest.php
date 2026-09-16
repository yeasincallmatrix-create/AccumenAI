<?php

namespace Tests\Feature;

use App\Models\Institute;
use App\Models\Medical\Medicine;
use App\Models\Membership;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class MedicineUniqueCodeTest extends TestCase
{
    use DatabaseTransactions;

    private Institute $institute;
    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $this->institute = Institute::create([
            'name' => 'Unique Code Test Hospital',
            'slug' => 'unique-code-test-'.uniqid(),
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
            'code' => 'UC-'.strtoupper(uniqid()),
            'generic_name' => 'Testmycin',
            'brand_name' => 'Testmycin 500',
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

    // ─── Duplicate Rejection ────────────────────────────────

    public function test_same_code_in_same_institute_is_rejected(): void
    {
        $this->makeMedicine(['code' => 'N001', 'brand_name' => 'Dup Test A', 'strength' => '10mg']);

        $response = $this->post(route('medical.pharmacy.medicines.store'), [
            'code' => 'N001',
            'generic_name' => 'Ibuprofen',
            'brand_name' => 'Dup Test B',
            'dosage_form' => 'Tablet',
            'strength' => '20mg',
            'unit' => 'Strip',
            'pack_size' => 10,
            'purchase_price' => 5,
            'selling_price' => 8,
            'reorder_level' => 10,
            'reorder_quantity' => 50,
        ]);

        $response->assertSessionHasErrors('code');
    }

    public function test_same_code_in_different_institutes_is_allowed(): void
    {
        $otherInstitute = Institute::create([
            'name' => 'Other Hospital',
            'slug' => 'other-hospital-'.uniqid(),
            'industry' => 'healthcare',
            'sub_industry' => 'hospital',
            'country' => 'Bangladesh',
            'status' => 'active',
        ]);

        $this->makeMedicine(['code' => 'N001', 'brand_name' => 'Cross Institute A', 'strength' => '10mg']);

        $medicine2 = Medicine::create([
            'institute_id' => $otherInstitute->id,
            'code' => 'N001',
            'generic_name' => 'Ibuprofen',
            'brand_name' => 'Cross Institute B',
            'dosage_form' => 'Tablet',
            'strength' => '20mg',
            'unit' => 'Strip',
            'pack_size' => 10,
            'purchase_price' => 5,
            'selling_price' => 8,
            'reorder_level' => 10,
            'reorder_quantity' => 50,
            'is_active' => true,
        ]);

        $this->assertNotNull($medicine2);
        $this->assertEquals('N001', $medicine2->code);
    }

    public function test_multiple_null_codes_are_allowed(): void
    {
        $m1 = $this->makeMedicine(['code' => null, 'brand_name' => 'NullCode A', 'strength' => '10mg']);
        $m2 = $this->makeMedicine(['code' => null, 'brand_name' => 'NullCode B', 'strength' => '20mg']);
        $m3 = $this->makeMedicine(['code' => null, 'brand_name' => 'NullCode C', 'strength' => '30mg']);

        $this->assertNotNull($m1);
        $this->assertNotNull($m2);
        $this->assertNotNull($m3);
    }

    // ─── Update Self-Ignore ─────────────────────────────────

    public function test_update_ignores_self(): void
    {
        $medicine = $this->makeMedicine(['code' => 'N001', 'brand_name' => 'Self Ignore A', 'strength' => '10mg']);

        $response = $this->put(route('medical.pharmacy.medicines.update', $medicine), [
            'code' => 'N001',
            'generic_name' => 'Updated Name',
            'brand_name' => 'Self Ignore A',
            'dosage_form' => 'Tablet',
            'strength' => '10mg',
            'unit' => 'Strip',
            'pack_size' => 10,
            'purchase_price' => 5,
            'selling_price' => 8,
            'reorder_level' => 10,
            'reorder_quantity' => 50,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('medicines', ['id' => $medicine->id, 'code' => 'N001']);
    }

    public function test_update_to_existing_code_is_rejected(): void
    {
        $this->makeMedicine(['code' => 'N001', 'brand_name' => 'Brand A', 'strength' => '100mg']);
        $medicineB = $this->makeMedicine(['code' => 'N002', 'brand_name' => 'Brand B', 'strength' => '200mg']);

        $response = $this->put(route('medical.pharmacy.medicines.update', $medicineB), [
            'code' => 'N001',
            'generic_name' => $medicineB->generic_name,
            'brand_name' => $medicineB->brand_name,
            'dosage_form' => 'Tablet',
            'strength' => $medicineB->strength,
            'unit' => 'Strip',
            'pack_size' => 10,
            'purchase_price' => 5,
            'selling_price' => 8,
            'reorder_level' => 10,
            'reorder_quantity' => 50,
        ]);

        $response->assertSessionHasErrors('code');
    }

    // ─── Soft-Deleted Does Not Block ────────────────────────

    public function test_soft_deleted_medicine_does_not_block_same_code(): void
    {
        $medicine = $this->makeMedicine(['code' => 'N001', 'brand_name' => 'SoftDel A', 'strength' => '10mg']);

        // Soft-delete
        $this->delete(route('medical.pharmacy.medicines.destroy', $medicine));
        $this->assertSoftDeleted('medicines', ['id' => $medicine->id]);

        // Create new with same code — should succeed (deleted_at differs)
        $medicine2 = $this->makeMedicine(['code' => 'N001', 'brand_name' => 'SoftDel B', 'strength' => '20mg']);
        $this->assertNotNull($medicine2);
        $this->assertEquals('N001', $medicine2->code);
    }

    // ─── QuickStore (auto-generated code) ───────────────────

    public function test_quick_store_auto_generates_unique_code(): void
    {
        $response = $this->postJson(route('medical.pharmacy.medicines.quick-store'), [
            'dosage_form' => 'Tablet',
            'generic_name' => 'Paracetamol',
            'strength' => '500mg',
            'brand_name' => 'Napa',
        ]);

        $response->assertOk();
        $data = $response->json();
        $this->assertArrayHasKey('id', $data);

        // Verify code was auto-generated (starts with MED-)
        $medicine = Medicine::find($data['id']);
        $this->assertStringStartsWith('MED-', $medicine->code);
    }

    // ─── Database Constraint ────────────────────────────────

    public function test_database_index_exists(): void
    {
        $hasIndex = \Illuminate\Support\Facades\Schema::hasIndex(
            'medicines',
            'uniq_medicine_code_per_institute_deleted'
        );
        $this->assertTrue($hasIndex, 'Unique index uniq_medicine_code_per_institute_deleted should exist');
    }
}

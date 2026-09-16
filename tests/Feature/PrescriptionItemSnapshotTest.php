<?php

namespace Tests\Feature;

use App\Models\Institute;
use App\Models\Medical\Medicine;
use App\Models\Medical\MedicineIdentifier;
use App\Models\Medical\MedicineConcept;
use App\Models\Medical\MedicineForm;
use App\Models\Medical\MedicineProduct;
use App\Models\Medical\MedicineProductIngredient;
use App\Models\Medical\MedicineIngredient;
use App\Models\Medical\Prescription;
use App\Models\Medical\PrescriptionItem;
use App\Models\Membership;
use App\Models\Role;
use App\Models\User;
use App\Services\Medical\PrescriptionItemSnapshotService;
use App\Support\Workspace;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class PrescriptionItemSnapshotTest extends TestCase
{
    use DatabaseTransactions;

    private Institute $institute;
    private User $owner;
    private User $doctor;
    private $instituteId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $this->institute = Institute::create([
            'name' => 'Snapshot Test Hospital',
            'slug' => 'snapshot-test-' . uniqid(),
            'industry' => 'healthcare',
            'sub_industry' => 'hospital',
            'country' => 'Bangladesh',
            'status' => 'active',
        ]);
        $this->instituteId = $this->institute->id;

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

        $this->doctor = User::factory()->create([
            'account_type' => 'staff',
            'email_verified_at' => now(),
            'status' => 'active',
        ]);

        \App\Models\Medical\Doctor::create([
            'institute_id' => $this->institute->id,
            'user_id' => $this->doctor->id,
            'registration_number' => 'REG-' . strtoupper(uniqid()),
        ]);

        $this->actingAs($this->owner, 'web');
        Workspace::set($this->instituteId);
    }

    private function createMedicine(array $overrides = []): Medicine
    {
        return Medicine::create(array_merge([
            'institute_id' => $this->instituteId,
            'generic_name' => 'Paracetamol',
            'brand_name' => 'Napa',
            'dosage_form' => 'Tablet',
            'strength' => '500mg',
            'unit' => 'pcs',
            'pack_size' => 100,
            'category' => 'Analgesic',
            'dgda_code' => 'DG-001',
            'purchase_price' => 5.00,
            'selling_price' => 8.00,
            'vat_percentage' => 0,
            'reorder_level' => 10,
            'reorder_quantity' => 100,
            'requires_prescription' => false,
            'is_controlled' => false,
            'is_active' => true,
            'dgda_status' => 'synced',
        ], $overrides));
    }

    private function createRx(array $overrides = []): Prescription
    {
        return Prescription::create(array_merge([
            'institute_id' => $this->instituteId,
            'patient_id' => \App\Models\Medical\Patient::create([
                'institute_id' => $this->instituteId,
                'first_name' => 'Snap',
                'last_name' => 'Patient',
                'date_of_birth' => '1990-01-15',
                'gender' => 'male',
                'phone' => '01712345679',
                'mr_number' => 'MR' . substr(uniqid(), -8),
            ])->id,
            'doctor_id' => $this->doctor->id,
            'prescription_number' => 'RX-SNAP-' . strtoupper(uniqid()),
            'prescription_date' => now()->format('Y-m-d'),
            'diagnosis' => 'Snapshot Test Dx',
            'is_finalized' => 0,
            'version' => 1,
        ], $overrides));
    }

    // ──────────────────────────────────────────────────────────
    // Tests
    // ──────────────────────────────────────────────────────────

    public function test_creating_prescription_item_snapshots_medicine_data(): void
    {
        $medicine = $this->createMedicine([
            'brand_name' => 'Napa',
            'generic_name' => 'Paracetamol',
            'strength' => '500mg',
            'dosage_form' => 'Tablet',
            'unit' => 'pcs',
            'pack_size' => 100,
            'category' => 'Analgesic',
            'dgda_code' => 'DG-001',
        ]);

        $rx = $this->createRx();

        $item = PrescriptionItemSnapshotService::build($medicine, [
            'prescription_id' => $rx->id,
            'medicine_name' => $medicine->brand_name,
            'dosage' => '500mg',
            'frequency' => '1+0+1',
            'duration_days' => 5,
            'quantity' => 10,
        ]);

        $created = PrescriptionItem::create($item);

        $this->assertEquals($medicine->id, $created->medicine_id);
        $this->assertEquals('Napa', $created->medicine_name);
        $this->assertEquals('Paracetamol', $created->generic_name_snapshot);
        $this->assertEquals('500mg', $created->strength_snapshot);
        $this->assertEquals('Tablet', $created->dosage_form_snapshot);
        $this->assertEquals('pcs', $created->unit_snapshot);
        $this->assertEquals(100, $created->pack_size_snapshot);
        $this->assertEquals('Analgesic', $created->category_snapshot);
        $this->assertEquals('DG-001', $created->dgda_code);
    }

    public function test_deleting_medicine_does_not_break_prescription_display(): void
    {
        $medicine = $this->createMedicine([
            'brand_name' => 'Napa',
            'strength' => '500mg',
            'dosage_form' => 'Tablet',
        ]);

        $rx = $this->createRx();

        $item = PrescriptionItemSnapshotService::build($medicine, [
            'prescription_id' => $rx->id,
            'medicine_name' => $medicine->brand_name,
            'dosage' => '500mg',
            'frequency' => '1+0+1',
            'quantity' => 10,
        ]);
        PrescriptionItem::create($item);

        // Soft-delete the medicine
        $medicine->delete();

        // Reload the prescription item
        $loaded = PrescriptionItem::where('prescription_id', $rx->id)->first();

        // Snapshot fields must still be intact
        $this->assertEquals('Napa', $loaded->medicine_name);
        $this->assertEquals('500mg', $loaded->strength_snapshot);
        $this->assertEquals('Tablet', $loaded->dosage_form_snapshot);

        // The medicine relationship should still resolve (withTrashed)
        $this->assertNotNull($loaded->medicine);
        $this->assertEquals('Napa', $loaded->medicine->brand_name);
    }

    public function test_editing_medicine_does_not_change_existing_prescription(): void
    {
        $medicine = $this->createMedicine([
            'brand_name' => 'Napa',
            'generic_name' => 'Paracetamol',
            'strength' => '500mg',
        ]);

        $rx = $this->createRx();

        $item = PrescriptionItemSnapshotService::build($medicine, [
            'prescription_id' => $rx->id,
            'medicine_name' => $medicine->brand_name,
            'dosage' => '500mg',
            'frequency' => '1+0+1',
            'quantity' => 10,
        ]);
        PrescriptionItem::create($item);

        // Edit the medicine
        $medicine->update([
            'brand_name' => 'New Napa',
            'generic_name' => 'Acetaminophen',
            'strength' => '250mg',
        ]);

        // Reload the prescription item — should still show original values
        $loaded = PrescriptionItem::where('prescription_id', $rx->id)->first();

        $this->assertEquals('Napa', $loaded->medicine_name);
        $this->assertEquals('Paracetamol', $loaded->generic_name_snapshot);
        $this->assertEquals('500mg', $loaded->strength_snapshot);
    }

    public function test_free_text_item_saves_medicine_name(): void
    {
        $rx = $this->createRx();

        $item = PrescriptionItemSnapshotService::build(null, [
            'prescription_id' => $rx->id,
            'medicine_name' => 'Custom Drug XYZ',
            'dosage' => '100mg',
            'frequency' => '1+1+1',
            'quantity' => 30,
        ]);

        $created = PrescriptionItem::create($item);

        $this->assertNull($created->medicine_id);
        $this->assertEquals('Custom Drug XYZ', $created->medicine_name);
    }

    public function test_snapshot_merges_with_user_editable_fields(): void
    {
        $medicine = $this->createMedicine([
            'brand_name' => 'Napa',
            'strength' => '500mg',
        ]);

        $rx = $this->createRx();

        $item = PrescriptionItemSnapshotService::build($medicine, [
            'prescription_id' => $rx->id,
            'medicine_name' => $medicine->brand_name,
            'dosage' => '250mg',
            'frequency' => '1+0+0',
            'duration_days' => 7,
            'quantity' => 21,
            'special_instructions' => 'Take after food',
        ]);

        $created = PrescriptionItem::create($item);

        // Snapshot fields from medicine
        $this->assertEquals('Napa', $created->medicine_name);
        $this->assertEquals('500mg', $created->strength_snapshot);

        // Editable fields from user input
        $this->assertEquals('250mg', $created->dosage);
        $this->assertEquals('1+0+0', $created->frequency);
        $this->assertEquals(7, $created->duration_days);
        $this->assertEquals(21, $created->quantity);
        $this->assertEquals('Take after food', $created->special_instructions);
    }

    public function test_backfill_command_populates_existing_snapshots(): void
    {
        $medicine = $this->createMedicine([
            'brand_name' => 'Napa',
            'generic_name' => 'Paracetamol',
            'strength' => '500mg',
            'dosage_form' => 'Tablet',
            'unit' => 'pcs',
            'pack_size' => 100,
            'category' => 'Analgesic',
        ]);

        $rx = $this->createRx();

        // Create item WITHOUT snapshot values (simulating legacy data)
        $item = PrescriptionItem::create([
            'prescription_id' => $rx->id,
            'medicine_id' => $medicine->id,
            'medicine_name' => 'Napa',
            'dosage' => '500mg',
            'frequency' => '1+0+1',
            'quantity' => 10,
            'status' => 'pending',
            'item_status' => 'active',
            // All snapshot columns intentionally left NULL
        ]);

        $this->assertNull($item->generic_name_snapshot);
        $this->assertNull($item->strength_snapshot);
        $this->assertNull($item->unit_snapshot);

        // Run the backfill command
        $this->artisan('medical:backfill-prescription-snapshots');

        $item->refresh();

        $this->assertEquals('Paracetamol', $item->generic_name_snapshot);
        $this->assertEquals('500mg', $item->strength_snapshot);
        $this->assertEquals('Tablet', $item->dosage_form_snapshot);
        $this->assertEquals('pcs', $item->unit_snapshot);
        $this->assertEquals(100, $item->pack_size_snapshot);
        $this->assertEquals('Analgesic', $item->category_snapshot);
    }

    public function test_medicine_display_name_accessor(): void
    {
        $medicine = $this->createMedicine(['brand_name' => 'Napa', 'generic_name' => 'Paracetamol']);
        $rx = $this->createRx();

        $item = PrescriptionItem::create([
            'prescription_id' => $rx->id,
            'medicine_id' => $medicine->id,
            'medicine_name' => 'Napa',
            'display_name_snapshot' => 'Paracetamol (Napa)',
            'dosage' => '500mg',
            'frequency' => '1+0+1',
            'quantity' => 10,
            'status' => 'pending',
            'item_status' => 'active',
        ]);

        // Falls back to display_name_snapshot
        $this->assertEquals('Paracetamol (Napa)', $item->medicine_display_name);

        // When snapshot is empty, uses medicine_name
        $item2 = PrescriptionItem::create([
            'prescription_id' => $rx->id,
            'medicine_name' => 'Custom Drug',
            'dosage' => '100mg',
            'frequency' => '1+1+1',
            'quantity' => 5,
            'status' => 'pending',
            'item_status' => 'active',
        ]);

        $this->assertEquals('Custom Drug', $item2->medicine_display_name);
    }

    public function test_full_display_accessor(): void
    {
        $rx = $this->createRx();

        $item = PrescriptionItem::create([
            'prescription_id' => $rx->id,
            'medicine_name' => 'Napa',
            'strength_snapshot' => '500mg',
            'dosage_form_snapshot' => 'Tablet',
            'dosage' => '500mg',
            'frequency' => '1+0+1',
            'quantity' => 10,
            'status' => 'pending',
            'item_status' => 'active',
        ]);

        $this->assertEquals('Napa 500mg Tablet', $item->full_display);
    }
}

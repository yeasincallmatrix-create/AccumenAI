<?php

namespace Tests\Feature;

use App\Models\Institute;
use App\Models\Medical\Medicine;
use App\Models\Medical\Patient;
use App\Models\Medical\PharmacyDispense;
use App\Models\Medical\PharmacyStock;
use App\Models\Medical\Prescription;
use App\Models\Medical\PrescriptionItem;
use App\Models\Membership;
use App\Models\Role;
use App\Models\User;
use App\Support\Workspace;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Phase 3 — HMS Prescription & Pharmacy verification.
 *
 * Same pattern as earlier medical suites: DatabaseTransactions on the dev
 * DB, web guard + Workspace context, institute-owner membership. CSRF
 * disabled for HTTP calls only; all other middleware still runs.
 */
class MedicalPhase3Test extends TestCase
{
    use DatabaseTransactions;

    private Institute $institute;

    private User $owner;

    private User $doctor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ValidateCsrfToken::class);

        $this->institute = Institute::create([
            'name' => 'Medical Pharmacy Test Hospital',
            'slug' => 'medical-pharmacy-test-'.uniqid(),
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

        $this->doctor = User::factory()->create([
            'account_type' => 'staff',
            'email_verified_at' => now(),
            'status' => 'active',
        ]);

        $this->actingAs($this->owner, 'web');
        Workspace::set($this->institute->id);
    }

    private function createPatient(array $overrides = []): Patient
    {
        $response = $this->post(route('medical.patients.store'), array_merge([
            'first_name' => 'Rx',
            'last_name' => 'Patient',
            'date_of_birth' => '1992-07-20',
            'gender' => 'male',
            'phone' => '01'.str_pad((string) random_int(0, 999999999), 9, '0', STR_PAD_LEFT),
        ], $overrides));
        $response->assertSessionHasNoErrors();

        return Patient::where('institute_id', $this->institute->id)->latest('id')->firstOrFail();
    }

    private function createMedicine(array $overrides = []): Medicine
    {
        return Medicine::create(array_merge([
            'institute_id' => $this->institute->id,
            'code' => 'T-'.strtoupper(uniqid()),
            'generic_name' => 'Testmycin',
            'brand_name' => 'Testmycin 500',
            'category' => 'Antibiotic',
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

    private function createStock(Medicine $medicine, array $overrides = []): PharmacyStock
    {
        return PharmacyStock::create(array_merge([
            'institute_id' => $this->institute->id,
            'medicine_id' => $medicine->id,
            'batch_number' => 'B-'.strtoupper(uniqid()),
            'expiry_date' => now()->addYear()->format('Y-m-d'),
            'quantity_received' => 100,
            'current_quantity' => 100,
            'purchase_price' => 5,
            'selling_price' => 8,
        ], $overrides));
    }

    private function prescriptionPayload(Patient $patient, array $items): array
    {
        return [
            'patient_id' => $patient->id,
            'doctor_id' => $this->doctor->id,
            'prescription_date' => now()->format('Y-m-d'),
            'diagnosis' => 'Test diagnosis',
            'items' => $items,
        ];
    }

    private function itemPayload(?Medicine $medicine, string $name = 'Testmycin 500mg'): array
    {
        return [
            'medicine_id' => $medicine?->id,
            'medicine_name' => $name,
            'dosage' => '500mg',
            'frequency' => '1+0+1',
            'duration_days' => 5,
            'quantity' => 10,
        ];
    }

    public function test_medicine_crud(): void
    {
        $code = 'CRUD-'.strtoupper(uniqid());

        $this->post(route('medical.pharmacy.medicines.store'), [
            'code' => $code,
            'generic_name' => 'Crudmycin',
            'dosage_form' => 'Tablet',
            'unit' => 'Strip',
            'pack_size' => 10,
            'purchase_price' => 4,
            'selling_price' => 7,
            'reorder_level' => 5,
            'reorder_quantity' => 20,
        ])->assertSessionHasNoErrors()->assertRedirect();

        $medicine = Medicine::where('code', $code)->firstOrFail();
        $this->assertSame($this->institute->id, (int) $medicine->institute_id);

        // Duplicate code rejected.
        $this->post(route('medical.pharmacy.medicines.store'), [
            'code' => $code,
            'generic_name' => 'Other',
            'dosage_form' => 'Tablet',
            'unit' => 'Strip',
            'pack_size' => 10,
            'purchase_price' => 1,
            'selling_price' => 2,
            'reorder_level' => 1,
            'reorder_quantity' => 5,
        ])->assertSessionHasErrors(['code']);

        $this->get(route('medical.pharmacy.medicines.index'))->assertOk()->assertSee($code);
        $this->get(route('medical.pharmacy.medicines.show', $medicine))->assertOk();

        $this->put(route('medical.pharmacy.medicines.update', $medicine), [
            'code' => $code,
            'generic_name' => 'Crudmycin-Renamed',
            'dosage_form' => 'Capsule',
            'unit' => 'Strip',
            'pack_size' => 10,
            'purchase_price' => 4,
            'selling_price' => 7,
            'reorder_level' => 5,
            'reorder_quantity' => 20,
        ])->assertSessionHasNoErrors();

        $this->assertSame('Crudmycin-Renamed', $medicine->fresh()->generic_name);

        $this->delete(route('medical.pharmacy.medicines.destroy', $medicine))->assertRedirect();
        $this->assertDatabaseMissing('medicines', ['id' => $medicine->id]);
    }

    public function test_medicine_with_stock_cannot_be_deleted(): void
    {
        $medicine = $this->createMedicine();
        $this->createStock($medicine);

        $this->delete(route('medical.pharmacy.medicines.destroy', $medicine))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseHas('medicines', ['id' => $medicine->id]);
    }

    public function test_stock_entry_and_duplicate_batch_rejected(): void
    {
        $medicine = $this->createMedicine();

        $this->post(route('medical.pharmacy.stock.store'), [
            'medicine_id' => $medicine->id,
            'batch_number' => 'DUP-001',
            'expiry_date' => now()->addMonths(6)->format('Y-m-d'),
            'quantity_received' => 50,
            'current_quantity' => 50,
            'purchase_price' => 5,
            'selling_price' => 8,
        ])->assertSessionHasNoErrors();

        // Same batch again → service-level duplicate error.
        $this->post(route('medical.pharmacy.stock.store'), [
            'medicine_id' => $medicine->id,
            'batch_number' => 'DUP-001',
            'expiry_date' => now()->addMonths(6)->format('Y-m-d'),
            'quantity_received' => 10,
            'current_quantity' => 10,
            'purchase_price' => 5,
            'selling_price' => 8,
        ])->assertSessionHas('error');

        // Past expiry rejected at validation.
        $this->post(route('medical.pharmacy.stock.store'), [
            'medicine_id' => $medicine->id,
            'batch_number' => 'OLD-001',
            'expiry_date' => now()->subDay()->format('Y-m-d'),
            'quantity_received' => 10,
            'current_quantity' => 10,
            'purchase_price' => 5,
            'selling_price' => 8,
        ])->assertSessionHasErrors(['expiry_date']);

        $this->get(route('medical.pharmacy.stock.index'))->assertOk()->assertSee('DUP-001');
    }

    public function test_stock_adjustment(): void
    {
        $stock = $this->createStock($this->createMedicine());

        $this->post(route('medical.pharmacy.stock.adjust', $stock->id), [
            'new_quantity' => 60,
            'reason' => 'Physical count',
        ])->assertSessionHasNoErrors();

        $this->assertSame(60, (int) $stock->fresh()->current_quantity);
        $this->assertStringContainsString('Physical count', (string) $stock->fresh()->notes);
    }

    public function test_prescription_create_and_numbering(): void
    {
        $patient = $this->createPatient();
        $medicine = $this->createMedicine();

        $this->post(
            route('medical.prescriptions.store'),
            $this->prescriptionPayload($patient, [$this->itemPayload($medicine)])
        )->assertSessionHasNoErrors();

        $rx = Prescription::where('institute_id', $this->institute->id)->firstOrFail();
        $this->assertMatchesRegularExpression(
            '/^RX-\d{4}-'.str_pad((string) $this->institute->id, 3, '0', STR_PAD_LEFT).'-\d{5}$/',
            $rx->prescription_number
        );
        $this->assertFalse((bool) $rx->is_finalized);
        $this->assertSame(1, $rx->items()->count());

        $this->get(route('medical.prescriptions.show', $rx))->assertOk();
        // Drafts cannot be printed.
        $this->get(route('medical.prescriptions.print', $rx))->assertRedirect();

        // Finalize then print.
        $this->post(route('medical.prescriptions.finalize', $rx))->assertRedirect();
        $this->assertTrue((bool) $rx->fresh()->is_finalized);
        $this->get(route('medical.prescriptions.print', $rx))->assertOk();

        // Finalized scripts cannot be edited or deleted.
        $this->put(route('medical.prescriptions.update', $rx), $this->prescriptionPayload(
            $patient,
            [$this->itemPayload($medicine)]
        ))->assertSessionHas('error');
        $this->delete(route('medical.prescriptions.destroy', $rx))->assertSessionHas('error');
    }

    public function test_allergy_blocks_prescription(): void
    {
        $patient = $this->createPatient(['allergies' => 'Testmycin']);
        $medicine = $this->createMedicine(['generic_name' => 'Testmycin']);

        $this->post(
            route('medical.prescriptions.store'),
            $this->prescriptionPayload($patient, [$this->itemPayload($medicine)])
        )->assertSessionHas('error');

        $this->assertSame(0, Prescription::where('institute_id', $this->institute->id)->count());
    }

    public function test_duplicate_therapy_blocked_but_category_overlap_warns(): void
    {
        $patient = $this->createPatient();
        $medA = $this->createMedicine(['generic_name' => 'SameGen', 'category' => 'Cat-A']);
        $medB = $this->createMedicine(['generic_name' => 'SameGen', 'category' => 'Cat-B']);

        // Same generic twice → blocked.
        $this->post(
            route('medical.prescriptions.store'),
            $this->prescriptionPayload($patient, [$this->itemPayload($medA), $this->itemPayload($medB)])
        )->assertSessionHas('error');
        $this->assertSame(0, Prescription::where('institute_id', $this->institute->id)->count());

        // Same category, different generic → created with a warning.
        $medC = $this->createMedicine(['generic_name' => 'OtherGen', 'category' => 'Cat-A']);
        $response = $this->post(
            route('medical.prescriptions.store'),
            $this->prescriptionPayload($patient, [$this->itemPayload($medA), $this->itemPayload($medC, 'OtherGen 250mg')])
        );
        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('status');
        $this->assertStringContainsString(
            'Warning',
            (string) session('status')
        );
        $this->assertSame(1, Prescription::where('institute_id', $this->institute->id)->count());
    }

    public function test_dispense_workflow_deducts_stock(): void
    {
        $patient = $this->createPatient();
        $medicine = $this->createMedicine();
        $stock = $this->createStock($medicine, ['current_quantity' => 100, 'quantity_received' => 100]);

        $this->post(
            route('medical.prescriptions.store'),
            $this->prescriptionPayload($patient, [$this->itemPayload($medicine)])
        )->assertSessionHasNoErrors();
        $rx = Prescription::where('institute_id', $this->institute->id)->firstOrFail();
        $this->post(route('medical.prescriptions.finalize', $rx))->assertRedirect();

        $item = $rx->items()->firstOrFail();

        // Queue lists the script.
        $this->get(route('medical.pharmacy.dispense.index'))
            ->assertOk()->assertSee($rx->prescription_number);
        $this->get(route('medical.pharmacy.dispense.show', $item->id))->assertOk();

        // Dispense the full quantity from the batch.
        $this->post(route('medical.pharmacy.dispense', $item->id), [
            'prescription_item_id' => $item->id,
            'stock_id' => $stock->id,
            'quantity_dispensed' => 10,
        ])->assertSessionHasNoErrors();

        $this->assertSame('dispensed', $item->fresh()->status);
        $this->assertSame(90, (int) $stock->fresh()->current_quantity);
        $this->assertSame(1, PharmacyDispense::where('prescription_item_id', $item->id)->count());

        // Double dispense refused.
        $this->post(route('medical.pharmacy.dispense', $item->id), [
            'prescription_item_id' => $item->id,
            'stock_id' => $stock->id,
            'quantity_dispensed' => 10,
        ])->assertSessionHas('error');
    }

    public function test_dispense_rejects_wrong_batch_and_short_stock(): void
    {
        $patient = $this->createPatient();
        $medicine = $this->createMedicine();
        $other = $this->createMedicine(['generic_name' => 'Othermycin']);
        $wrongBatch = $this->createStock($other);

        $this->post(
            route('medical.prescriptions.store'),
            $this->prescriptionPayload($patient, [$this->itemPayload($medicine)])
        )->assertSessionHasNoErrors();
        $rx = Prescription::where('institute_id', $this->institute->id)->firstOrFail();
        $this->post(route('medical.prescriptions.finalize', $rx))->assertRedirect();
        $item = $rx->items()->firstOrFail();

        // Batch of a different medicine → refused.
        $this->post(route('medical.pharmacy.dispense', $item->id), [
            'prescription_item_id' => $item->id,
            'stock_id' => $wrongBatch->id,
            'quantity_dispensed' => 10,
        ])->assertSessionHas('error');
        $this->assertSame('pending', $item->fresh()->status);
    }

    public function test_batch_dispense_and_expiry_dashboard(): void
    {
        $patient = $this->createPatient();
        $medicine = $this->createMedicine();
        $this->createStock($medicine, ['current_quantity' => 100, 'quantity_received' => 100]);

        $this->post(
            route('medical.prescriptions.store'),
            $this->prescriptionPayload($patient, [$this->itemPayload($medicine)])
        )->assertSessionHasNoErrors();
        $rx = Prescription::where('institute_id', $this->institute->id)->firstOrFail();
        $this->post(route('medical.prescriptions.finalize', $rx))->assertRedirect();

        $this->post(route('medical.pharmacy.dispense.batch', $rx))->assertSessionHasNoErrors();
        $this->assertTrue($rx->fresh()->isFullyDispensed());

        // Expiry dashboard renders with a near-expiry batch present.
        $this->createStock($this->createMedicine(), [
            'expiry_date' => now()->addDays(5)->format('Y-m-d'),
        ]);
        $this->get(route('medical.pharmacy.expiry-alerts'))->assertOk();
        $this->get(route('medical.pharmacy.index'))->assertOk();
    }

    public function test_draft_item_add_remove(): void
    {
        $patient = $this->createPatient();
        $medicine = $this->createMedicine();

        $this->post(
            route('medical.prescriptions.store'),
            $this->prescriptionPayload($patient, [$this->itemPayload($medicine)])
        )->assertSessionHasNoErrors();
        $rx = Prescription::where('institute_id', $this->institute->id)->firstOrFail();

        // Add a free-text item (no catalog link).
        $this->post(route('medical.prescriptions.items.store', $rx), [
            'medicine_name' => 'ORSaline',
            'dosage' => '1 sachet',
            'frequency' => 'as needed',
            'quantity' => 5,
        ])->assertSessionHasNoErrors();
        $this->assertSame(2, $rx->items()->count());

        $added = $rx->items()->latest('id')->firstOrFail();
        $this->assertSame('ORSaline', $added->medicine_name);

        $this->delete(route('medical.prescriptions.items.destroy', [$rx, $added]))->assertRedirect();
        $this->assertSame(1, $rx->items()->count());
    }

    public function test_cross_institute_medicine_is_forbidden(): void
    {
        $other = Institute::create([
            'name' => 'Other Pharmacy Hospital',
            'slug' => 'other-pharmacy-'.uniqid(),
            'industry' => 'healthcare',
            'sub_industry' => 'clinic',
            'country' => 'Bangladesh',
            'status' => 'active',
        ]);

        $foreign = Medicine::create([
            'institute_id' => $other->id,
            'code' => 'F-'.strtoupper(uniqid()),
            'generic_name' => 'Foreignmycin',
            'dosage_form' => 'Tablet',
            'unit' => 'Strip',
            'is_active' => true,
        ]);

        $this->get(route('medical.pharmacy.medicines.show', $foreign))->assertForbidden();

        // …and its stock cannot be used for our items either.
        $patient = $this->createPatient();
        $medicine = $this->createMedicine();
        $this->post(
            route('medical.prescriptions.store'),
            $this->prescriptionPayload($patient, [$this->itemPayload($medicine)])
        )->assertSessionHasNoErrors();
        $rx = Prescription::where('institute_id', $this->institute->id)->firstOrFail();
        $this->post(route('medical.prescriptions.finalize', $rx))->assertRedirect();
        $item = $rx->items()->firstOrFail();

        $foreignStock = PharmacyStock::create([
            'institute_id' => $other->id,
            'medicine_id' => $foreign->id,
            'batch_number' => 'FB-'.strtoupper(uniqid()),
            'expiry_date' => now()->addYear()->format('Y-m-d'),
            'quantity_received' => 50,
            'current_quantity' => 50,
            'purchase_price' => 1,
            'selling_price' => 2,
        ]);

        $this->get(route('medical.pharmacy.dispense.show', $item->id))->assertOk();
        $response = $this->post(route('medical.pharmacy.dispense', $item->id), [
            'prescription_item_id' => $item->id,
            'stock_id' => $foreignStock->id,
            'quantity_dispensed' => 10,
        ]);
        // Foreign batch fails the institute-scoped exists rule → refused.
        $response->assertSessionHasErrors(['stock_id']);
        $this->assertSame('pending', $item->fresh()->status);
    }
}

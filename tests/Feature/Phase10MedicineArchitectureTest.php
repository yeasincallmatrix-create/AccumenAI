<?php

namespace Tests\Feature;

use App\Models\Institute;
use App\Models\Medical\Doctor;
use App\Models\Medical\InstituteMedicine;
use App\Models\Medical\Medicine;
use App\Models\Medical\MedicineConcept;
use App\Models\Medical\MedicineIdentifier;
use App\Models\Medical\MedicineIngredient;
use App\Models\Medical\MedicineProduct;
use App\Models\Medical\Patient;
use App\Models\Medical\Prescription;
use App\Models\Membership;
use App\Models\Role;
use App\Models\User;
use App\Support\Workspace;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Phase 10 — Medicine architecture & terminology foundation.
 *
 * Proves: normalized schema exists with intended constraints; terminology
 * (concepts/ingredients/forms/routes/products/identifiers) behaves;
 * combinations are first-class; tenant catalogs are isolated; prescription
 * snapshots are immutable history; pharmacy keeps working on medicines.id;
 * legacy rows are never renumbered or rewritten; nothing is fabricated.
 */
class Phase10MedicineArchitectureTest extends TestCase
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
            'name' => 'Terminology Test Hospital',
            'slug' => 'terminology-test-'.uniqid(),
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
        Membership::create([
            'user_id' => $this->owner->id,
            'institution_id' => $this->institute->id,
            'role_id' => Role::where('slug', 'institute-owner')->value('id'),
            'status' => 'active',
        ]);

        $this->doctor = User::factory()->create([
            'account_type' => 'staff',
            'email_verified_at' => now(),
            'status' => 'active',
        ]);
        Doctor::create([
            'institute_id' => $this->institute->id,
            'user_id' => $this->doctor->id,
            'registration_number' => 'REG-'.strtoupper(uniqid()),
        ]);

        $this->actingAs($this->owner, 'web');
        Workspace::set($this->institute->id);
    }

    private function makeMedicine(array $overrides = []): Medicine
    {
        return Medicine::create(array_merge([
            'institute_id' => $this->institute->id,
            'code' => 'T-'.strtoupper(uniqid()),
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

    private function createPatient(): Patient
    {
        $this->post(route('medical.patients.store'), [
            'first_name' => 'Terminology',
            'last_name' => 'Probe',
            'date_of_birth' => '1990-05-15',
            'gender' => 'male',
            'phone' => '01'.str_pad((string) random_int(0, 999999999), 9, '0', STR_PAD_LEFT),
            'blood_group' => 'O+',
        ])->assertSessionHasNoErrors();

        return Patient::where('institute_id', $this->institute->id)->latest('id')->firstOrFail();
    }

    // --- Schema ---------------------------------------------------------------

    public function test_terminology_tables_and_snapshot_columns_exist(): void
    {
        foreach ([
            'medicine_concepts', 'medicine_ingredients', 'medicine_forms',
            'medicine_routes', 'medicine_products', 'medicine_product_ingredients',
            'medicine_identifiers', 'institute_medicines',
        ] as $table) {
            $this->assertTrue(Schema::hasTable($table), "missing table {$table}");
        }

        foreach ([
            'medicine_concept_id', 'medicine_product_id', 'display_name_snapshot',
            'strength_snapshot', 'dosage_form_snapshot', 'route_snapshot', 'rxnorm_code_snapshot',
        ] as $column) {
            $this->assertTrue(
                Schema::hasColumn('prescription_items', $column),
                "missing snapshot column {$column}"
            );
        }
        $this->assertTrue(Schema::hasColumn('medicines', 'medicine_product_id'));
    }

    public function test_concept_normalized_name_unique(): void
    {
        MedicineConcept::create(['canonical_name' => 'Paracetamol', 'normalized_name' => 'paracetamol']);

        $this->expectException(\Illuminate\Database\QueryException::class);
        MedicineConcept::create(['canonical_name' => 'PARACETAMOL', 'normalized_name' => 'paracetamol']);
    }

    public function test_identifier_system_scope_unique(): void
    {
        $product = $this->backfilledProduct($this->makeMedicine());
        MedicineIdentifier::create([
            'medicine_product_id' => $product->id,
            'system' => 'rxnorm-test',
            'identifier_type' => 'rxcui-test',
            'value' => 'X-1',
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);
        MedicineIdentifier::create([
            'medicine_product_id' => $product->id,
            'system' => 'rxnorm-test',
            'identifier_type' => 'rxcui-test',
            'value' => 'X-1',
        ]);
    }

    // --- Terminology ---------------------------------------------------------------

    private function backfilledProduct(Medicine $medicine): MedicineProduct
    {
        $this->artisan('medical:backfill-medicine-terminology')->assertSuccessful();

        return $medicine->fresh()->product()->firstOrFail();
    }

    public function test_single_ingredient_product_mapping(): void
    {
        $product = $this->backfilledProduct($this->makeMedicine());

        $this->assertSame('Testmycin', $product->concept->canonical_name);
        $this->assertSame('single', $product->concept->concept_type);
        $this->assertSame('Tablet', $product->form->name);
        $this->assertCount(1, $product->productIngredients);
        $pivot = $product->productIngredients->first();
        $this->assertSame('Testmycin', $pivot->ingredient->canonical_name);
        $this->assertSame(500.0, (float) $pivot->strength_value);
        $this->assertSame('mg', $pivot->strength_unit);

        $catalog = InstituteMedicine::where('institute_id', $this->institute->id)
            ->where('medicine_product_id', $product->id)->firstOrFail();
        $this->assertTrue((bool) $catalog->active);
        $this->assertSame(8.0, (float) $catalog->selling_price);
    }

    public function test_combination_product_mapping(): void
    {
        $medicine = $this->makeMedicine([
            'generic_name' => 'Calcium + Vitamin D3',
            'strength' => '500mg+400IU',
        ]);
        $product = $this->backfilledProduct($medicine);

        $this->assertSame('combination', $product->concept->concept_type);
        $this->assertCount(2, $product->productIngredients()->orderBy('sequence')->get());
        $names = $product->ingredients()->orderBy('sequence')->pluck('canonical_name')->all();
        $this->assertSame(['Calcium', 'Vitamin D3'], array_values($names));
        $units = $product->productIngredients()->orderBy('sequence')->pluck('strength_unit')->all();
        $this->assertSame(['mg', 'IU'], array_values($units));
    }

    public function test_unparseable_strength_preserved_not_guessed(): void
    {
        $medicine = $this->makeMedicine(['generic_name' => 'Oddmycin', 'strength' => 'as directed']);
        $product = $this->backfilledProduct($medicine);

        $this->assertSame('as directed', $product->strength_raw);
        $pivot = $product->productIngredients->first();
        $this->assertNull($pivot->strength_value);
        $this->assertNull($pivot->strength_unit);
    }

    // --- Identifiers --------------------------------------------------------------------

    public function test_no_fabricated_identifiers(): void
    {
        $this->makeMedicine();
        $this->artisan('medical:backfill-medicine-terminology')->assertSuccessful();

        $this->assertSame(
            0,
            MedicineIdentifier::where('system', MedicineIdentifier::SYSTEM_RXNORM)->count()
        );
    }

    public function test_legacy_dgda_values_migrate_as_identifiers(): void
    {
        $medicine = $this->makeMedicine(['dgda_code' => '353-TEST-001', 'dgda_dar_number' => 'DAR-T-1']);
        $product = $this->backfilledProduct($medicine);

        $codes = $product->identifiers()->where('system', 'dgda')->pluck('value', 'identifier_type')->all();
        $this->assertSame('353-TEST-001', $codes['code'] ?? null);
        $this->assertSame('DAR-T-1', $codes['dar_number'] ?? null);
        // Legacy row keeps its own columns untouched (compat).
        $this->assertSame('353-TEST-001', $medicine->fresh()->dgda_code);
    }

    // --- Tenant catalog ----------------------------------------------------------------------

    public function test_catalog_isolation_and_local_codes(): void
    {
        $product = $this->backfilledProduct($this->makeMedicine());

        $other = Institute::create([
            'name' => 'Terminology Rival', 'slug' => 'terminology-rival-'.uniqid(),
            'industry' => 'healthcare', 'sub_industry' => 'clinic',
            'country' => 'Bangladesh', 'status' => 'active',
        ]);

        // Same local code in another institute: allowed at catalog level.
        $row = InstituteMedicine::create([
            'institute_id' => $other->id,
            'medicine_product_id' => $product->id,
            'local_code' => InstituteMedicine::where('institute_id', $this->institute->id)->firstOrFail()->local_code,
            'selling_price' => 999,
            'active' => true,
        ]);
        $this->assertDatabaseHas('institute_medicines', ['id' => $row->id]);

        // …but each tenant sees only its own rows and prices.
        $this->assertSame(1, InstituteMedicine::where('institute_id', $other->id)->count());
        $this->assertSame(999.0, (float) $row->fresh()->selling_price);
        $this->assertSame(
            8.0,
            (float) InstituteMedicine::where('institute_id', $this->institute->id)->firstOrFail()->selling_price
        );

        // Duplicate local code within ONE institute is refused.
        $this->expectException(\Illuminate\Database\QueryException::class);
        InstituteMedicine::create([
            'institute_id' => $other->id,
            'medicine_product_id' => $product->id,
            'local_code' => $row->local_code,
        ]);
    }

    public function test_global_terminology_has_no_tenant_scope(): void
    {
        $this->backfilledProduct($this->makeMedicine());

        // Concepts/ingredients are shared vocabulary: visible without any
        // institute predicate, carrying no prices or local codes.
        $this->assertGreaterThan(0, MedicineConcept::count());
        $this->assertGreaterThan(0, MedicineIngredient::count());
        $this->assertArrayNotHasKey('institute_id', MedicineConcept::first()->toArray());
    }

    // --- Prescription snapshots ------------------------------------------------------------------

    private function createPrescription(Patient $patient, Medicine $medicine): Prescription
    {
        $this->post(route('medical.prescriptions.store'), [
            'patient_id' => $patient->id,
            'doctor_id' => $this->doctor->id,
            'prescription_date' => now()->format('Y-m-d'),
            'diagnosis' => 'Terminology diagnosis',
            'items' => [[
                'medicine_id' => $medicine->id,
                'medicine_name' => $medicine->display_name,
                'dosage' => '500mg',
                'frequency' => '1+0+1',
                'quantity' => 10,
            ]],
        ])->assertSessionHasNoErrors();

        return Prescription::where('institute_id', $this->institute->id)->latest('id')->firstOrFail();
    }

    public function test_snapshot_populated_on_create(): void
    {
        $medicine = $this->makeMedicine();
        $this->artisan('medical:backfill-medicine-terminology')->assertSuccessful();

        $item = $this->createPrescription($this->createPatient(), $medicine)->items()->firstOrFail();

        $this->assertNotNull($item->medicine_concept_id);
        $this->assertNotNull($item->medicine_product_id);
        $this->assertSame($medicine->fresh()->product->display_name, $item->display_name_snapshot);
        $this->assertSame('500mg', $item->strength_snapshot);
        $this->assertSame('Tablet', $item->dosage_form_snapshot);
        // No RxNorm curation exists: null, never fabricated.
        $this->assertNull($item->rxnorm_code_snapshot);
    }

    public function test_snapshot_stable_across_product_remap(): void
    {
        $medicine = $this->makeMedicine();
        $this->artisan('medical:backfill-medicine-terminology')->assertSuccessful();
        $rx = $this->createPrescription($this->createPatient(), $medicine);
        $item = $rx->items()->firstOrFail();
        $originalDisplay = $item->display_name_snapshot;

        // Later catalog/terminology edits (rename + remap) must not rewrite
        // the historical item.
        $product = $medicine->fresh()->product;
        $product->update(['display_name' => 'Renamed Product', 'strength_raw' => '250mg']);
        $this->post(route('medical.prescriptions.finalize', $rx))->assertRedirect();

        $this->assertSame($originalDisplay, $item->fresh()->display_name_snapshot);
        $this->assertSame('500mg', $item->fresh()->strength_snapshot);
    }

    public function test_finalized_protection_intact(): void
    {
        $medicine = $this->makeMedicine();
        $rx = $this->createPrescription($this->createPatient(), $medicine);
        $this->post(route('medical.prescriptions.finalize', $rx))->assertRedirect();

        $this->put(route('medical.prescriptions.update', $rx), [
            'patient_id' => $rx->patient_id,
            'doctor_id' => $this->doctor->id,
            'prescription_date' => now()->format('Y-m-d'),
            'diagnosis' => 'Tampered',
            'items' => [[
                'medicine_id' => $medicine->id,
                'medicine_name' => $medicine->display_name,
                'dosage' => '1mg',
                'frequency' => '1+0+0',
                'quantity' => 1,
            ]],
        ])->assertSessionHas('error');
    }

    // --- Pharmacy compatibility ---------------------------------------------------------------------

    public function test_pharmacy_flow_on_mapped_medicine(): void
    {
        // HTTP creation auto-maps via the controller hook.
        $this->post(route('medical.pharmacy.medicines.store'), [
            'code' => 'P10-'.strtoupper(uniqid()),
            'generic_name' => 'Flowmycin',
            'dosage_form' => 'Capsule',
            'strength' => '250mg',
            'unit' => 'Strip',
            'pack_size' => 10,
            'purchase_price' => 4,
            'selling_price' => 7,
            'reorder_level' => 5,
            'reorder_quantity' => 20,
        ])->assertSessionHasNoErrors();
        $medicine = Medicine::where('institute_id', $this->institute->id)->latest('id')->firstOrFail();
        $this->assertNotNull($medicine->fresh()->medicine_product_id);

        $stock = \App\Models\Medical\PharmacyStock::create([
            'institute_id' => $this->institute->id,
            'medicine_id' => $medicine->id,
            'batch_number' => 'B-'.strtoupper(uniqid()),
            'expiry_date' => now()->addYear()->format('Y-m-d'),
            'quantity_received' => 100,
            'current_quantity' => 100,
            'purchase_price' => 4,
            'selling_price' => 7,
        ]);
        $rx = $this->createPrescription($this->createPatient(), $medicine);
        $this->post(route('medical.prescriptions.finalize', $rx))->assertRedirect();
        $item = $rx->items()->firstOrFail();

        $this->post(route('medical.pharmacy.dispense', $item->id), [
            'prescription_item_id' => $item->id,
            'stock_id' => $stock->id,
            'quantity_dispensed' => 10,
        ])->assertSessionHasNoErrors();

        $this->assertSame('dispensed', $item->fresh()->status);
        $this->assertSame(90, (int) $stock->fresh()->current_quantity);
    }

    // --- Legacy compatibility ----------------------------------------------------------------------------

    public function test_legacy_rows_ids_and_references_intact(): void
    {
        $medicine = $this->makeMedicine();
        $beforeId = $medicine->id;
        $rx = $this->createPrescription($this->createPatient(), $medicine);
        $itemId = $rx->items()->firstOrFail()->id;

        $this->artisan('medical:backfill-medicine-terminology')->assertSuccessful();

        $this->assertSame($beforeId, $medicine->fresh()->id);
        $this->assertSame($beforeId, (int) $rx->items()->firstOrFail()->medicine_id);
        $this->assertDatabaseHas('medicines', ['id' => $beforeId]);
        $this->assertDatabaseHas('prescription_items', ['id' => $itemId]);
    }

    // --- Safety -----------------------------------------------------------------------------------------------------

    public function test_product_with_catalog_entry_cannot_be_deleted(): void
    {
        $product = $this->backfilledProduct($this->makeMedicine());

        $this->expectException(\Illuminate\Database\QueryException::class);
        $product->delete();
    }

    public function test_no_terminology_management_routes(): void
    {
        // No admin UI exists in Phase 10; terminology is curated offline.
        // Catalog mutations keep flowing through the existing medicines
        // controller under the existing medical_medicines.* permissions.
        foreach (['medicine_concepts', 'medicine_products', 'institute_medicines'] as $name) {
            $this->assertFalse(\Illuminate\Support\Facades\Route::has('medical.'.$name.'.store'));
            $this->assertFalse(\Illuminate\Support\Facades\Route::has('medical.'.$name.'.destroy'));
        }
    }
}

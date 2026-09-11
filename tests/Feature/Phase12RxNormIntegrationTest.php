<?php

namespace Tests\Feature;

use App\Models\Institute;
use App\Models\Medical\Doctor;
use App\Models\Medical\InstituteMedicine;
use App\Models\Medical\Medicine;
use App\Models\Medical\MedicineIdentifier;
use App\Models\Medical\MedicineIngredient;
use App\Models\Medical\MedicineProduct;
use App\Models\Medical\Patient;
use App\Models\Medical\Prescription;
use App\Models\Medical\RxNormConcept;
use App\Models\Medical\RxNormImportBatch;
use App\Models\Membership;
use App\Models\Role;
use App\Models\User;
use App\Services\Medical\RxNormClient;
use App\Support\Workspace;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Phase 12 — RxNorm integration & external terminology mapping.
 *
 * No live RxNav calls happen here (or in the importer): every RXCUI below
 * is synthetic test data in the 9xxxxxx range (above any real RXCUI, so a
 * leak could never pass as production mapping), exercised through the
 * file-driven pipeline. Live reachability was verified manually once
 * (RxNav REST version endpoint, 2026-09-10) and is documented, not tested.
 */
class Phase12RxNormIntegrationTest extends TestCase
{
    use DatabaseTransactions;

    private Institute $institute;

    private User $owner;

    private User $doctor;

    /** RXCUIs appearing in fixtures — the only "registry" values used. */
    private array $fixtureRxcuis = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ValidateCsrfToken::class);

        $this->institute = Institute::create([
            'name' => 'RxNorm Test Hospital',
            'slug' => 'rxnorm-test-'.uniqid(),
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

    private function fixtureFile(array $rows, array $headers = null): string
    {
        $headers ??= ['rxcui', 'name', 'tty', 'ingredient_names', 'ingredient_rxcuis', 'strength_value', 'strength_unit', 'dose_form', 'brand_name', 'status'];
        $path = tempnam(sys_get_temp_dir(), 'rxn').'.csv';
        $handle = fopen($path, 'w');
        fputcsv($handle, $headers);
        foreach ($rows as $row) {
            fputcsv($handle, $row);
            if (trim((string) ($row[0] ?? '')) !== '') {
                $this->fixtureRxcuis[] = trim((string) $row[0]);
            }
        }
        fclose($handle);

        return $path;
    }

    private function import(string $path, array $options = []): int
    {
        return Artisan::call('medical:sync-rxnorm', array_merge(['file' => $path], $options));
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

    // --- Source contract ---------------------------------------------------------------

    public function test_source_contract_and_malformed_rows(): void
    {
        $service = app(\App\Services\Medical\RxNormImportService::class);
        $path = $this->fixtureFile([
            ['9000001', 'Paracetamol', 'IN', '', '', '', '', '', '', 'active'],
            ['ZZZ', 'Broken', 'IN', '', '', '', '', '', '', 'active'],
            ['', 'NoId', 'IN', '', '', '', '', '', '', 'active'],
            ['9000002', 'Mystery', 'XX', '', '', '', '', '', '', 'active'],
        ]);

        $parsed = $service->parseFile($path);
        $this->assertCount(4, $parsed['rows']);

        $this->assertTrue($service->validateRow($parsed['rows'][0]['data'], 2)['valid']);
        $this->assertFalse($service->validateRow($parsed['rows'][1]['data'], 3)['valid']);
        $this->assertFalse($service->validateRow($parsed['rows'][2]['data'], 4)['valid']);
        $this->assertFalse($service->validateRow($parsed['rows'][3]['data'], 5)['valid']);

        $this->expectException(\RuntimeException::class);
        $service->parseFile($path.'-missing');
    }

    // --- Identifiers --------------------------------------------------------------------------

    public function test_rxcui_storage_and_uniqueness(): void
    {
        $path = $this->fixtureFile([
            ['9000010', 'Paracetamol', 'IN', '', '', '', '', '', '', 'active'],
        ]);
        $this->assertSame(0, $this->import($path));

        $row = MedicineIdentifier::where('system', 'rxnorm')
            ->where('identifier_type', 'rxcui')
            ->where('value', '9000010')
            ->firstOrFail();
        $this->assertNotNull($row->medicine_ingredient_id);
        $this->assertNull($row->medicine_product_id);

        $this->expectException(\Illuminate\Database\QueryException::class);
        MedicineIdentifier::create([
            'medicine_product_id' => null,
            'medicine_ingredient_id' => $row->medicine_ingredient_id,
            'system' => 'rxnorm',
            'identifier_type' => 'rxcui',
            'value' => '9000010',
        ]);
    }

    public function test_dgda_rxnorm_coexistence(): void
    {
        $medicine = $this->makeMedicine(['dgda_code' => '353-RX-001']);
        $this->artisan('medical:backfill-medicine-terminology')->assertSuccessful();
        $productId = $medicine->fresh()->medicine_product_id;
        $this->assertNotNull($productId);

        // DGDA identifier (legacy migration path) already present…
        $this->assertSame(1, MedicineIdentifier::where('medicine_product_id', $productId)
            ->where('system', 'dgda')->count());

        // …RxNorm attaches beside it; same value string in the other system
        // is a distinct identifier, never a collision or overwrite.
        $path = $this->fixtureFile([
            ['9000020', 'Testmycin 500 MG Oral Tablet', 'SCD', 'Testmycin', '', '500', 'mg', 'Tablet', '', 'active'],
        ]);
        $this->import($path);

        $this->assertSame(1, MedicineIdentifier::where('medicine_product_id', $productId)
            ->where('system', 'rxnorm')->count());
        $this->assertSame(1, MedicineIdentifier::where('medicine_product_id', $productId)
            ->where('system', 'dgda')->count());
    }

    public function test_no_fabricated_rxcuis(): void
    {
        $path = $this->fixtureFile([
            ['9000030', 'Paracetamol', 'IN', '', '', '', '', '', '', 'active'],
            ['9000031', 'Paracetamol 500 MG Oral Tablet', 'SCD', 'Paracetamol', '', '500', 'mg', 'Tablet', '', 'active'],
        ]);
        $this->import($path);

        $values = MedicineIdentifier::where('system', 'rxnorm')->pluck('value')->all();
        $this->assertNotEmpty($values);
        foreach ($values as $value) {
            $this->assertContains($value, $this->fixtureRxcuis, "RXCUI {$value} did not come from the fixture");
        }
        $this->assertSame([], array_diff(
            RxNormConcept::pluck('rxcui')->all(),
            $this->fixtureRxcuis
        ));
    }

    // --- Matching ---------------------------------------------------------------------------------

    public function test_exact_match_reuses_identifier(): void
    {
        $this->import($this->fixtureFile([
            ['9000040', 'Paracetamol', 'IN', '', '', '', '', '', '', 'active'],
        ]));
        $firstProduct = null;

        // Same RXCUI again: reconciled as unchanged, never duplicated.
        $this->import($this->fixtureFile([
            ['9000040', 'Paracetamol', 'IN', '', '', '', '', '', '', 'active'],
        ]));
        $this->assertSame(1, MedicineIdentifier::where('system', 'rxnorm')->where('value', '9000040')->count());
        $this->assertSame(
            1,
            RxNormConcept::where('rxcui', '9000040')->count()
        );
        $this->assertSame('exact', RxNormConcept::where('rxcui', '9000040')->firstOrFail()->match_status);
    }

    public function test_deterministic_product_match(): void
    {
        $this->makeMedicine(); // Testmycin 500mg Tablet (backfilled below)
        $this->artisan('medical:backfill-medicine-terminology')->assertSuccessful();
        $productCount = MedicineProduct::count();

        $path = $this->fixtureFile([
            ['9000050', 'Testmycin 500 MG Oral Tablet', 'SCD', 'Testmycin', '', '500', 'mg', 'Tablet', '', 'active'],
        ]);
        $this->import($path);

        $row = RxNormConcept::where('rxcui', '9000050')->firstOrFail();
        $this->assertSame(RxNormConcept::MATCH_DETERMINISTIC, $row->match_status);
        $this->assertSame($productCount, MedicineProduct::count());
        $this->assertNotNull($row->medicine_product_id);
    }

    public function test_relationship_assisted_match(): void
    {
        // Local ingredient known only under another name; the source-declared
        // ingredient RXCUI bridges deterministically.
        $this->import($this->fixtureFile([
            ['9000060', 'Paracetamol', 'IN', '', '', '', '', '', '', 'active'],
        ]));
        $this->makeMedicine([
            'generic_name' => 'Acetaminophen', 'brand_name' => 'Acetaminophen 500',
        ]);
        $this->artisan('medical:backfill-medicine-terminology')->assertSuccessful();

        $path = $this->fixtureFile([
            ['9000061', 'Acetaminophen 500 MG Oral Tablet', 'SCD', 'Acetaminophen', '9000060', '500', 'mg', 'Tablet', '', 'active'],
        ]);
        $this->import($path);

        // Names differ, but the declared RXCUI resolves to the local
        // Paracetamol ingredient… which is NOT in the Acetaminophen product,
        // so agreement must fail rather than force a merge.
        $row = RxNormConcept::where('rxcui', '9000061')->firstOrFail();
        $this->assertContains($row->match_status, [
            RxNormConcept::MATCH_UNMAPPED, RxNormConcept::MATCH_AMBIGUOUS,
        ]);
        $this->assertNull($row->medicine_product_id);
    }

    public function test_relationship_match_succeeds_when_composition_agrees(): void
    {
        $this->import($this->fixtureFile([
            ['9000065', 'Paracetamol', 'IN', '', '', '', '', '', '', 'active'],
        ]));
        // Local product whose ingredient IS Paracetamol.
        $medicine = $this->makeMedicine(['generic_name' => 'Paracetamol', 'brand_name' => 'Para 500']);
        $this->artisan('medical:backfill-medicine-terminology')->assertSuccessful();

        $path = $this->fixtureFile([
            ['9000066', 'Para 500 MG Oral Tablet', 'SCD', 'Acetaminophen', '9000065', '500', 'mg', 'Tablet', '', 'active'],
        ]);
        $this->import($path);

        $row = RxNormConcept::where('rxcui', '9000066')->firstOrFail();
        $this->assertSame(RxNormConcept::MATCH_RELATIONSHIP, $row->match_status);
        $this->assertSame((int) $medicine->fresh()->medicine_product_id, (int) $row->medicine_product_id);
    }

    public function test_ambiguous_brand_never_merges(): void
    {
        foreach (['Tablet', 'Capsule'] as $form) {
            $this->makeMedicine([
                'code' => 'T-'.strtoupper(uniqid()),
                'generic_name' => 'Ambigmycin',
                'brand_name' => 'Ambigmycin',
                'dosage_form' => $form,
                'strength' => '200mg',
            ]);
        }
        $this->artisan('medical:backfill-medicine-terminology')->assertSuccessful();
        $productCount = MedicineProduct::count();

        $path = $this->fixtureFile([
            ['9000070', 'Ambigmycin', 'BN', '', '', '', '', '', '', 'active'],
        ]);
        $this->import($path);

        $row = RxNormConcept::where('rxcui', '9000070')->firstOrFail();
        $this->assertSame(RxNormConcept::MATCH_AMBIGUOUS, $row->match_status);
        $this->assertNull($row->medicine_product_id);
        $this->assertCount(2, $row->candidates);
        $this->assertSame($productCount, MedicineProduct::count());
    }

    public function test_unmapped_pack_concept(): void
    {
        $path = $this->fixtureFile([
            ['9000071', 'Some Pack', 'GPCK', '', '', '', '', '', '', 'active'],
        ]);
        $this->import($path);

        $row = RxNormConcept::where('rxcui', '9000071')->firstOrFail();
        $this->assertSame(RxNormConcept::MATCH_UNMAPPED, $row->match_status);
        $this->assertNull($row->medicine_product_id);
    }

    public function test_fuzzy_brand_variant_never_merges(): void
    {
        $this->makeMedicine(); // Testmycin 500mg Tablet, brand Testmycin 500
        $this->artisan('medical:backfill-medicine-terminology')->assertSuccessful();
        $productCount = MedicineProduct::count();

        // Generic (SCD) concepts are brand-agnostic by definition: a
        // one-letter brand spelling difference still links the SHARED
        // generic product — correctly, without merging anything.
        $path = $this->fixtureFile([
            ['9000072', 'Testmycim 500 MG Oral Tablet', 'SCD', 'Testmycin', '', '500', 'mg', 'Tablet', 'Testmycim', 'active'],
        ]);
        $this->import($path);

        $row = RxNormConcept::where('rxcui', '9000072')->firstOrFail();
        $this->assertSame($productCount, MedicineProduct::count());

        // Branded (SBD) concepts DO require brand agreement: a mismatched
        // brand must not link, even with identical ingredients/strength/form.
        $path2 = $this->fixtureFile([
            ['9000073', 'Testmycim 500 MG Oral Tablet [Testmycim]', 'SBD', 'Testmycin', '', '500', 'mg', 'Tablet', 'Testmycim', 'active'],
        ]);
        $this->import($path2);

        $row2 = RxNormConcept::where('rxcui', '9000073')->firstOrFail();
        $this->assertNull($row2->medicine_product_id);
        $this->assertSame($productCount, MedicineProduct::count());
    }

    // --- Combinations ------------------------------------------------------------------------------------

    public function test_combination_ingredient_agreement(): void
    {
        $this->makeMedicine([
            'generic_name' => 'Calcium + Vitamin D3',
            'strength' => '500mg+400IU',
        ]);
        $this->artisan('medical:backfill-medicine-terminology')->assertSuccessful();

        $path = $this->fixtureFile([
            ['9000080', 'Calcium 500 MG / Vitamin D3 400 UT Oral Tablet', 'SCD', 'Calcium|Vitamin D3', '', '', '', 'Tablet', '', 'active'],
        ]);
        $this->import($path);

        $row = RxNormConcept::where('rxcui', '9000080')->firstOrFail();
        $this->assertSame(RxNormConcept::MATCH_DETERMINISTIC, $row->match_status);
        $this->assertCount(
            2,
            MedicineProduct::findOrFail($row->medicine_product_id)->productIngredients
        );
    }

    // --- Release & retirement ----------------------------------------------------------------------------------

    public function test_release_tracked_and_update_safe(): void
    {
        $path = $this->fixtureFile([
            ['9000090', 'Paracetamol', 'IN', '', '', '', '', '', '', 'active'],
        ]);
        $this->import($path, ['--release' => '2026-09']);

        $row = RxNormConcept::where('rxcui', '9000090')->firstOrFail();
        $this->assertSame('2026-09', $row->source_release);
        $this->assertSame('2026-09', $row->batch->release_version);

        // Later release, renamed display: updated, same identity row.
        $path2 = $this->fixtureFile([
            ['9000090', 'Paracetamol (updated)', 'IN', '', '', '', '', '', '', 'active'],
        ]);
        $this->import($path2, ['--release' => '2026-10']);
        $this->assertSame(1, RxNormConcept::where('rxcui', '9000090')->count());
        $this->assertSame('2026-10', RxNormConcept::where('rxcui', '9000090')->firstOrFail()->source_release);
    }

    public function test_retired_concept_preserved(): void
    {
        $path = $this->fixtureFile([
            ['9000091', 'Oldmycin', 'IN', '', '', '', '', '', '', 'active'],
        ]);
        $this->import($path);
        $ingredientId = RxNormConcept::where('rxcui', '9000091')->firstOrFail()->medicine_ingredient_id;
        $this->assertNotNull($ingredientId);

        $path2 = $this->fixtureFile([
            ['9000091', 'Oldmycin', 'IN', '', '', '', '', '', '', 'retired'],
        ]);
        $this->import($path2, ['--release' => '2026-10']);

        $row = RxNormConcept::where('rxcui', '9000091')->firstOrFail();
        $this->assertSame('retired', $row->status);
        // Historical link intact; identifier row retired, never deleted.
        $this->assertSame((int) $ingredientId, (int) $row->medicine_ingredient_id);
        $identifier = MedicineIdentifier::where('system', 'rxnorm')->where('value', '9000091')->firstOrFail();
        $this->assertSame('retired', $identifier->status);
    }

    // --- Tenant & catalog --------------------------------------------------------------------------------------------

    public function test_exact_match_prefers_existing_identifier(): void
    {
        $this->makeMedicine();
        $this->artisan('medical:backfill-medicine-terminology')->assertSuccessful();
        $product = MedicineProduct::latest('id')->firstOrFail();

        // Pre-seeded authoritative identifier (e.g. from an earlier release).
        MedicineIdentifier::create([
            'medicine_product_id' => $product->id,
            'system' => 'rxnorm',
            'identifier_type' => 'rxcui',
            'value' => '9000045',
        ]);

        $path = $this->fixtureFile([
            ['9000045', 'Whatever Name', 'SCD', 'Unrelated', '', '', '', '', '', 'active'],
        ]);
        $this->import($path);

        // Exact identifier wins over differing names: linked, authoritative.
        $row = RxNormConcept::where('rxcui', '9000045')->firstOrFail();
        $this->assertSame(RxNormConcept::MATCH_EXACT, $row->match_status);
        $this->assertSame((int) $product->id, (int) $row->medicine_product_id);
        $this->assertSame(1, MedicineIdentifier::where('system', 'rxnorm')->where('value', '9000045')->count());
    }

    public function test_no_tenant_writes_or_leakage(): void
    {
        $catalogBefore = InstituteMedicine::count();
        $priceBefore = (float) InstituteMedicine::sum('selling_price');

        $path = $this->fixtureFile([
            ['9000100', 'Paracetamol', 'IN', '', '', '', '', '', '', 'active'],
        ]);
        $this->import($path);

        $this->assertFalse(Schema::hasColumn('rxnorm_concepts', 'institute_id'));
        $this->assertFalse(Schema::hasColumn('rxnorm_import_batches', 'institute_id'));
        $this->assertSame($catalogBefore, InstituteMedicine::count());
        $this->assertSame($priceBefore, (float) InstituteMedicine::sum('selling_price'));
    }

    // --- Prescription snapshots ---------------------------------------------------------------------------------------------

    public function test_new_items_snapshot_current_mapping_old_items_untouched(): void
    {
        $medicine = $this->makeMedicine();
        $patient = $this->createPatient();

        // Prescription BEFORE any RxNorm mapping: snapshot stays NULL forever.
        $rx1 = $this->createPrescription($patient, $medicine);
        $oldItem = $rx1->items()->firstOrFail();
        $this->assertNull($oldItem->rxnorm_code_snapshot);

        // Map the product, then prescribe again: new item snapshots it.
        $this->artisan('medical:backfill-medicine-terminology')->assertSuccessful();
        $path = $this->fixtureFile([
            ['9000110', 'Testmycin 500 MG Oral Tablet', 'SCD', 'Testmycin', '', '500', 'mg', 'Tablet', '', 'active'],
        ]);
        $this->import($path);

        $rx2 = $this->createPrescription($patient, $medicine);
        $newItem = $rx2->items()->firstOrFail();
        $this->assertSame('9000110', $newItem->rxnorm_code_snapshot);
        $this->assertNull($oldItem->fresh()->rxnorm_code_snapshot);
    }

    private function createPatient(): Patient
    {
        $this->post(route('medical.patients.store'), [
            'first_name' => 'RxNorm',
            'last_name' => 'Probe',
            'date_of_birth' => '1990-05-15',
            'gender' => 'male',
            'phone' => '01'.str_pad((string) random_int(0, 999999999), 9, '0', STR_PAD_LEFT),
            'blood_group' => 'O+',
        ])->assertSessionHasNoErrors();

        return Patient::where('institute_id', $this->institute->id)->latest('id')->firstOrFail();
    }

    private function createPrescription(Patient $patient, Medicine $medicine): Prescription
    {
        $this->post(route('medical.prescriptions.store'), [
            'patient_id' => $patient->id,
            'doctor_id' => $this->doctor->id,
            'prescription_date' => now()->format('Y-m-d'),
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

    // --- Dry run / safety / authorization --------------------------------------------------------------------------------------

    public function test_dry_run_persists_nothing(): void
    {
        $before = [
            RxNormConcept::count(), MedicineProduct::count(), MedicineIdentifier::count(),
            RxNormImportBatch::count(), InstituteMedicine::count(),
            \App\Models\Medical\PrescriptionItem::count(),
        ];
        $path = $this->fixtureFile([
            ['9000120', 'Paracetamol', 'IN', '', '', '', '', '', '', 'active'],
        ]);
        $this->assertSame(0, $this->import($path, ['--dry-run' => true]));

        $this->assertSame($before[0], RxNormConcept::count());
        $this->assertSame($before[1], MedicineProduct::count());
        $this->assertSame($before[2], MedicineIdentifier::count());
        $this->assertSame($before[3], RxNormImportBatch::count());
        $this->assertSame($before[4], InstituteMedicine::count());
        $this->assertSame($before[5], \App\Models\Medical\PrescriptionItem::count());
    }

    public function test_failed_file_creates_nothing(): void
    {
        $this->assertSame(1, Artisan::call('medical:sync-rxnorm', ['file' => '/nonexistent/file.csv']));
        $this->assertSame(0, RxNormImportBatch::count());
    }

    public function test_no_regulatory_admin_routes(): void
    {
        foreach (['rxnorm.concepts', 'rxnorm.batches', 'rxnorm.import'] as $name) {
            $this->assertFalse(\Illuminate\Support\Facades\Route::has('medical.'.$name.'.store'));
            $this->assertFalse(\Illuminate\Support\Facades\Route::has('medical.'.$name.'.destroy'));
        }
    }

    public function test_duplicate_rxcui_rejected_at_db(): void
    {
        $path = $this->fixtureFile([
            ['9000130', 'Paracetamol', 'IN', '', '', '', '', '', '', 'active'],
        ]);
        $this->import($path);
        $concept = RxNormConcept::where('rxcui', '9000130')->firstOrFail();

        $this->expectException(\Illuminate\Database\QueryException::class);
        RxNormConcept::create([
            'rxcui' => '9000130',
            'name' => 'Duplicate',
            'tty' => 'IN',
            'match_status' => 'unmapped',
        ]);
    }

    public function test_client_soft_fail_and_shapes(): void
    {
        Http::fake([
            'rxnav.nlm.nih.gov/REST/version.json' => Http::response(['version' => '2026-09', 'apiVersion' => '3.1'], 200),
            'rxnav.nlm.nih.gov/REST/rxcui.json*' => Http::response(['idGroup' => ['rxnormId' => ['12345']]], 200),
            'rxnav.nlm.nih.gov/REST/rxcui/12345/allProperties.json*' => Http::response([
                'propConceptGroup' => ['propConcept' => [['propName' => 'SCD', 'propValue' => 'Test Drug']]],
            ], 200),
            'rxnav.nlm.nih.gov/REST/rxcui/99999/historystatus.json' => Http::response([
                'rxcuiStatusHistory' => ['status' => 'Retired'],
            ], 200),
        ]);

        $client = new RxNormClient();
        $this->assertSame('2026-09', $client->version()['version']);

        $found = $client->findByString('Test Drug');
        $this->assertSame('12345', $found['rxcui']);
        $this->assertSame('SCD', $found['tty']);

        $hist = $client->historyStatus('99999');
        $this->assertSame('Retired', $hist['status']);

        $dead = new RxNormClient('https://unroutable.invalid');
        $this->assertFalse($dead->version()['ok']);
    }

    public function test_schema_tables_exist(): void
    {
        $this->assertTrue(Schema::hasTable('rxnorm_concepts'));
        $this->assertTrue(Schema::hasTable('rxnorm_import_batches'));
        foreach (['rxcui', 'name', 'tty', 'source_release', 'status', 'medicine_product_id', 'medicine_ingredient_id', 'match_status', 'candidates', 'raw_payload'] as $column) {
            $this->assertTrue(Schema::hasColumn('rxnorm_concepts', $column), $column);
        }
        $this->assertTrue(Schema::hasColumn('medicine_identifiers', 'medicine_ingredient_id'));
    }
}

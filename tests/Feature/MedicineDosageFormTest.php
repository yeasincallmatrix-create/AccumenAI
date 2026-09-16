<?php

namespace Tests\Feature;

use App\Models\Institute;
use App\Models\Medical\Medicine;
use App\Models\Membership;
use App\Models\Role;
use App\Models\User;
use App\Services\Medical\MedicineDuplicateService;
use App\Support\MedicineDosageForm;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class MedicineDosageFormTest extends TestCase
{
    use DatabaseTransactions;

    private Institute $institute;
    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $this->institute = Institute::create([
            'name' => 'Dosage Form Test Hospital',
            'slug' => 'dosage-form-test-'.uniqid(),
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

    // ─── Dosage Form Validation ──────────────────────────────

    public function test_dosage_form_must_be_from_the_canonical_list(): void
    {
        $dosageForms = implode(',', MedicineDosageForm::all());

        $data = [
            'code' => 'MED-TEST-INVALID',
            'generic_name' => 'Test Med',
            'brand_name' => 'TestBrand',
            'dosage_form' => 'tablet-something',
            'unit' => 'pcs',
            'pack_size' => 1,
            'purchase_price' => 0,
            'selling_price' => 0,
            'reorder_level' => 0,
            'reorder_quantity' => 0,
        ];

        $validator = \Validator::make($data, [
            'dosage_form' => "nullable|string|in:{$dosageForms}",
        ]);

        $this->assertTrue($validator->fails(), 'Invalid dosage form should fail validation');
        $this->assertArrayHasKey('dosage_form', $validator->errors()->toArray());
    }

    public function test_all_17_dosage_forms_are_accepted(): void
    {
        $forms = MedicineDosageForm::all();
        $this->assertCount(17, $forms);

        foreach ($forms as $form) {
            $medicine = Medicine::create([
                'institute_id' => $this->institute->id,
                'code' => 'MED-TEST-'.strtoupper(uniqid()),
                'generic_name' => 'Test Med '.$form,
                'brand_name' => 'Brand '.$form,
                'dosage_form' => $form,
                'unit' => 'pcs',
                'pack_size' => 1,
                'purchase_price' => 0,
                'selling_price' => 0,
                'reorder_level' => 0,
                'reorder_quantity' => 0,
            ]);

            $this->assertEquals($form, $medicine->dosage_form);
        }
    }

    public function test_medicine_dosage_form_helper_validates_correctly(): void
    {
        $this->assertTrue(MedicineDosageForm::isValid('Tablet'));
        $this->assertTrue(MedicineDosageForm::isValid('Capsule'));
        $this->assertFalse(MedicineDosageForm::isValid('tablet'));
        $this->assertFalse(MedicineDosageForm::isValid(''));
        $this->assertFalse(MedicineDosageForm::isValid(null));
        $this->assertFalse(MedicineDosageForm::isValid('InvalidForm'));
    }

    public function test_dosage_form_config_returns_17_options(): void
    {
        $forms = config('medicine.dosage_forms');
        $this->assertCount(17, $forms);
        $this->assertArrayHasKey('Tablet', $forms);
        $this->assertArrayHasKey('Solution', $forms);
        $this->assertArrayHasKey('Patch', $forms);
    }

    // ─── Normalized Name / Case-Insensitive ───────────────────

    public function test_normalized_name_is_set_on_save(): void
    {
        $medicine = Medicine::create([
            'institute_id' => $this->institute->id,
            'code' => 'MED-TEST-NORM1',
            'generic_name' => 'Paracetamol',
            'brand_name' => 'Napa',
            'strength' => '500mg',
            'dosage_form' => 'Tablet',
            'unit' => 'pcs',
            'pack_size' => 1,
            'purchase_price' => 0,
            'selling_price' => 0,
            'reorder_level' => 0,
            'reorder_quantity' => 0,
        ]);

        $this->assertEquals('napa500mg', $medicine->normalized_name);
    }

    public function test_normalized_name_handles_case_variations(): void
    {
        $tests = [
            ['Napa', '500mg', 'napa500mg'],
            ['napa', '250mg', 'napa250mg'],
            ['NAPA', '100 MG', 'napa100mg'],
            ['Napa ', '600mg', 'napa600mg'],
            ['My-Med', '10mg', 'mymed10mg'],
        ];

        foreach ($tests as [$brand, $strength, $expected]) {
            $medicine = Medicine::create([
                'institute_id' => $this->institute->id,
                'code' => 'MED-TEST-'.strtoupper(uniqid()),
                'generic_name' => 'Test',
                'brand_name' => $brand,
                'strength' => $strength,
                'dosage_form' => 'Tablet',
                'unit' => 'pcs',
                'pack_size' => 1,
                'purchase_price' => 0,
                'selling_price' => 0,
                'reorder_level' => 0,
                'reorder_quantity' => 0,
            ]);

            $this->assertEquals($expected, $medicine->normalized_name, "Failed for brand='{$brand}' strength='{$strength}'");
        }
    }

    public function test_medicine_name_is_case_insensitive_for_duplicates(): void
    {
        Medicine::create([
            'institute_id' => $this->institute->id,
            'code' => 'MED-TEST-DUP1',
            'generic_name' => 'Paracetamol',
            'brand_name' => 'Napa',
            'strength' => '500mg',
            'dosage_form' => 'Tablet',
            'unit' => 'pcs',
            'pack_size' => 1,
            'purchase_price' => 0,
            'selling_price' => 0,
            'reorder_level' => 0,
            'reorder_quantity' => 0,
        ]);

        $dupService = app(MedicineDuplicateService::class);
        $duplicates = $dupService->findDuplicates($this->institute->id, 'napa', '500mg');

        $this->assertCount(1, $duplicates);
        $this->assertEquals('Napa', $duplicates->first()->brand_name);
    }

    public function test_duplicate_service_finds_exact_match(): void
    {
        Medicine::create([
            'institute_id' => $this->institute->id,
            'code' => 'MED-TEST-DUP2',
            'generic_name' => 'Amoxicillin',
            'brand_name' => 'Amoxil',
            'strength' => '250mg',
            'dosage_form' => 'Capsule',
            'unit' => 'pcs',
            'pack_size' => 1,
            'purchase_price' => 0,
            'selling_price' => 0,
            'reorder_level' => 0,
            'reorder_quantity' => 0,
        ]);

        $dupService = app(MedicineDuplicateService::class);

        // Same name, same strength
        $this->assertTrue($dupService->exists($this->institute->id, 'Amoxil', '250mg'));
        // Same name, different case
        $this->assertTrue($dupService->exists($this->institute->id, 'amoxil', '250MG'));
        // Different name
        $this->assertFalse($dupService->exists($this->institute->id, 'Amoxil', '500mg'));
        // Different name entirely
        $this->assertFalse($dupService->exists($this->institute->id, 'Panadol', '250mg'));
    }

    public function test_duplicate_service_excludes_specified_id(): void
    {
        $medicine = Medicine::create([
            'institute_id' => $this->institute->id,
            'code' => 'MED-TEST-DUP3',
            'generic_name' => 'Ibuprofen',
            'brand_name' => 'Brufen',
            'strength' => '400mg',
            'dosage_form' => 'Tablet',
            'unit' => 'pcs',
            'pack_size' => 1,
            'purchase_price' => 0,
            'selling_price' => 0,
            'reorder_level' => 0,
            'reorder_quantity' => 0,
        ]);

        $dupService = app(MedicineDuplicateService::class);

        // Without exclude: found
        $this->assertTrue($dupService->exists($this->institute->id, 'Brufen', '400mg'));

        // With exclude: not found (excludes itself)
        $this->assertFalse($dupService->exists($this->institute->id, 'Brufen', '400mg', $medicine->id));
    }

    public function test_search_finds_medicine_case_insensitively(): void
    {
        Medicine::create([
            'institute_id' => $this->institute->id,
            'code' => 'MED-TEST-SRCH1',
            'generic_name' => 'Paracetamol',
            'brand_name' => 'Napa',
            'strength' => '500mg',
            'dosage_form' => 'Tablet',
            'unit' => 'pcs',
            'pack_size' => 1,
            'purchase_price' => 0,
            'selling_price' => 0,
            'reorder_level' => 0,
            'reorder_quantity' => 0,
        ]);

        // Case-insensitive brand search
        $results = Medicine::where('institute_id', $this->institute->id)
            ->search('napa')
            ->get();
        $this->assertCount(1, $results);

        $results = Medicine::where('institute_id', $this->institute->id)
            ->search('NAPA')
            ->get();
        $this->assertCount(1, $results);

        // Generic name search still works
        $results = Medicine::where('institute_id', $this->institute->id)
            ->search('Paracetamol')
            ->get();
        $this->assertCount(1, $results);
    }

    // ─── Tenant Isolation ─────────────────────────────────────

    public function test_duplicate_check_is_scoped_to_institute(): void
    {
        $otherInstitute = Institute::create([
            'name' => 'Other Hospital',
            'slug' => 'other-hospital-'.uniqid(),
            'industry' => 'healthcare',
            'sub_industry' => 'hospital',
            'country' => 'Bangladesh',
            'status' => 'active',
        ]);

        Medicine::create([
            'institute_id' => $this->institute->id,
            'code' => 'MED-TEST-ISO1',
            'generic_name' => 'Test',
            'brand_name' => 'UniqueBrand',
            'strength' => '100mg',
            'dosage_form' => 'Tablet',
            'unit' => 'pcs',
            'pack_size' => 1,
            'purchase_price' => 0,
            'selling_price' => 0,
            'reorder_level' => 0,
            'reorder_quantity' => 0,
        ]);

        $dupService = app(MedicineDuplicateService::class);

        // Same institute: found
        $this->assertTrue($dupService->exists($this->institute->id, 'UniqueBrand', '100mg'));

        // Different institute: not found
        $this->assertFalse($dupService->exists($otherInstitute->id, 'UniqueBrand', '100mg'));
    }
}

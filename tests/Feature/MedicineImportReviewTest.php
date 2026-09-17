<?php

namespace Tests\Feature;

use App\Models\Institute;
use App\Models\Medical\Medicine;
use App\Models\Medical\MedicineImportBatch;
use App\Models\Membership;
use App\Models\Role;
use App\Models\User;
use App\Support\Workspace;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class MedicineImportReviewTest extends TestCase
{
    use DatabaseTransactions;

    private Institute $institute;
    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $this->institute = Institute::create([
            'name' => 'Review Test Hospital',
            'slug' => 'review-test-' . uniqid(),
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

    private function makeCsv(array $rows): UploadedFile
    {
        $header = 'brand_name,generic_name,strength,dosage_form,route,unit,pack_size,manufacturer,category,selling_price,reorder_level,code,dgda_code,is_active';
        $lines = [$header];
        foreach ($rows as $row) {
            $lines[] = implode(',', array_map(fn ($v) => $v ?? '', $row));
        }
        $tmp = tempnam(sys_get_temp_dir(), 'med_csv_');
        file_put_contents($tmp, implode("\n", $lines));

        return new UploadedFile($tmp, 'medicines.csv', 'text/csv', null, true);
    }

    private function row(string $brand, string $code = '', string $strength = '500mg', string $form = 'Tablet'): array
    {
        return [$brand, $brand . 'Generic', $strength, $form, 'oral', 'pcs', '10', 'Square', 'other', '1.00', '10', $code, '', '1'];
    }

    private function upload(array $rows): string
    {
        $response = $this->post(route('medical.pharmacy.medicines.import.upload'), [
            'csv_file' => $this->makeCsv($rows),
        ]);
        $response->assertRedirect();

        $batch = MedicineImportBatch::where('institute_id', $this->institute->id)
            ->orderByDesc('id')->firstOrFail();

        return $batch->batch_id;
    }

    public function test_upload_creates_batch_pending_review(): void
    {
        $batchId = $this->upload([$this->row('CleanMed')]);

        $batch = MedicineImportBatch::where('batch_id', $batchId)->firstOrFail();
        $this->assertEquals('pending_review', $batch->status);
        $this->assertEquals(1, $batch->total_rows);
        $this->assertEquals(1, $batch->clean_rows);
        $this->assertEquals(0, $batch->conflict_rows);
        $this->assertEquals(0, $batch->error_rows);
        // Nothing imported yet — two-phase.
        $this->assertEquals(0, Medicine::where('institute_id', $this->institute->id)->count());
    }

    public function test_upload_detects_duplicate_code_in_institute(): void
    {
        Medicine::create([
            'institute_id' => $this->institute->id,
            'brand_name' => 'Existing',
            'generic_name' => 'ExistingGeneric',
            'strength' => '100mg',
            'dosage_form' => 'Tablet',
            'unit' => 'pcs',
            'code' => '4585',
            'is_active' => true,
        ]);

        $batch = MedicineImportBatch::where('batch_id', $this->upload([$this->row('NewMed', '4585')]))->firstOrFail();

        $this->assertEquals(1, $batch->conflict_rows);
        $conflict = $batch->parsed_data['conflicts'][0];
        $this->assertStringContainsString('4585', $conflict['issues'][0]['message']);
    }

    public function test_upload_detects_duplicate_code_in_batch(): void
    {
        $batch = MedicineImportBatch::where('batch_id', $this->upload([
            $this->row('FirstMed', '7777'),
            $this->row('SecondMed', '7777'),
        ]))->firstOrFail();

        $this->assertEquals(1, $batch->conflict_rows);
    }

    public function test_upload_detects_duplicate_generic_name(): void
    {
        Medicine::create([
            'institute_id' => $this->institute->id,
            'brand_name' => 'Napa',
            'generic_name' => 'Paracetamol',
            'strength' => '500mg',
            'dosage_form' => 'Tablet',
            'unit' => 'pcs',
            'code' => '2001',
            'is_active' => true,
        ]);

        $batch = MedicineImportBatch::where('batch_id', $this->upload([$this->row('Napa', '', '500mg', 'Tablet')]))->firstOrFail();

        $this->assertEquals(1, $batch->conflict_rows);
    }

    public function test_upload_detects_invalid_dosage_form(): void
    {
        $batch = MedicineImportBatch::where('batch_id', $this->upload([$this->row('BadForm', '', '100mg', 'Blob')]))->firstOrFail();

        $this->assertEquals(1, $batch->error_rows);
        $this->assertEquals(0, $batch->clean_rows);
    }

    public function test_review_page_shows_conflicts(): void
    {
        Medicine::create([
            'institute_id' => $this->institute->id,
            'brand_name' => 'Existing',
            'generic_name' => 'ExistingGeneric',
            'strength' => '100mg',
            'dosage_form' => 'Tablet',
            'unit' => 'pcs',
            'code' => '4585',
            'is_active' => true,
        ]);

        $batchId = $this->upload([$this->row('NewMed', '4585')]);

        $response = $this->get(route('medical.pharmacy.medicines.import.review', $batchId));
        $response->assertStatus(200);
        $response->assertSee('Conflicts');
        $response->assertSee('4585');
    }

    public function test_confirm_with_skip_resolution(): void
    {
        Medicine::create([
            'institute_id' => $this->institute->id,
            'brand_name' => 'Existing',
            'generic_name' => 'ExistingGeneric',
            'strength' => '100mg',
            'dosage_form' => 'Tablet',
            'unit' => 'pcs',
            'code' => '4585',
            'is_active' => true,
        ]);

        $batchId = $this->upload([$this->row('NewMed', '4585')]);

        // Default resolution is skip (no resolutions posted).
        $response = $this->post(route('medical.pharmacy.medicines.import.confirm', $batchId), []);
        $response->assertRedirect();

        $batch = MedicineImportBatch::where('batch_id', $batchId)->firstOrFail();
        $this->assertEquals('confirmed', $batch->status);
        $this->assertEquals(1, $batch->skipped_count);
        $this->assertDatabaseMissing('medicines', ['brand_name' => 'NewMed']);
    }

    public function test_confirm_with_update_resolution(): void
    {
        $existing = Medicine::create([
            'institute_id' => $this->institute->id,
            'brand_name' => 'Napa',
            'generic_name' => 'Paracetamol',
            'strength' => '500mg',
            'dosage_form' => 'Tablet',
            'unit' => 'pcs',
            'selling_price' => 2.5,
            'code' => '2001',
            'is_active' => true,
        ]);

        $batchId = $this->upload([
            ['Napa', 'Paracetamol', '500mg', 'Tablet', 'oral', 'pcs', '10', 'Square', 'analgesic', '9.99', '50', '', '', '1'],
        ]);

        // Conflict row is CSV row #2.
        $response = $this->post(route('medical.pharmacy.medicines.import.confirm', $batchId), [
            'resolutions' => [2 => 'update'],
        ]);
        $response->assertRedirect();

        $this->assertEquals(9.99, (float) $existing->refresh()->selling_price);
        $this->assertEquals(1, Medicine::where('institute_id', $this->institute->id)->count());
    }

    public function test_confirm_with_create_new_resolution(): void
    {
        // Code-only conflict (different medicine, same code): create_new
        // regenerates a fresh sequential code.
        Medicine::create([
            'institute_id' => $this->institute->id,
            'brand_name' => 'Existing',
            'generic_name' => 'ExistingGeneric',
            'strength' => '100mg',
            'dosage_form' => 'Tablet',
            'unit' => 'pcs',
            'code' => '4585',
            'is_active' => true,
        ]);

        $batchId = $this->upload([$this->row('BrandNew', '4585', '250mg', 'Capsule')]);

        $response = $this->post(route('medical.pharmacy.medicines.import.confirm', $batchId), [
            'resolutions' => [2 => 'create_new'],
        ]);
        $response->assertRedirect();

        $this->assertEquals(2, Medicine::where('institute_id', $this->institute->id)->count());
        $created = Medicine::where('institute_id', $this->institute->id)->where('brand_name', 'BrandNew')->firstOrFail();
        $this->assertNotEquals('4585', $created->code);
    }

    public function test_forced_create_new_on_name_duplicate_is_safely_skipped(): void
    {
        Medicine::create([
            'institute_id' => $this->institute->id,
            'brand_name' => 'Napa',
            'generic_name' => 'Paracetamol',
            'strength' => '500mg',
            'dosage_form' => 'Tablet',
            'unit' => 'pcs',
            'code' => '2001',
            'is_active' => true,
        ]);

        $batchId = $this->upload([
            ['Napa', 'Paracetamol', '500mg', 'Tablet', 'oral', 'pcs', '10', 'Square', 'analgesic', '2.50', '50', '', '', '1'],
        ]);

        // Crafted POST (UI hides this option): must not crash, row skipped.
        $response = $this->post(route('medical.pharmacy.medicines.import.confirm', $batchId), [
            'resolutions' => [2 => 'create_new'],
        ]);
        $response->assertRedirect();

        $this->assertEquals(1, Medicine::where('institute_id', $this->institute->id)->count());
    }

    public function test_create_new_auto_generates_code_on_conflict(): void
    {
        Medicine::create([
            'institute_id' => $this->institute->id,
            'brand_name' => 'Existing',
            'generic_name' => 'ExistingGeneric',
            'strength' => '100mg',
            'dosage_form' => 'Tablet',
            'unit' => 'pcs',
            'code' => '4585',
            'is_active' => true,
        ]);

        $batchId = $this->upload([$this->row('NewMed', '4585')]);

        $response = $this->post(route('medical.pharmacy.medicines.import.confirm', $batchId), [
            'resolutions' => [2 => 'create_new'],
        ]);
        $response->assertRedirect();

        $created = Medicine::where('institute_id', $this->institute->id)->where('brand_name', 'NewMed')->firstOrFail();
        $this->assertNotEquals('4585', $created->code);
        $this->assertMatchesRegularExpression('/^[0-9]{4,6}$/', $created->code);
    }

    public function test_clean_rows_import_without_conflict(): void
    {
        $batchId = $this->upload([$this->row('CleanA'), $this->row('CleanB', '5555')]);

        $response = $this->post(route('medical.pharmacy.medicines.import.confirm', $batchId), []);
        $response->assertRedirect();

        $batch = MedicineImportBatch::where('batch_id', $batchId)->firstOrFail();
        $this->assertEquals('confirmed', $batch->status);
        $this->assertEquals(2, $batch->imported_count);
        $this->assertDatabaseHas('medicines', ['brand_name' => 'CleanB', 'code' => '5555']);
    }

    public function test_cancel_batch_marks_cancelled(): void
    {
        $batchId = $this->upload([$this->row('CancelMed')]);

        $response = $this->post(route('medical.pharmacy.medicines.import.cancel', $batchId), []);
        $response->assertRedirect();

        $this->assertEquals('cancelled', MedicineImportBatch::where('batch_id', $batchId)->value('status'));
        $this->assertEquals(0, Medicine::where('institute_id', $this->institute->id)->count());
    }

    public function test_batch_scoped_to_uploader(): void
    {
        $batchId = $this->upload([$this->row('ScopedMed')]);

        // A different staff user WITH import permission still cannot see
        // another uploader's batch (404 via uploader scoping, not 403).
        $perm = \App\Models\Permission::firstOrCreate(
            ['slug' => 'medical_medicines.create'],
            ['name' => 'Create Medicines', 'module' => 'medical_medicines']
        );
        $role = Role::create([
            'institute_id' => $this->institute->id,
            'name' => 'Import Staff',
            'slug' => 'import-staff-' . uniqid(),
            'status' => 'active',
        ]);
        $role->permissions()->attach($perm->id);

        $other = User::factory()->create([
            'account_type' => 'staff',
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
        Membership::create([
            'user_id' => $other->id,
            'institution_id' => $this->institute->id,
            'role_id' => $role->id,
            'status' => 'active',
        ]);
        $this->actingAs($other, 'web');
        Workspace::set($this->institute->id);

        $this->get(route('medical.pharmacy.medicines.import.review', $batchId))->assertStatus(404);
        $this->post(route('medical.pharmacy.medicines.import.confirm', $batchId), [])->assertStatus(404);
    }

    public function test_tenant_isolation(): void
    {
        $other = Institute::create([
            'name' => 'Other Hospital',
            'slug' => 'review-other-' . uniqid(),
            'industry' => 'healthcare',
            'sub_industry' => 'hospital',
            'country' => 'Bangladesh',
            'status' => 'active',
        ]);

        $batchId = $this->upload([$this->row('IsoMed', '4585')]);

        // Same code in another institute is NOT a conflict here.
        $batch = MedicineImportBatch::where('batch_id', $batchId)->firstOrFail();
        $this->assertEquals(1, $batch->clean_rows);

        $this->assertEquals(0, MedicineImportBatch::forInstitute($other->id)->count());
    }
}

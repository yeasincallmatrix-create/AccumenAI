<?php

namespace Tests\Feature;

use App\Models\Institute;
use App\Models\Medical\Medicine;
use App\Models\Membership;
use App\Models\Role;
use App\Models\User;
use App\Support\Workspace;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class MedicineImportTest extends TestCase
{
    use DatabaseTransactions;

    private Institute $institute;
    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $this->institute = Institute::create([
            'name' => 'Import Test Hospital',
            'slug' => 'import-test-'.uniqid(),
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
        $content = implode("\n", $lines);
        $tmp = tempnam(sys_get_temp_dir(), 'med_csv_');
        file_put_contents($tmp, $content);

        return new UploadedFile($tmp, 'medicines.csv', 'text/csv', null, true);
    }

    public function test_import_valid_csv_creates_medicines(): void
    {
        $csv = $this->makeCsv([
            ['Napa', 'Paracetamol', '500mg', 'Tablet', 'oral', 'pcs', '10', 'Square', 'analgesic', '2.50', '50', '', '', '1'],
            ['Seclo', 'Omeprazole', '20mg', 'Capsule', 'oral', 'pcs', '30', 'Square', 'gastro', '5.00', '30', '', '', '1'],
            ['Azithro', 'Azithromycin', '250mg', 'Tablet', 'oral', 'pcs', '6', 'Square', 'antibiotic', '12.00', '20', '', '', '1'],
        ]);

        $response = $this->post(route('medical.pharmacy.medicines.import'), [
            'csv_file' => $csv,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('medicines', ['brand_name' => 'Napa', 'institute_id' => $this->institute->id]);
        $this->assertDatabaseHas('medicines', ['brand_name' => 'Seclo', 'institute_id' => $this->institute->id]);
        $this->assertDatabaseHas('medicines', ['brand_name' => 'Azithro', 'institute_id' => $this->institute->id]);
        $this->assertEquals(3, Medicine::where('institute_id', $this->institute->id)->count());
    }

    public function test_import_skips_duplicates(): void
    {
        Medicine::create([
            'institute_id' => $this->institute->id,
            'brand_name' => 'Napa',
            'generic_name' => 'Paracetamol',
            'strength' => '500mg',
            'dosage_form' => 'Tablet',
            'unit' => 'pcs',
            'code' => 'MED-EXISTING',
            'normalized_name' => 'napa500mg',
            'is_active' => true,
        ]);

        $csv = $this->makeCsv([
            ['Napa', 'Paracetamol', '500mg', 'Tablet', 'oral', 'pcs', '10', 'Square', 'analgesic', '2.50', '50', '', '', '1'],
            ['Seclo', 'Omeprazole', '20mg', 'Capsule', 'oral', 'pcs', '30', 'Square', 'gastro', '5.00', '30', '', '', '1'],
            ['NewMed', 'NewGeneric', '100mg', 'Tablet', 'oral', 'pcs', '10', 'Square', 'other', '3.00', '10', '', '', '1'],
        ]);

        $response = $this->post(route('medical.pharmacy.medicines.import'), [
            'csv_file' => $csv,
        ]);

        $response->assertRedirect();
        $this->assertEquals(3, Medicine::where('institute_id', $this->institute->id)->count());
    }

    public function test_import_rejects_invalid_dosage_form(): void
    {
        $csv = $this->makeCsv([
            ['Napa', 'Paracetamol', '500mg', 'Tablet', 'oral', 'pcs', '10', 'Square', 'analgesic', '2.50', '50', '', '', '1'],
            ['Bad', 'BadGeneric', '100mg', 'Blob', 'oral', 'pcs', '10', 'Square', 'other', '1.00', '10', '', '', '1'],
        ]);

        $response = $this->post(route('medical.pharmacy.medicines.import'), [
            'csv_file' => $csv,
        ]);

        $response->assertRedirect();
        $this->assertEquals(1, Medicine::where('institute_id', $this->institute->id)->count());
        $this->assertDatabaseHas('medicines', ['brand_name' => 'Napa']);
    }

    public function test_import_requires_required_columns(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'med_csv_');
        file_put_contents($tmp, "generic_name,strength\nParacetamol,500mg\n");
        $csv = new UploadedFile($tmp, 'bad.csv', 'text/csv', null, true);

        $response = $this->post(route('medical.pharmacy.medicines.import'), [
            'csv_file' => $csv,
        ]);

        $response->assertSessionHasErrors('csv_file');
    }

    public function test_import_generates_code_when_empty(): void
    {
        $csv = $this->makeCsv([
            ['Napa', 'Paracetamol', '500mg', 'Tablet', 'oral', 'pcs', '10', 'Square', 'analgesic', '2.50', '50', '', '', '1'],
        ]);

        $this->post(route('medical.pharmacy.medicines.import'), [
            'csv_file' => $csv,
        ]);

        $medicine = Medicine::where('institute_id', $this->institute->id)->first();
        $this->assertNotNull($medicine);
        // Empty CSV code → sequential numeric auto-assignment.
        $this->assertMatchesRegularExpression('/^[0-9]{4,6}$/', $medicine->code);
    }

    public function test_import_respects_institute_isolation(): void
    {
        $otherInstitute = Institute::create([
            'name' => 'Other Hospital',
            'slug' => 'other-'.uniqid(),
            'industry' => 'healthcare',
            'sub_industry' => 'hospital',
            'country' => 'Bangladesh',
            'status' => 'active',
        ]);

        $csv = $this->makeCsv([
            ['Napa', 'Paracetamol', '500mg', 'Tablet', 'oral', 'pcs', '10', 'Square', 'analgesic', '2.50', '50', '', '', '1'],
        ]);

        $this->post(route('medical.pharmacy.medicines.import'), [
            'csv_file' => $csv,
        ]);

        $this->assertEquals(1, Medicine::where('institute_id', $this->institute->id)->count());
        $this->assertEquals(0, Medicine::where('institute_id', $otherInstitute->id)->count());
    }

    public function test_import_skips_empty_rows(): void
    {
        $csv = $this->makeCsv([
            ['Napa', 'Paracetamol', '500mg', 'Tablet', 'oral', 'pcs', '10', 'Square', 'analgesic', '2.50', '50', '', '', '1'],
            ['', '', '', '', '', '', '', '', '', '', '', '', '', ''],
            ['Seclo', 'Omeprazole', '20mg', 'Capsule', 'oral', 'pcs', '30', 'Square', 'gastro', '5.00', '30', '', '', '1'],
        ]);

        $this->post(route('medical.pharmacy.medicines.import'), [
            'csv_file' => $csv,
        ]);

        $this->assertEquals(2, Medicine::where('institute_id', $this->institute->id)->count());
    }

    public function test_import_max_file_size_enforced(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'med_csv_');
        file_put_contents($tmp, str_repeat('x', 6 * 1024 * 1024));
        $csv = new UploadedFile($tmp, 'big.csv', 'text/csv', null, true);

        $response = $this->post(route('medical.pharmacy.medicines.import'), [
            'csv_file' => $csv,
        ]);

        $response->assertSessionHasErrors('csv_file');
    }

    // ─── Code column validation ──────────────────────────────

    public function test_import_rejects_invalid_code_format(): void
    {
        $csv = $this->makeCsv([
            ['BadRx', 'BadGeneric', '100mg', 'Tablet', 'oral', 'pcs', '10', 'Square', 'other', '1.00', '10', 'RX-001', '', '1'],
            ['Good', 'GoodGeneric', '100mg', 'Tablet', 'oral', 'pcs', '10', 'Square', 'other', '1.00', '10', '', '', '1'],
        ]);

        $response = $this->post(route('medical.pharmacy.medicines.import'), [
            'csv_file' => $csv,
        ]);

        $response->assertRedirect();
        // Bad row skipped, good row imported (sequential code assigned).
        $this->assertEquals(1, Medicine::where('institute_id', $this->institute->id)->count());
        $this->assertDatabaseHas('medicines', ['brand_name' => 'Good']);
        $this->assertDatabaseMissing('medicines', ['brand_name' => 'BadRx']);
    }

    public function test_import_rejects_code_with_letters(): void
    {
        $csv = $this->makeCsv([
            ['BadAlpha', 'BadGeneric', '100mg', 'Tablet', 'oral', 'pcs', '10', 'Square', 'other', '1.00', '10', 'ABCD', '', '1'],
        ]);

        $this->post(route('medical.pharmacy.medicines.import'), [
            'csv_file' => $csv,
        ]);

        $this->assertEquals(0, Medicine::where('institute_id', $this->institute->id)->count());
    }

    public function test_import_rejects_duplicate_code(): void
    {
        Medicine::create([
            'institute_id' => $this->institute->id,
            'brand_name' => 'Existing',
            'generic_name' => 'ExistingGeneric',
            'strength' => '100mg',
            'dosage_form' => 'Tablet',
            'unit' => 'pcs',
            'code' => '1000',
            'is_active' => true,
        ]);

        $csv = $this->makeCsv([
            ['DupCode', 'DupGeneric', '100mg', 'Tablet', 'oral', 'pcs', '10', 'Square', 'other', '1.00', '10', '1000', '', '1'],
        ]);

        $this->post(route('medical.pharmacy.medicines.import'), [
            'csv_file' => $csv,
        ]);

        $this->assertEquals(1, Medicine::where('institute_id', $this->institute->id)->count());
        $this->assertDatabaseMissing('medicines', ['brand_name' => 'DupCode']);
    }

    public function test_import_accepts_4_digit_numeric_code(): void
    {
        $csv = $this->makeCsv([
            ['FourDigit', 'FourGeneric', '100mg', 'Tablet', 'oral', 'pcs', '10', 'Square', 'other', '1.00', '10', '4585', '', '1'],
        ]);

        $this->post(route('medical.pharmacy.medicines.import'), [
            'csv_file' => $csv,
        ]);

        $this->assertDatabaseHas('medicines', [
            'institute_id' => $this->institute->id,
            'brand_name' => 'FourDigit',
            'code' => '4585',
        ]);
    }

    public function test_import_accepts_6_digit_numeric_code(): void
    {
        $csv = $this->makeCsv([
            ['SixDigit', 'SixGeneric', '100mg', 'Tablet', 'oral', 'pcs', '10', 'Square', 'other', '1.00', '10', '123456', '', '1'],
        ]);

        $this->post(route('medical.pharmacy.medicines.import'), [
            'csv_file' => $csv,
        ]);

        $this->assertDatabaseHas('medicines', [
            'institute_id' => $this->institute->id,
            'brand_name' => 'SixDigit',
            'code' => '123456',
        ]);
    }

    public function test_import_accepts_legacy_med_code(): void
    {
        $csv = $this->makeCsv([
            ['LegacyMed', 'LegacyGeneric', '100mg', 'Tablet', 'oral', 'pcs', '10', 'Square', 'other', '1.00', '10', 'MED-189-ABC123', '', '1'],
        ]);

        $this->post(route('medical.pharmacy.medicines.import'), [
            'csv_file' => $csv,
        ]);

        $this->assertDatabaseHas('medicines', [
            'institute_id' => $this->institute->id,
            'brand_name' => 'LegacyMed',
            'code' => 'MED-189-ABC123',
        ]);
    }

    public function test_import_auto_generates_when_code_empty(): void
    {
        $csv = $this->makeCsv([
            ['AutoGen', 'AutoGeneric', '100mg', 'Tablet', 'oral', 'pcs', '10', 'Square', 'other', '1.00', '10', '', '', '1'],
        ]);

        $this->post(route('medical.pharmacy.medicines.import'), [
            'csv_file' => $csv,
        ]);

        $medicine = Medicine::where('institute_id', $this->institute->id)->where('brand_name', 'AutoGen')->first();
        $this->assertNotNull($medicine);
        $this->assertMatchesRegularExpression('/^[0-9]{4,6}$/', $medicine->code);
    }
}

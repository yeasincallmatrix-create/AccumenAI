<?php

namespace Tests\Feature;

use App\Models\Institute;
use App\Models\Medical\Doctor;
use App\Models\Medical\Patient;
use App\Models\Medical\Prescription;
use App\Models\Medical\PrescriptionAuditLog;
use App\Models\Membership;
use App\Models\Role;
use App\Models\User;
use App\Support\Workspace;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class PrescriptionAmendmentTest extends TestCase
{
    use DatabaseTransactions;

    private Institute $institute;
    private User $owner;
    private User $doctor;
    private Patient $patient;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $this->institute = Institute::create([
            'name' => 'Rx Amend Test Hospital',
            'slug' => 'rx-amend-test-' . uniqid(),
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

        Doctor::create([
            'institute_id' => $this->institute->id,
            'user_id' => $this->doctor->id,
            'registration_number' => 'REG-' . strtoupper(uniqid()),
        ]);

        $this->patient = Patient::create([
            'institute_id' => $this->institute->id,
            'first_name' => 'Amend',
            'last_name' => 'Test',
            'date_of_birth' => '1990-01-15',
            'gender' => 'male',
            'phone' => '01712345678',
            'mr_number' => 'MR-' . strtoupper(uniqid()),
        ]);

        $this->actingAs($this->owner, 'web');
        Workspace::set($this->institute->id);
    }

    private function createRx(array $overrides = []): Prescription
    {
        return Prescription::create(array_merge([
            'institute_id' => $this->institute->id,
            'patient_id' => $this->patient->id,
            'doctor_id' => $this->doctor->id,
            'prescription_number' => 'RX-' . strtoupper(uniqid()),
            'prescription_date' => now()->format('Y-m-d'),
            'diagnosis' => 'Test Dx',
            'is_finalized' => 0,
            'version' => 1,
        ], $overrides));
    }

    public function test_model_has_version_fields(): void
    {
        $rx = $this->createRx();
        $this->assertEquals(1, $rx->version);
        $this->assertNull($rx->parent_prescription_id);
        $this->assertNull($rx->amendment_reason);
        $this->assertTrue($rx->isLatestVersion());
        $this->assertFalse($rx->isAmendment());
        $this->assertFalse($rx->isAmended());
    }

    public function test_model_is_amendment_when_parent_set(): void
    {
        $parent = $this->createRx();
        $child = $this->createRx([
            'version' => 2,
            'parent_prescription_id' => $parent->id,
        ]);
        $this->assertTrue($child->isAmendment());
        $this->assertTrue($child->isLatestVersion());
    }

    public function test_model_is_amended_when_has_child(): void
    {
        $parent = $this->createRx();
        $this->createRx([
            'version' => 2,
            'parent_prescription_id' => $parent->id,
        ]);
        $parent->refresh();
        $this->assertTrue($parent->isAmended());
        $this->assertFalse($parent->isLatestVersion());
    }

    public function test_model_today_for_doctor_patient_scope(): void
    {
        $this->createRx();
        $found = Prescription::todayForDoctorPatient($this->doctor->id, $this->patient->id, $this->institute->id)->first();
        $this->assertNotNull($found);
    }

    public function test_amend_form_loads_when_finalized(): void
    {
        $rx = $this->createRx(['is_finalized' => 1]);
        $response = $this->get(route('medical.prescriptions.amend', $rx));
        $response->assertOk()->assertSee('Amend Prescription');
    }

    public function test_amend_form_rejects_non_finalized(): void
    {
        $rx = $this->createRx(['is_finalized' => 0]);
        $response = $this->get(route('medical.prescriptions.amend', $rx));
        $response->assertStatus(422);
    }

    public function test_amend_creates_new_version(): void
    {
        $rx = $this->createRx(['is_finalized' => 1]);
        $response = $this->post(route('medical.prescriptions.amend.store', $rx), [
            'patient_id' => $this->patient->id,
            'doctor_id' => $this->doctor->id,
            'date' => now()->format('Y-m-d'),
            'diagnosis' => 'Updated Dx',
            'amendment_reason' => 'Patient response to initial treatment',
            'items' => [
                ['medicine_name' => 'Amoxicillin', 'dosage' => '500mg', 'frequency' => '1+0+1', 'duration_days' => 5, 'quantity' => 10],
            ],
        ]);
        $response->assertSessionHasNoErrors();

        $newRx = Prescription::where('parent_prescription_id', $rx->id)->first();
        $this->assertNotNull($newRx);
        $this->assertEquals(2, $newRx->version);
        $this->assertEquals('Updated Dx', $newRx->diagnosis);
        $this->assertEquals('Patient response to initial treatment', $newRx->amendment_reason);
        $this->assertEquals($rx->id, $newRx->parent_prescription_id);
    }

    public function test_amend_preserves_original_version(): void
    {
        $rx = $this->createRx(['is_finalized' => 1, 'prescription_number' => 'RX-ORIG-001']);
        $this->post(route('medical.prescriptions.amend.store', $rx), [
            'patient_id' => $this->patient->id,
            'doctor_id' => $this->doctor->id,
            'date' => now()->format('Y-m-d'),
            'diagnosis' => 'Dx',
            'amendment_reason' => 'Reason',
            'items' => [['medicine_name' => 'Drug', 'dosage' => '10mg', 'frequency' => '1+0+0', 'duration_days' => 1, 'quantity' => 1]],
        ]);

        $newRx = Prescription::where('parent_prescription_id', $rx->id)->first();
        $this->assertNotNull($newRx);
        $this->assertEquals($rx->id, $newRx->parent_prescription_id);
        $this->assertEquals(2, $newRx->version);
        $this->assertEquals($rx->prescription_date, $newRx->prescription_date);
    }

    public function test_amend_logs_prescription_audit(): void
    {
        $rx = $this->createRx(['is_finalized' => 1]);
        $this->post(route('medical.prescriptions.amend.store', $rx), [
            'patient_id' => $this->patient->id,
            'doctor_id' => $this->doctor->id,
            'date' => now()->format('Y-m-d'),
            'diagnosis' => 'Dx',
            'amendment_reason' => 'Audit log test',
            'items' => [['medicine_name' => 'X', 'dosage' => '1mg', 'frequency' => '1+0+0', 'duration_days' => 1, 'quantity' => 1]],
        ]);

        $newRx = Prescription::where('parent_prescription_id', $rx->id)->first();
        $log = PrescriptionAuditLog::where('prescription_id', $newRx->id)->where('action', 'amended')->first();
        $this->assertNotNull($log);
        $this->assertNotEmpty($log->detail);
    }

    public function test_duplicate_guard_rejects_same_doctor_patient_date(): void
    {
        $this->createRx();
        $response = $this->getJson(route('medical.prescriptions.check-existing', [
            'patient_id' => $this->patient->id,
            'doctor_id' => $this->doctor->id,
            'date' => now()->format('Y-m-d'),
        ]));
        $response->assertOk()->assertJson(['exists' => true]);
    }

    public function test_duplicate_guard_allows_different_doctor(): void
    {
        $this->createRx();
        $otherDoctor = User::factory()->create(['account_type' => 'staff', 'email_verified_at' => now(), 'status' => 'active']);
        Doctor::create(['institute_id' => $this->institute->id, 'user_id' => $otherDoctor->id, 'registration_number' => 'REG-' . strtoupper(uniqid())]);

        $response = $this->getJson(route('medical.prescriptions.check-existing', [
            'patient_id' => $this->patient->id,
            'doctor_id' => $otherDoctor->id,
            'date' => now()->format('Y-m-d'),
        ]));
        $response->assertOk()->assertJson(['exists' => false]);
    }

    public function test_check_existing_returns_version_info(): void
    {
        $rx = $this->createRx();
        $response = $this->getJson(route('medical.prescriptions.check-existing', [
            'patient_id' => $this->patient->id,
            'doctor_id' => $this->doctor->id,
            'date' => now()->format('Y-m-d'),
        ]));
        $response->assertOk()->assertJsonPath('version', 1);
    }

    public function test_routes_are_registered(): void
    {
        $rx = $this->createRx(['is_finalized' => 1]);
        $this->get(route('medical.prescriptions.amend', $rx))->assertStatus(200);
    }

    public function test_amend_requires_reason(): void
    {
        $rx = $this->createRx(['is_finalized' => 1]);
        $response = $this->post(route('medical.prescriptions.amend.store', $rx), [
            'patient_id' => $this->patient->id,
            'doctor_id' => $this->doctor->id,
            'date' => now()->format('Y-m-d'),
            'diagnosis' => 'Dx',
            'amendment_reason' => '',
            'items' => [['medicine_name' => 'X', 'dosage' => '1mg', 'frequency' => '1+0+0', 'duration_days' => 1, 'quantity' => 1]],
        ]);
        $response->assertSessionHasErrors(['amendment_reason']);
    }

    public function test_amend_preserves_original_items_in_old_version(): void
    {
        $rx = $this->createRx(['is_finalized' => 1]);
        $originalNumber = $rx->prescription_number;

        $this->post(route('medical.prescriptions.amend.store', $rx), [
            'patient_id' => $this->patient->id,
            'doctor_id' => $this->doctor->id,
            'date' => now()->format('Y-m-d'),
            'diagnosis' => 'New Dx',
            'amendment_reason' => 'Changed',
            'items' => [['medicine_name' => 'New Drug', 'dosage' => '5mg', 'frequency' => '0+1+1', 'duration_days' => 7, 'quantity' => 14]],
        ]);

        $original = Prescription::where('prescription_number', $originalNumber)->where('version', 1)->first();
        $this->assertNotNull($original);
        $this->assertEquals(1, $original->version);
        $this->assertNull($original->parent_prescription_id);
    }

    public function test_unique_constraint_blocks_duplicate_version_at_db_level(): void
    {
        // Create v1 via application layer
        $rx = $this->createRx(['version' => 1]);
        $this->assertNotNull($rx);

        // Attempt to insert a duplicate v1 via raw DB — must hit UNIQUE constraint
        $this->expectException(\Illuminate\Database\QueryException::class);

        \DB::table('prescriptions')->insert([
            'institute_id'        => $this->institute->id,
            'patient_id'          => $this->patient->id,
            'doctor_id'           => $this->doctor->id,
            'prescription_number' => 'RX-DUPLICATE-DB-TEST',
            'prescription_date'   => now()->format('Y-m-d'),
            'version'             => 1,
            'is_finalized'        => false,
            'created_at'          => now(),
            'updated_at'          => now(),
        ]);
    }

    public function test_isLatestVersion_returns_false_for_invalid_version(): void
    {
        $rx = $this->createRx(['version' => 0]);
        $this->assertFalse($rx->isLatestVersion());
    }

    public function test_amend_blocks_version_below_one(): void
    {
        $rx = $this->createRx(['is_finalized' => 1, 'version' => 0]);
        $response = $this->get(route('medical.prescriptions.amend', $rx));
        $response->assertStatus(422);
    }

    public function test_amend_store_blocks_version_below_one(): void
    {
        $rx = $this->createRx(['is_finalized' => 1, 'version' => 0]);
        $response = $this->post(route('medical.prescriptions.amend.store', $rx), [
            'patient_id' => $this->patient->id,
            'doctor_id' => $this->doctor->id,
            'date' => now()->format('Y-m-d'),
            'diagnosis' => 'Dx',
            'amendment_reason' => 'Test amendment of invalid version',
            'items' => [['medicine_name' => 'X', 'dosage' => '1mg', 'frequency' => '1+0+0', 'duration_days' => 1, 'quantity' => 1]],
        ]);
        $response->assertStatus(422);
    }

    public function test_today_for_doctor_patient_excludes_version_zero(): void
    {
        $this->createRx(['version' => 0]);
        $found = Prescription::todayForDoctorPatient($this->doctor->id, $this->patient->id, $this->institute->id);
        $this->assertNull($found);
    }

    public function test_renumbered_prescriptions_have_correct_lineage(): void
    {
        $v1 = $this->createRx(['is_finalized' => 1, 'version' => 1]);
        $v2 = $this->createRx([
            'version' => 2,
            'parent_prescription_id' => $v1->id,
            'is_finalized' => 1,
            'prescription_number' => 'RX-TEST-V2-' . uniqid(),
        ]);

        $this->assertEquals(1, $v1->version);
        $this->assertNull($v1->parent_prescription_id);
        $this->assertFalse($v1->isLatestVersion());
        $this->assertTrue($v1->isAmended());

        $this->assertEquals(2, $v2->version);
        $this->assertEquals($v1->id, $v2->parent_prescription_id);
        $this->assertTrue($v2->isAmendment());
        $this->assertTrue($v2->isLatestVersion());
        $this->assertFalse($v2->isAmended());

        $v1->refresh();
        $this->assertTrue($v1->isAmended());
        $this->assertFalse($v1->isLatestVersion());
    }
}

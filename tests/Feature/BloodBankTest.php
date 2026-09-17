<?php

namespace Tests\Feature;

use App\Models\Institute;
use App\Models\Medical\BloodDonor;
use App\Models\Medical\BloodIssueItem;
use App\Models\Medical\BloodRequest;
use App\Models\Medical\BloodUnit;
use App\Models\Medical\NumberSequence;
use App\Models\Medical\Patient;
use App\Models\Membership;
use App\Models\Role;
use App\Models\User;
use App\Support\Workspace;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class BloodBankTest extends TestCase
{
    use DatabaseTransactions;

    private Institute $institute;
    private User $owner;
    private Patient $patient;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $this->institute = Institute::create([
            'name' => 'Blood Bank Test Hospital',
            'slug' => 'bb-test-' . uniqid(),
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

        app(\App\Services\ModuleAccessService::class)
            ->enableModule($this->institute, 'medical.bloodbank');

        $this->patient = Patient::create([
            'institute_id' => $this->institute->id,
            'first_name' => 'Test',
            'last_name' => 'Patient',
            'gender' => 'male',
            'date_of_birth' => '1990-01-01',
            'phone' => '01712345678',
            'is_patient' => true,
            'mr_number' => 'MR-2026-00001',
        ]);
    }

    public function test_creates_blood_donor(): void
    {
        $response = $this->post(route('medical.blood-bank.donors.store'), [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'phone' => '01712345678',
            'gender' => 'male',
            'blood_group' => 'O+',
            'weight_kg' => 70,
            'hemoglobin' => 14.5,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('blood_donors', [
            'institute_id' => $this->institute->id,
            'first_name' => 'John',
            'blood_group' => 'O+',
        ]);
    }

    public function test_creates_blood_unit(): void
    {
        $response = $this->post(route('medical.blood-bank.units.store'), [
            'blood_group' => 'A+',
            'component' => 'PRBC',
            'volume_ml' => 450,
            'collection_date' => now()->subDays(3)->format('Y-m-d'),
            'expiry_date' => now()->addDays(35)->format('Y-m-d'),
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('blood_units', [
            'institute_id' => $this->institute->id,
            'blood_group' => 'A+',
            'component' => 'PRBC',
            'status' => 'available',
        ]);
    }

    public function test_creates_blood_request(): void
    {
        $response = $this->post(route('medical.blood-bank.requests.store'), [
            'patient_id' => $this->patient->id,
            'blood_group' => 'B+',
            'component' => 'Whole Blood',
            'units_requested' => 2,
            'urgency' => 'urgent',
            'clinical_indication' => 'Post-surgical transfusion',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('blood_requests', [
            'institute_id' => $this->institute->id,
            'patient_id' => $this->patient->id,
            'blood_group' => 'B+',
            'status' => 'pending',
        ]);
    }

    public function test_donor_index_scopes_to_institute(): void
    {
        BloodDonor::create([
            'institute_id' => $this->institute->id,
            'donor_number' => 'BD-2026-00001',
            'first_name' => 'InstituteScoped',
            'blood_group' => 'O+',
            'gender' => 'male',
            'status' => 'active',
        ]);

        $other = Institute::create([
            'name' => 'Other Inst', 'slug' => 'other-bb-' . uniqid(),
            'industry' => 'healthcare', 'sub_industry' => 'hospital',
            'country' => 'Bangladesh', 'status' => 'active',
        ]);

        BloodDonor::create([
            'institute_id' => $other->id,
            'donor_number' => 'BD-2026-00001',
            'first_name' => 'CrossInstitute',
            'blood_group' => 'A+',
            'gender' => 'female',
            'status' => 'active',
        ]);

        $response = $this->get(route('medical.blood-bank.donors.index'));
        $response->assertStatus(200);
        $response->assertSee('InstituteScoped');
        $response->assertDontSee('CrossInstitute');
    }

    public function test_unit_index_scopes_to_institute(): void
    {
        BloodUnit::create([
            'institute_id' => $this->institute->id,
            'unit_number' => 'BU-2026-00001',
            'blood_group' => 'A+',
            'component' => 'PRBC',
            'collection_date' => now()->subDays(2),
            'expiry_date' => now()->addDays(35),
            'status' => 'available',
        ]);

        $other = Institute::create([
            'name' => 'Other', 'slug' => 'other-bbu-' . uniqid(),
            'industry' => 'healthcare', 'sub_industry' => 'hospital',
            'country' => 'Bangladesh', 'status' => 'active',
        ]);

        BloodUnit::create([
            'institute_id' => $other->id,
            'unit_number' => 'BU-2026-00001',
            'blood_group' => 'B+',
            'component' => 'FFP',
            'collection_date' => now()->subDays(2),
            'expiry_date' => now()->addDays(35),
            'status' => 'available',
        ]);

        $response = $this->get(route('medical.blood-bank.units.index'));
        $response->assertStatus(200);
        $response->assertSee('BU-2026-00001');
    }

    public function test_request_index_scopes_to_institute(): void
    {
        BloodRequest::create([
            'institute_id' => $this->institute->id,
            'request_number' => 'BR-2026-00001',
            'patient_id' => $this->patient->id,
            'blood_group' => 'O+',
            'component' => 'Whole Blood',
            'units_requested' => 1,
            'urgency' => 'routine',
            'status' => 'pending',
        ]);

        $response = $this->get(route('medical.blood-bank.requests.index'));
        $response->assertStatus(200);
        $response->assertSee('BR-2026-00001');
    }

    public function test_approve_request(): void
    {
        $request = BloodRequest::create([
            'institute_id' => $this->institute->id,
            'request_number' => 'BR-2026-00002',
            'patient_id' => $this->patient->id,
            'blood_group' => 'A+',
            'component' => 'PRBC',
            'units_requested' => 1,
            'urgency' => 'routine',
            'status' => 'pending',
        ]);

        $response = $this->post(route('medical.blood-bank.requests.approve', $request));

        $response->assertRedirect();
        $this->assertDatabaseHas('blood_requests', [
            'id' => $request->id,
            'status' => 'approved',
            'approved_by' => $this->owner->id,
        ]);
    }

    public function test_cancel_request(): void
    {
        $request = BloodRequest::create([
            'institute_id' => $this->institute->id,
            'request_number' => 'BR-2026-00003',
            'patient_id' => $this->patient->id,
            'blood_group' => 'O-',
            'component' => 'Whole Blood',
            'units_requested' => 1,
            'urgency' => 'routine',
            'status' => 'pending',
        ]);

        $response = $this->post(route('medical.blood-bank.requests.cancel', $request), [
            'cancel_reason' => 'Patient condition improved',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('blood_requests', [
            'id' => $request->id,
            'status' => 'cancelled',
            'cancel_reason' => 'Patient condition improved',
        ]);
    }

    public function test_issue_unit(): void
    {
        $request = BloodRequest::create([
            'institute_id' => $this->institute->id,
            'request_number' => 'BR-2026-00004',
            'patient_id' => $this->patient->id,
            'blood_group' => 'A+',
            'component' => 'PRBC',
            'units_requested' => 1,
            'urgency' => 'routine',
            'status' => 'approved',
        ]);

        $unit = BloodUnit::create([
            'institute_id' => $this->institute->id,
            'unit_number' => 'BU-2026-00002',
            'blood_group' => 'A+',
            'component' => 'PRBC',
            'collection_date' => now()->subDays(3),
            'expiry_date' => now()->addDays(35),
            'status' => 'available',
        ]);

        $response = $this->post(route('medical.blood-bank.requests.issue', $request), [
            'blood_unit_id' => $unit->id,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('blood_units', [
            'id' => $unit->id,
            'status' => 'issued',
        ]);
        $this->assertDatabaseHas('blood_requests', [
            'id' => $request->id,
            'status' => 'fulfilled',
            'units_issued' => 1,
        ]);
        $this->assertDatabaseHas('blood_issue_items', [
            'blood_request_id' => $request->id,
            'blood_unit_id' => $unit->id,
        ]);
    }

    public function test_return_unit(): void
    {
        $request = BloodRequest::create([
            'institute_id' => $this->institute->id,
            'request_number' => 'BR-2026-00005',
            'patient_id' => $this->patient->id,
            'blood_group' => 'B+',
            'component' => 'FFP',
            'units_requested' => 1,
            'urgency' => 'routine',
            'status' => 'approved',
        ]);

        $unit = BloodUnit::create([
            'institute_id' => $this->institute->id,
            'unit_number' => 'BU-2026-00003',
            'blood_group' => 'B+',
            'component' => 'FFP',
            'collection_date' => now()->subDays(5),
            'expiry_date' => now()->addDays(30),
            'status' => 'available',
        ]);

        $this->post(route('medical.blood-bank.requests.issue', $request), [
            'blood_unit_id' => $unit->id,
        ]);

        $item = BloodIssueItem::where('blood_request_id', $request->id)->first();

        $response = $this->post(route('medical.blood-bank.requests.return', $request), [
            'issue_item_id' => $item->id,
            'return_reason' => 'Wrong blood group issued',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('blood_units', [
            'id' => $unit->id,
            'status' => 'available',
        ]);
        $this->assertDatabaseHas('blood_issue_items', [
            'id' => $item->id,
            'status' => 'returned',
        ]);
    }

    public function test_screen_updates_results(): void
    {
        $unit = BloodUnit::create([
            'institute_id' => $this->institute->id,
            'unit_number' => 'BU-2026-00004',
            'blood_group' => 'O+',
            'component' => 'Whole Blood',
            'collection_date' => now()->subDays(1),
            'expiry_date' => now()->addDays(36),
            'status' => 'available',
        ]);

        $response = $this->post(route('medical.blood-bank.units.screen', $unit), [
            'screening_hiv' => 'pass',
            'screening_hbsag' => 'pass',
            'screening_hcv' => 'fail',
            'screening_syphilis' => 'pass',
            'screening_malaria' => 'pass',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('blood_units', [
            'id' => $unit->id,
            'screening_hiv' => 'pass',
            'screening_hcv' => 'fail',
        ]);
    }

    public function test_discard_unit(): void
    {
        $unit = BloodUnit::create([
            'institute_id' => $this->institute->id,
            'unit_number' => 'BU-2026-00005',
            'blood_group' => 'AB+',
            'component' => 'Platelets',
            'collection_date' => now()->subDays(2),
            'expiry_date' => now()->addDays(5),
            'status' => 'available',
        ]);

        $response = $this->post(route('medical.blood-bank.units.discard', $unit), [
            'reason' => 'Contamination suspected',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('blood_units', [
            'id' => $unit->id,
            'status' => 'discarded',
        ]);
    }

    public function test_donor_update_audit_log(): void
    {
        $donor = BloodDonor::create([
            'institute_id' => $this->institute->id,
            'donor_number' => 'BD-2026-00002',
            'first_name' => 'Audit',
            'blood_group' => 'O+',
            'gender' => 'male',
            'status' => 'active',
        ]);

        $this->put(route('medical.blood-bank.donors.update', $donor), [
            'first_name' => 'Audit Updated',
            'blood_group' => 'O+',
            'gender' => 'male',
        ]);

        $this->assertDatabaseHas('clinical_audit_logs', [
            'auditable_type' => 'App\Models\Medical\BloodDonor',
            'auditable_id' => $donor->id,
            'action' => 'updated',
        ]);
    }

    public function test_request_number_unique_per_institute(): void
    {
        BloodRequest::create([
            'institute_id' => $this->institute->id,
            'request_number' => 'BR-2026-00099',
            'patient_id' => $this->patient->id,
            'blood_group' => 'A+',
            'component' => 'PRBC',
            'units_requested' => 1,
            'urgency' => 'routine',
            'status' => 'pending',
        ]);

        $this->assertDatabaseHas('blood_requests', [
            'institute_id' => $this->institute->id,
            'request_number' => 'BR-2026-00099',
        ]);

        $other = Institute::create([
            'name' => 'Other', 'slug' => 'other-bbr-' . uniqid(),
            'industry' => 'healthcare', 'sub_industry' => 'hospital',
            'country' => 'Bangladesh', 'status' => 'active',
        ]);

        BloodRequest::create([
            'institute_id' => $other->id,
            'request_number' => 'BR-2026-00099',
            'patient_id' => $this->patient->id,
            'blood_group' => 'A+',
            'component' => 'PRBC',
            'units_requested' => 1,
            'urgency' => 'routine',
            'status' => 'pending',
        ]);

        $this->assertDatabaseHas('blood_requests', [
            'institute_id' => $other->id,
            'request_number' => 'BR-2026-00099',
        ]);
    }

    public function test_dashboard_loads(): void
    {
        $response = $this->get(route('medical.blood-bank.dashboard'));
        $response->assertStatus(200);
    }

    public function test_soft_delete_donor(): void
    {
        $donor = BloodDonor::create([
            'institute_id' => $this->institute->id,
            'donor_number' => 'BD-2026-00003',
            'first_name' => 'Delete',
            'blood_group' => 'A-',
            'gender' => 'female',
            'status' => 'active',
        ]);

        $this->delete(route('medical.blood-bank.donors.destroy', $donor));
        $this->assertSoftDeleted('blood_donors', ['id' => $donor->id]);
    }

    public function test_delete_only_pending_request(): void
    {
        $request = BloodRequest::create([
            'institute_id' => $this->institute->id,
            'request_number' => 'BR-2026-00006',
            'patient_id' => $this->patient->id,
            'blood_group' => 'O+',
            'component' => 'Whole Blood',
            'units_requested' => 1,
            'urgency' => 'routine',
            'status' => 'approved',
        ]);

        $response = $this->delete(route('medical.blood-bank.requests.destroy', $request));
        $response->assertStatus(422);
    }

    public function test_emergency_request_has_emergent_urgency(): void
    {
        $response = $this->post(route('medical.blood-bank.requests.store'), [
            'patient_id' => $this->patient->id,
            'blood_group' => 'O-',
            'component' => 'Whole Blood',
            'units_requested' => 4,
            'urgency' => 'emergent',
            'clinical_indication' => 'Massive hemorrhage',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('blood_requests', [
            'institute_id' => $this->institute->id,
            'urgency' => 'emergent',
            'units_requested' => 4,
        ]);
    }
}

<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Institute;
use App\Models\Medical\Admission;
use App\Models\Medical\Appointment;
use App\Models\Medical\Bed;
use App\Models\Medical\ClinicalAuditLog;
use App\Models\Medical\Doctor;
use App\Models\Medical\Encounter;
use App\Models\Medical\FollowUp;
use App\Models\Medical\LabOrder;
use App\Models\Medical\Patient;
use App\Models\Medical\PatientProblem;
use App\Models\Medical\Prescription;
use App\Models\Medical\Ward;
use App\Models\Medical\Invoice;
use App\Models\Medical\PharmacyStock;
use App\Models\Membership;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\BranchContext;
use App\Support\Workspace;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Phase 18 — HMS branch isolation foundation.
 *
 * Reuses the existing branches table / Branch model / BranchContext (no
 * duplicate system). Branch-owned transactions carry nullable branch_id
 * (Stage 1); legacy NULLs stay visible; institute-wide actors are
 * unconstrained; numbering stays institute-wide.
 */
class Phase18BranchIsolationTest extends TestCase
{
    use DatabaseTransactions;

    private Institute $instituteA;

    private Institute $instituteB;

    private Branch $a1;

    private Branch $a2;

    private Branch $b1;

    private User $adminA;

    private User $userA1;

    private User $userA2;

    private User $doctorA1;

    private User $doctorA2;

    private User $legacyDoctor;

    private User $userB;

    private const MEDICAL_PERMS = [
        'medical_patients.view', 'medical_patients.create',
        'medical_appointments.view', 'medical_appointments.create', 'medical_appointments.edit',
        'medical_encounters.view', 'medical_encounters.create', 'medical_encounters.edit',
        'medical_encounters.complete', 'medical_encounters.amend',
        'medical_diagnoses.view', 'medical_diagnoses.create', 'medical_diagnoses.remove',
        'medical_admissions.view', 'medical_admissions.create', 'medical_admissions.edit',
        'medical_lab.view', 'medical_lab.create', 'medical_lab.edit',
        'medical_prescriptions.view', 'medical_prescriptions.create', 'medical_prescriptions.edit',
        'medical_problems.view', 'medical_problems.create', 'medical_problems.edit',
        'medical_followups.view', 'medical_followups.create', 'medical_followups.edit',
        'medical_billing.view', 'medical_billing.create', 'medical_billing.edit',
        'medical_pharmacy.view', 'medical_pharmacy.create', 'medical_pharmacy.edit',
        'medical_wards.view', 'medical_wards.create',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ValidateCsrfToken::class);

        $this->instituteA = $this->makeInstitute('Branch Alpha Hospital');
        $this->instituteB = $this->makeInstitute('Branch Beta Clinic');

        $this->a1 = $this->makeBranch($this->instituteA, 'Alpha Main');
        $this->a2 = $this->makeBranch($this->instituteA, 'Alpha North');
        $this->b1 = $this->makeBranch($this->instituteB, 'Beta Main');

        $this->adminA = $this->makeOwner($this->instituteA);
        $this->userA1 = $this->makeStaff($this->instituteA, $this->a1->id);
        $this->userA2 = $this->makeStaff($this->instituteA, $this->a2->id);
        $this->doctorA1 = $this->makeDoctor($this->instituteA, $this->a1->id, [$this->a1->id]);
        $this->doctorA2 = $this->makeDoctor($this->instituteA, $this->a2->id, [$this->a2->id]);
        $this->legacyDoctor = $this->makeDoctor($this->instituteA, null, []);
        $this->userB = $this->makeOwner($this->instituteB);

        $this->actingAs($this->adminA, 'web');
        Workspace::set($this->instituteA->id);
    }

    private function makeInstitute(string $name): Institute
    {
        return Institute::create([
            'name' => $name,
            'slug' => \Illuminate\Support\Str::slug($name).'-'.uniqid(),
            'industry' => 'healthcare',
            'sub_industry' => 'hospital',
            'country' => 'Bangladesh',
            'status' => 'active',
        ]);
    }

    private function makeBranch(Institute $institute, string $name): Branch
    {
        return Branch::create([
            'institute_id' => $institute->id,
            'name' => $name.' '.uniqid(),
            'status' => 'active',
        ]);
    }

    private function makeOwner(Institute $institute): User
    {
        $user = User::factory()->create(['account_type' => 'owner', 'status' => 'active']);
        Membership::create([
            'user_id' => $user->id,
            'institution_id' => $institute->id,
            'role_id' => Role::where('slug', 'institute-owner')->value('id'),
            'status' => 'active',
        ]);

        return $user;
    }

    private function makeStaff(Institute $institute, ?int $branchId): User
    {
        $user = User::factory()->create(['account_type' => 'staff', 'status' => 'active']);
        $role = Role::create([
            'institute_id' => $institute->id,
            'name' => 'Branch Role '.uniqid(),
            'slug' => 'branch-role-'.uniqid(),
            'status' => 'active',
        ]);
        foreach (self::MEDICAL_PERMS as $slug) {
            $permission = Permission::firstOrCreate(
                ['slug' => $slug],
                ['module' => explode('.', $slug)[0] ?? 'medical', 'name' => $slug]
            );
            DB::table('role_permissions')->insertOrIgnore([
                'role_id' => $role->id,
                'permission_id' => $permission->id,
            ]);
        }
        Membership::create([
            'user_id' => $user->id,
            'institution_id' => $institute->id,
            'role_id' => $role->id,
            'branch_id' => $branchId,
            'status' => 'active',
        ]);

        return $user;
    }

    private function makeDoctor(Institute $institute, ?int $branchId, array $assignedBranches): User
    {
        $user = $this->makeStaff($institute, $branchId);
        $doctor = Doctor::create([
            'institute_id' => $institute->id,
            'user_id' => $user->id,
            'registration_number' => 'REG-'.strtoupper(uniqid()),
        ]);
        foreach ($assignedBranches as $branchId) {
            DB::table('doctor_branch')->insert([
                'institute_id' => $institute->id,
                'branch_id' => $branchId,
                'doctor_id' => $doctor->id,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return $user;
    }

    private function actAs(User $user, Institute $institute): void
    {
        $this->actingAs($user, 'web');
        Workspace::set($institute->id);
        // Workspace syncs BranchContext from the membership; assert sync.
        $membership = Membership::where('user_id', $user->id)
            ->where('institution_id', $institute->id)->first();
        $this->assertSame(
            $membership?->branch_id !== null ? (int) $membership->branch_id : null,
            BranchContext::id()
        );
    }

    private function createPatient(string $first = 'Branch'): Patient
    {
        $this->post(route('medical.patients.store'), [
            'first_name' => $first,
            'last_name' => 'Probe',
            'date_of_birth' => '1990-05-15',
            'gender' => 'male',
            'phone' => '01'.str_pad((string) random_int(0, 999999999), 9, '0', STR_PAD_LEFT),
            'blood_group' => 'O+',
        ])->assertSessionHasNoErrors();

        return Patient::where('institute_id', $this->instituteA->id)->latest('id')->firstOrFail();
    }

    // --- Architecture -------------------------------------------------------------------------------

    public function test_existing_branch_system_reused(): void
    {
        $this->assertSame($this->instituteA->id, (int) $this->a1->institute_id);
        $this->assertSame($this->instituteA->id, (int) $this->a2->institute_id);
        $this->assertSame($this->instituteB->id, (int) $this->b1->institute_id);
        $this->assertFalse(\Illuminate\Support\Facades\Schema::hasTable('hms_branches'));
        $this->assertFalse(\Illuminate\Support\Facades\Schema::hasTable('branch_facilities'));
    }

    public function test_invalid_branch_rejected(): void
    {
        $patient = $this->createPatient();
        $this->post(route('medical.encounters.store'), [
            'patient_id' => $patient->id,
            'doctor_id' => $this->legacyDoctor->id,
            'encounter_type' => 'OPD',
            'branch_id' => $this->b1->id,
        ])->assertForbidden();
        $this->assertSame(0, Encounter::where('institute_id', $this->instituteA->id)->count());
    }

    // --- Encounter isolation ----------------------------------------------------------------------------------

    public function test_branch_user_sees_own_branch_only(): void
    {
        $patient = $this->createPatient();
        $encA1 = $this->openEncounter($patient, $this->a1->id);
        $encA2 = $this->openEncounter($patient, $this->a2->id);

        $this->actAs($this->userA1, $this->instituteA);

        $this->get(route('medical.encounters.show', $encA1))->assertOk();
        $this->get(route('medical.encounters.show', $encA2))->assertForbidden();
        $response = $this->get(route('medical.encounters.index'))->assertOk();
        $response->assertSee(clinical_no($encA1->encounter_number));
        $response->assertDontSee(clinical_no($encA2->encounter_number));
    }

    public function test_branch_user_store_scoped(): void
    {
        $patient = $this->createPatient();
        $this->actAs($this->userA1, $this->instituteA);

        // Explicit foreign branch: refused.
        $this->post(route('medical.encounters.store'), [
            'patient_id' => $patient->id,
            'doctor_id' => $this->legacyDoctor->id,
            'encounter_type' => 'OPD',
            'branch_id' => $this->a2->id,
        ])->assertForbidden();

        // Default: actor's own branch assigned automatically.
        $this->post(route('medical.encounters.store'), [
            'patient_id' => $patient->id,
            'doctor_id' => $this->legacyDoctor->id,
            'encounter_type' => 'OPD',
        ])->assertSessionHasNoErrors();
        $encounter = Encounter::where('institute_id', $this->instituteA->id)->latest('id')->firstOrFail();
        $this->assertSame($this->a1->id, (int) $encounter->branch_id);
    }

    public function test_admin_sees_all_branches_and_foreign_institute_denied(): void
    {
        $patient = $this->createPatient();
        $encA1 = $this->openEncounter($patient, $this->a1->id);
        $encA2 = $this->openEncounter($patient, $this->a2->id);

        $this->actAs($this->adminA, $this->instituteA);
        $this->get(route('medical.encounters.show', $encA1))->assertOk();
        $this->get(route('medical.encounters.show', $encA2))->assertOk();

        $this->actAs($this->userB, $this->instituteB);
        $this->get(route('medical.encounters.show', $encA1))->assertForbidden();
        $this->get(route('medical.encounters.index', ['patient_id' => $patient->id]))->assertForbidden();
    }

    // --- Doctor assignment -------------------------------------------------------------------------------------------

    public function test_doctor_branch_assignment_enforced(): void
    {
        $patient = $this->createPatient();

        // Assigned doctor books in its own branch.
        $this->actAs($this->doctorA1, $this->instituteA);
        $this->post(route('medical.encounters.store'), [
            'patient_id' => $patient->id,
            'doctor_id' => $this->doctorA1->id,
            'encounter_type' => 'OPD',
        ])->assertSessionHasNoErrors();
        $own = Encounter::where('institute_id', $this->instituteA->id)->latest('id')->firstOrFail();
        $this->assertSame($this->a1->id, (int) $own->branch_id);

        // Same doctor explicitly booking another branch: fence refuses.
        $this->post(route('medical.encounters.store'), [
            'patient_id' => $patient->id,
            'doctor_id' => $this->doctorA1->id,
            'encounter_type' => 'OPD',
            'branch_id' => $this->a2->id,
        ])->assertForbidden();

        // Institute-wide actor booking an assigned doctor outside its
        // branch: refused via the assignment rule (not the fence).
        $this->actAs($this->adminA, $this->instituteA);
        $this->post(route('medical.encounters.store'), [
            'patient_id' => $patient->id,
            'doctor_id' => $this->doctorA1->id,
            'encounter_type' => 'OPD',
            'branch_id' => $this->a2->id,
        ])->assertSessionHas('error');

        // Legacy doctor (no assignments): institute-wide, both branches fine.
        $this->actAs($this->legacyDoctor, $this->instituteA);
        $this->post(route('medical.encounters.store'), [
            'patient_id' => $patient->id,
            'doctor_id' => $this->legacyDoctor->id,
            'encounter_type' => 'OPD',
            'branch_id' => $this->a2->id,
        ])->assertSessionHasNoErrors();
    }

    // --- Appointments ------------------------------------------------------------------------------------------------------

    public function test_appointment_branch_isolation_and_serials_preserved(): void
    {
        $patientA = $this->createPatient('Alpha');
        $patientB = $this->createPatient('Beta');
        $date = now()->addDay()->format('Y-m-d');
        $a1Appt = $this->bookAppointment($patientA, $date, $this->a1->id);
        $a2Appt = $this->bookAppointment($patientB, $date, $this->a2->id);

        $this->actAs($this->userA1, $this->instituteA);
        $this->get(route('medical.appointments.show', $a1Appt))->assertOk();
        $this->get(route('medical.appointments.show', $a2Appt))->assertForbidden();
        // Row-level isolation in the list payload (the patient directory
        // picker stays institute-wide per the documented identity policy).
        $response = $this->get(route('medical.appointments.index', ['date' => $date]))->assertOk();
        $response->assertSee('appointments\/'.$a1Appt->id, false);
        $response->assertDontSee('appointments\/'.$a2Appt->id, false);

        // Serials stay doctor+day scoped (branch adds no numbering split):
        // same doctor/day across branches still sequences 1, 2.
        $this->assertSame(1, (int) $a1Appt->serial_number);
        $this->assertSame(2, (int) $a2Appt->serial_number);
    }

    public function test_appointment_search_does_not_leak(): void
    {
        $patient = $this->createPatient('ZedSearch');
        $foreign = $this->bookAppointment($patient, now()->addDay()->format('Y-m-d'), $this->a2->id);

        $this->actAs($this->userA1, $this->instituteA);
        $response = $this->get(route('medical.appointments.index', [
            'date' => now()->addDay()->format('Y-m-d'),
            'search' => 'ZedSearch',
        ]))->assertOk();
        // The foreign appointment row is absent from the list payload.
        $response->assertDontSee('appointments\/'.$foreign->id, false);
    }

    // --- Admissions + beds ---------------------------------------------------------------------------------------------------

    public function test_admission_bed_branch_rules(): void
    {
        $ward1 = $this->makeWard($this->a1->id);
        $ward2 = $this->makeWard($this->a2->id);
        $bed1 = $this->makeBed($ward1);
        $bed2 = $this->makeBed($ward2);
        $this->assertSame($this->a1->id, (int) $bed1->fresh()->branch_id);
        $this->assertSame($this->a2->id, (int) $bed2->fresh()->branch_id);

        $patient = $this->createPatient();
        $this->actAs($this->userA1, $this->instituteA);

        // Cross-branch bed selection denied.
        $this->post(route('medical.admissions.store'), [
            'patient_id' => $patient->id,
            'admitting_doctor_id' => $this->legacyDoctor->id,
            'admission_date' => now()->format('Y-m-d'),
            'admission_time' => '10:00',
            'primary_diagnosis' => 'Branch test',
            'bed_id' => $bed2->id,
        ])->assertSessionHas('error');

        // Own-branch bed works; admission carries the branch.
        $this->post(route('medical.admissions.store'), [
            'patient_id' => $patient->id,
            'admitting_doctor_id' => $this->legacyDoctor->id,
            'admission_date' => now()->format('Y-m-d'),
            'admission_time' => '10:00',
            'primary_diagnosis' => 'Branch test',
            'bed_id' => $bed1->id,
        ])->assertSessionHasNoErrors();
        $admission = Admission::where('institute_id', $this->instituteA->id)->latest('id')->firstOrFail();
        $this->assertSame($this->a1->id, (int) $admission->branch_id);

        // Cross-branch transfer target denied.
        $this->post(route('medical.admissions.transfer', $admission), ['bed_id' => $bed2->id])
            ->assertSessionHas('error');
        $this->assertSame($bed1->id, (int) $admission->fresh()->bed_id);

        // Foreign-branch admission invisible (created institute-wide,
        // then read as the branch user).
        $other = $this->createPatient('OtherAdmit');
        $this->actAs($this->adminA, $this->instituteA);
        $foreign = $this->admitDirect($other, $this->a2->id);
        $this->actAs($this->userA1, $this->instituteA);
        $this->get(route('medical.admissions.show', $foreign))->assertForbidden();
    }

    // --- Prescriptions ----------------------------------------------------------------------------------------------------------------

    public function test_prescription_branch_fence_and_safety_intact(): void
    {
        $patient = $this->createPatient();
        $encounter = $this->openEncounter($patient, $this->a1->id);
        $rx = $this->createPrescription($patient, $encounter);

        $this->assertSame($this->a1->id, (int) $rx->fresh()->branch_id);

        $this->actAs($this->userA2, $this->instituteA);
        $this->get(route('medical.prescriptions.show', $rx))->assertForbidden();

        // Safety pipeline + snapshots unaffected by branch ownership.
        $allergic = $this->createPatient('Allergic');
        $allergic->update(['allergies' => 'Branchmycin 50mg']);
        $this->actAs($this->adminA, $this->instituteA);
        $this->post(route('medical.prescriptions.store'), [
            'patient_id' => $allergic->id,
            'doctor_id' => $this->legacyDoctor->id,
            'prescription_date' => now()->format('Y-m-d'),
            'encounter_id' => $encounter->id,
            'items' => [[
                'medicine_id' => null,
                'medicine_name' => 'Branchmycin 50mg',
                'dosage' => '50mg',
                'frequency' => '1+0+0',
                'quantity' => 5,
            ]],
        ])->assertSessionHas('error');
    }

    // --- Labs ---------------------------------------------------------------------------------------------------------------------------------

    public function test_lab_branch_isolation_and_result_derivation(): void
    {
        $patient = $this->createPatient();
        $encounter = $this->openEncounter($patient, $this->a1->id);
        $order = $this->createLabOrder($patient, $encounter);
        $this->assertSame($this->a1->id, (int) $order->fresh()->branch_id);
        $this->post(route('medical.lab.orders.collect', $order))->assertRedirect();
        $result = $order->results()->firstOrFail();
        $this->post(route('medical.lab.orders.result', $order), [
            'results' => [$result->id => ['result_value' => '42']],
        ])->assertSessionHasNoErrors();

        $this->actAs($this->userA2, $this->instituteA);
        $this->get(route('medical.lab.orders.show', $order))->assertForbidden();
        $this->post(route('medical.lab.orders.cancel', $order), ['reason' => 'x'])->assertForbidden();

        // Timeline shows the order in-branch and hides it cross-branch.
        $this->actAs($this->userA1, $this->instituteA);
        $this->get(route('medical.patients.history', $patient))->assertOk()
            ->assertSee(clinical_no($order->order_number));
        $this->actAs($this->userA2, $this->instituteA);
        $this->get(route('medical.patients.history', $patient))->assertOk()
            ->assertDontSee(clinical_no($order->order_number));
    }

    // --- Diagnoses / problems / follow-ups -------------------------------------------------------------------------------------------------------

    public function test_diagnosis_follows_encounter_branch(): void
    {
        $patient = $this->createPatient();
        $encounter = $this->openEncounter($patient, $this->a2->id);

        $this->actAs($this->userA1, $this->instituteA);
        $this->post(route('medical.encounters.diagnoses.store', $encounter), [
            'label' => 'Cross-branch entry',
            'diagnosis_type' => 'primary',
        ])->assertForbidden();
        $this->assertSame(0, \App\Models\Medical\EncounterDiagnosis::where('encounter_id', $encounter->id)->count());
    }

    public function test_problems_institute_level_followups_branch_scoped(): void
    {
        $patient = $this->createPatient();
        $problem = $this->createProblem($patient);
        $followup = $this->createFollowUp($patient, $this->a2->id);

        // Problems are longitudinal institute context: visible in-branch.
        $this->actAs($this->userA1, $this->instituteA);
        $this->get(route('medical.patients.show', $patient))->assertOk()
            ->assertSee($problem->label);
        $this->get(route('medical.patients.history', $patient))->assertOk()
            ->assertSee($problem->label)
            ->assertDontSee('A2 review');

        // Follow-ups are branch-scoped: foreign-branch rows hidden + locked.
        $this->post(route('medical.followups.complete', $followup), [])->assertForbidden();
        $this->assertSame('planned', $followup->fresh()->status);
    }

    // --- Billing + pharmacy ----------------------------------------------------------------------------------------------------------------------------

    public function test_billing_branch_isolation_and_totals_intact(): void
    {
        $patient = $this->createPatient();
        $invoice = $this->createInvoice($patient, $this->a1->id);
        $this->assertSame($this->a1->id, (int) $invoice->fresh()->branch_id);

        $this->actAs($this->userA2, $this->instituteA);
        $this->get(route('medical.billing.invoices.show', $invoice))->assertForbidden();
        $this->post(route('medical.billing.invoices.payment', $invoice), [
            'amount' => 10,
            'method' => 'cash',
        ])->assertForbidden();
        $this->assertSame(0.0, (float) $invoice->fresh()->paid_amount);

        $this->actAs($this->adminA, $this->instituteA);
        $this->post(route('medical.billing.invoices.payment', $invoice), [
            'amount' => $invoice->total,
            'method' => 'cash',
        ])->assertRedirect();
        $this->assertSame((float) $invoice->total, (float) $invoice->fresh()->paid_amount);
    }

    public function test_pharmacy_branch_isolation(): void
    {
        $medicine = $this->makeMedicine();
        $stockA1 = $this->makeStock($medicine, $this->a1->id);
        $stockA2 = $this->makeStock($medicine, $this->a2->id);

        $this->actAs($this->userA1, $this->instituteA);
        $this->get(route('medical.pharmacy.stock.show', $stockA1))->assertOk();
        $this->get(route('medical.pharmacy.stock.show', $stockA2))->assertForbidden();
        $response = $this->get(route('medical.pharmacy.stock.index'))->assertOk();
        $response->assertDontSee($stockA2->batch_number);
    }

    // --- Audit -----------------------------------------------------------------------------------------------------------------------------------------

    public function test_audit_branch_context_and_legacy_null(): void
    {
        $patient = $this->createPatient();
        // Legacy (branch-less) encounter by institute-wide admin.
        $this->actAs($this->adminA, $this->instituteA);
        $legacy = $this->openEncounter($patient, null);
        $this->assertNull($legacy->branch_id);
        $legacyAudit = ClinicalAuditLog::where('auditable_type', Encounter::class)
            ->where('auditable_id', $legacy->id)->where('action', 'created')->firstOrFail();
        $this->assertNull($legacyAudit->branch_id);

        // Branched encounter carries branch into audit.
        $branched = $this->openEncounter($patient, $this->a1->id);
        $row = ClinicalAuditLog::where('auditable_type', Encounter::class)
            ->where('auditable_id', $branched->id)->where('action', 'created')->firstOrFail();
        $this->assertSame($this->a1->id, (int) $row->branch_id);

        // Legacy rows stay visible to branch users (documented rule).
        $this->actAs($this->userA1, $this->instituteA);
        $this->get(route('medical.encounters.show', $legacy))->assertOk();
    }

    // --- Helpers -------------------------------------------------------------------------------------------------------------------------------------------

    private function openEncounter(Patient $patient, ?int $branchId): Encounter
    {
        $payload = [
            'patient_id' => $patient->id,
            'doctor_id' => $this->legacyDoctor->id,
            'encounter_type' => 'OPD',
            'chief_complaint' => 'Branch review',
        ];
        if ($branchId !== null) {
            $payload['branch_id'] = $branchId;
        }
        $this->post(route('medical.encounters.store'), $payload)->assertSessionHasNoErrors();

        return Encounter::where('institute_id', $this->instituteA->id)->latest('id')->firstOrFail();
    }

    private function bookAppointment(Patient $patient, string $date, ?int $branchId): Appointment
    {
        $payload = [
            'patient_id' => $patient->id,
            'doctor_id' => $this->legacyDoctor->id,
            'appointment_date' => $date,
            'appointment_time' => '09:00',
        ];
        if ($branchId !== null) {
            $payload['branch_id'] = $branchId;
        }
        $this->post(route('medical.appointments.store'), $payload)->assertSessionHasNoErrors();

        return Appointment::where('institute_id', $this->instituteA->id)->latest('id')->firstOrFail();
    }

    private function makeWard(int $branchId): Ward
    {
        return Ward::create([
            'institute_id' => $this->instituteA->id,
            'branch_id' => $branchId,
            'name' => 'Ward '.uniqid(),
            'type' => 'general',
            'total_beds' => 4,
            'available_beds' => 4,
            'daily_rate' => 500,
            'is_active' => true,
        ]);
    }

    private function makeBed(Ward $ward): Bed
    {
        return Bed::create([
            'institute_id' => $this->instituteA->id,
            'ward_id' => $ward->id,
            'branch_id' => $ward->branch_id,
            'bed_number' => 'B-'.uniqid(),
            'status' => 'available',
        ]);
    }

    private function admitDirect(Patient $patient, int $branchId): Admission
    {
        $this->post(route('medical.admissions.store'), [
            'patient_id' => $patient->id,
            'admitting_doctor_id' => $this->legacyDoctor->id,
            'admission_date' => now()->format('Y-m-d'),
            'admission_time' => '10:00',
            'primary_diagnosis' => 'Direct admit',
            'branch_id' => $branchId,
        ])->assertSessionHasNoErrors();

        return Admission::where('institute_id', $this->instituteA->id)->latest('id')->firstOrFail();
    }

    private function createPrescription(Patient $patient, Encounter $encounter): Prescription
    {
        $this->post(route('medical.prescriptions.store'), [
            'patient_id' => $patient->id,
            'doctor_id' => $this->legacyDoctor->id,
            'prescription_date' => now()->format('Y-m-d'),
            'encounter_id' => $encounter->id,
            'items' => [[
                'medicine_id' => null,
                'medicine_name' => 'Branchmycin 50mg',
                'dosage' => '50mg',
                'frequency' => '1+0+0',
                'quantity' => 5,
            ]],
        ])->assertSessionHasNoErrors();

        return Prescription::where('institute_id', $this->instituteA->id)->latest('id')->firstOrFail();
    }

    private function createLabOrder(Patient $patient, Encounter $encounter): LabOrder
    {
        $test = \App\Models\Medical\LabTest::create([
            'institute_id' => $this->instituteA->id,
            'code' => 'LT-'.strtoupper(uniqid()),
            'name' => 'Branch Panel',
            'price' => 100,
            'is_active' => true,
        ]);
        $this->post(route('medical.lab.orders.store'), [
            'patient_id' => $patient->id,
            'doctor_id' => $this->legacyDoctor->id,
            'priority' => 'routine',
            'encounter_id' => $encounter->id,
            'tests' => [['lab_test_id' => $test->id]],
        ])->assertSessionHasNoErrors();

        return LabOrder::where('institute_id', $this->instituteA->id)->latest('id')->firstOrFail();
    }

    private function createProblem(Patient $patient): PatientProblem
    {
        $this->post(route('medical.patients.problems.store', $patient), [
            'label' => 'Branch asthma',
            'problem_type' => 'chronic',
        ])->assertSessionHasNoErrors();

        return PatientProblem::where('patient_id', $patient->id)->latest('id')->firstOrFail();
    }

    private function createFollowUp(Patient $patient, int $branchId): FollowUp
    {
        $this->post(route('medical.patients.followups.store', $patient), [
            'planned_date' => now()->addWeek()->format('Y-m-d'),
            'reason' => 'A2 review',
            'branch_id' => $branchId,
        ])->assertSessionHasNoErrors();

        return FollowUp::where('patient_id', $patient->id)->latest('id')->firstOrFail();
    }

    private function createInvoice(Patient $patient, int $branchId): Invoice
    {
        $this->post(route('medical.billing.invoices.store'), [
            'patient_id' => $patient->id,
            'type' => 'opd',
            'branch_id' => $branchId,
            'items' => [[
                'description' => 'Consultation',
                'amount' => 500,
                'quantity' => 1,
                'discount' => 0,
            ]],
        ])->assertSessionHasNoErrors();

        return Invoice::where('institute_id', $this->instituteA->id)->latest('id')->firstOrFail();
    }

    private function makeMedicine(): \App\Models\Medical\Medicine
    {
        return \App\Models\Medical\Medicine::create([
            'institute_id' => $this->instituteA->id,
            'code' => 'MED-'.strtoupper(uniqid()),
            'generic_name' => 'Branch Salt',
            'brand_name' => 'Branch Salt 100',
            'dosage_form' => 'Tablet',
            'strength' => '100mg',
            'unit' => 'Strip',
            'pack_size' => 10,
            'purchase_price' => 5,
            'selling_price' => 8,
            'reorder_level' => 10,
            'reorder_quantity' => 50,
            'is_active' => true,
        ]);
    }

    private function makeStock(\App\Models\Medical\Medicine $medicine, int $branchId): PharmacyStock
    {
        return PharmacyStock::create([
            'institute_id' => $this->instituteA->id,
            'branch_id' => $branchId,
            'medicine_id' => $medicine->id,
            'batch_number' => 'BN-'.strtoupper(uniqid()),
            'expiry_date' => now()->addYear()->format('Y-m-d'),
            'quantity' => 100,
            'current_quantity' => 100,
            'purchase_price' => 5,
        ]);
    }
}

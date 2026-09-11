<?php

namespace Tests\Feature;

use App\Models\Institute;
use App\Models\Medical\ClinicalAuditLog;
use App\Models\Medical\Doctor;
use App\Models\Medical\Encounter;
use App\Models\Medical\EncounterDiagnosis;
use App\Models\Medical\FollowUp;
use App\Models\Medical\Patient;
use App\Models\Medical\PatientProblem;
use App\Models\Membership;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\Medical\PatientTimelineService;
use App\Support\Workspace;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Phase 17 — Longitudinal problem list + clinical follow-up.
 *
 * Problems are explicit clinician records (never auto-derived from
 * diagnoses); follow-ups are planning records (never appointments).
 * Every mutation is fenced (institute + patient) and audited.
 */
class Phase17ProblemListAndFollowUpTest extends TestCase
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
            'name' => 'Problem Test Hospital',
            'slug' => 'problem-test-'.uniqid(),
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

    private function createPatient(string $first = 'Problem'): Patient
    {
        $this->post(route('medical.patients.store'), [
            'first_name' => $first,
            'last_name' => 'Probe',
            'date_of_birth' => '1990-05-15',
            'gender' => 'male',
            'phone' => '01'.str_pad((string) random_int(0, 999999999), 9, '0', STR_PAD_LEFT),
            'blood_group' => 'O+',
        ])->assertSessionHasNoErrors();

        return Patient::where('institute_id', $this->institute->id)->latest('id')->firstOrFail();
    }

    private function openEncounter(Patient $patient): Encounter
    {
        $this->post(route('medical.encounters.store'), [
            'patient_id' => $patient->id,
            'doctor_id' => $this->doctor->id,
            'encounter_type' => 'OPD',
            'chief_complaint' => 'Problem review',
        ])->assertSessionHasNoErrors();

        return Encounter::where('institute_id', $this->institute->id)->latest('id')->firstOrFail();
    }

    private function addDiagnosis(Encounter $encounter, string $label): EncounterDiagnosis
    {
        $this->post(route('medical.encounters.diagnoses.store', $encounter), [
            'label' => $label,
            'diagnosis_type' => 'primary',
        ])->assertSessionHasNoErrors();

        return EncounterDiagnosis::where('encounter_id', $encounter->id)->latest('id')->firstOrFail();
    }

    private function createProblem(Patient $patient, array $overrides = []): PatientProblem
    {
        $response = $this->post(route('medical.patients.problems.store', $patient), array_merge([
            'label' => 'Persistent cough',
            'problem_type' => 'chronic',
        ], $overrides));
        $response->assertSessionHasNoErrors();

        return PatientProblem::where('patient_id', $patient->id)->latest('id')->firstOrFail();
    }

    private function createFollowUp(Patient $patient, array $overrides = []): FollowUp
    {
        $response = $this->post(route('medical.patients.followups.store', $patient), array_merge([
            'planned_date' => now()->addWeek()->format('Y-m-d'),
            'reason' => 'Review persistent cough',
        ], $overrides));
        $response->assertSessionHasNoErrors();

        return FollowUp::where('patient_id', $patient->id)->latest('id')->firstOrFail();
    }

    private function roleWith(array $slugs): User
    {
        $user = User::factory()->create(['account_type' => 'staff', 'status' => 'active']);
        $role = Role::create([
            'institute_id' => $this->institute->id,
            'name' => 'Problem Role '.uniqid(),
            'slug' => 'problem-role-'.uniqid(),
            'status' => 'active',
        ]);
        foreach ($slugs as $slug) {
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
            'institution_id' => $this->institute->id,
            'role_id' => $role->id,
            'status' => 'active',
        ]);

        return $user;
    }

    private function rivalContext(): void
    {
        $other = Institute::create([
            'name' => 'Problem Rival', 'slug' => 'problem-rival-'.uniqid(),
            'industry' => 'healthcare', 'sub_industry' => 'clinic',
            'country' => 'Bangladesh', 'status' => 'active',
        ]);
        $rival = User::factory()->create(['account_type' => 'owner', 'status' => 'active']);
        Membership::create([
            'user_id' => $rival->id, 'institution_id' => $other->id,
            'role_id' => Role::where('slug', 'institute-owner')->value('id'), 'status' => 'active',
        ]);
        $this->actingAs($rival, 'web');
        Workspace::set($other->id);
    }

    // --- Problem creation ---------------------------------------------------------------

    public function test_authorized_clinician_creates_problem(): void
    {
        $patient = $this->createPatient();
        $problem = $this->createProblem($patient);

        $this->assertSame('active', $problem->status);
        $this->assertSame('chronic', $problem->problem_type);
        $this->assertSame('unresolved', $problem->mapping_status);
        $this->assertNull($problem->onset_date);
        $this->get(route('medical.patients.show', $patient))
            ->assertOk()
            ->assertSee('Persistent cough');
    }

    public function test_problem_validation_required(): void
    {
        $patient = $this->createPatient();
        $this->post(route('medical.patients.problems.store', $patient), [
            'problem_type' => 'chronic',
        ])->assertSessionHasErrors(['label']);
        $this->post(route('medical.patients.problems.store', $patient), [
            'label' => 'No type',
        ])->assertSessionHasErrors(['problem_type']);
        $this->assertSame(0, PatientProblem::where('patient_id', $patient->id)->count());
    }

    public function test_problem_with_source_encounter_and_diagnosis(): void
    {
        $patient = $this->createPatient();
        $encounter = $this->openEncounter($patient);
        $diagnosis = $this->addDiagnosis($encounter, 'Source fever');

        $problem = $this->createProblem($patient, [
            'label' => 'Recurrent fever',
            'problem_type' => 'condition',
            'encounter_id' => $encounter->id,
            'encounter_diagnosis_id' => $diagnosis->id,
            'onset_date' => now()->subMonth()->format('Y-m-d'),
        ]);

        $this->assertSame($encounter->id, (int) $problem->encounter_id);
        $this->assertSame($diagnosis->id, (int) $problem->encounter_diagnosis_id);
        $this->assertNotNull($problem->onset_date);
        // Source diagnosis untouched by linkage.
        $this->assertSame('active', $diagnosis->fresh()->status);
        $this->assertSame('Source fever', $diagnosis->fresh()->label);
    }

    public function test_invalid_source_rejected(): void
    {
        $patient = $this->createPatient();
        $other = $this->createPatient('Other');
        $foreignEncounter = $this->openEncounter($other);

        $this->post(route('medical.patients.problems.store', $patient), [
            'label' => 'Bad link',
            'problem_type' => 'acute',
            'encounter_id' => $foreignEncounter->id,
        ])->assertSessionHas('error');
        $this->assertSame(0, PatientProblem::where('patient_id', $patient->id)->count());
    }

    public function test_cross_patient_diagnosis_link_rejected(): void
    {
        $patient = $this->createPatient('Owner');
        $other = $this->createPatient('Other');
        $otherEncounter = $this->openEncounter($other);
        $otherDiagnosis = $this->addDiagnosis($otherEncounter, 'Foreign diagnosis');
        $ownEncounter = $this->openEncounter($patient);

        // Diagnosis of another patient: refused.
        $this->post(route('medical.patients.problems.store', $patient), [
            'label' => 'Mismatch',
            'problem_type' => 'acute',
            'encounter_diagnosis_id' => $otherDiagnosis->id,
        ])->assertSessionHas('error');

        // Diagnosis not in the stated encounter: refused.
        $this->post(route('medical.patients.problems.store', $patient), [
            'label' => 'Mismatch 2',
            'problem_type' => 'acute',
            'encounter_id' => $ownEncounter->id,
            'encounter_diagnosis_id' => $otherDiagnosis->id,
        ])->assertSessionHas('error');
        $this->assertSame(0, PatientProblem::where('patient_id', $patient->id)->count());
    }

    public function test_supplied_code_stays_unresolved(): void
    {
        $patient = $this->createPatient();
        $problem = $this->createProblem($patient, [
            'label' => 'Suspected typhoid',
            'problem_type' => 'condition',
            'code' => 'A01.0',
            'code_system' => 'ICD-10',
        ]);

        $this->assertSame('A01.0', $problem->code);
        $this->assertSame('unresolved', $problem->mapping_status);
    }

    // --- Problem lifecycle ----------------------------------------------------------------------

    public function test_active_inactive_reactivate(): void
    {
        $patient = $this->createPatient();
        $problem = $this->createProblem($patient);

        $this->patch(route('medical.problems.inactivate', $problem), [])->assertSessionHasNoErrors();
        $this->assertSame('inactive', $problem->fresh()->status);

        $this->patch(route('medical.problems.reactivate', $problem), [])->assertSessionHasNoErrors();
        $this->assertSame('active', $problem->fresh()->status);
    }

    public function test_active_resolved_and_inactive_resolved(): void
    {
        $patient = $this->createPatient();
        $direct = $this->createProblem($patient, ['label' => 'Direct resolve']);

        $this->patch(route('medical.problems.resolve', $direct), [])->assertSessionHasNoErrors();
        $direct->refresh();
        $this->assertSame('resolved', $direct->status);
        $this->assertSame(now()->format('Y-m-d'), $direct->resolved_date->format('Y-m-d'));

        $via = $this->createProblem($patient, ['label' => 'Via inactive']);
        $this->patch(route('medical.problems.inactivate', $via), [])->assertSessionHasNoErrors();
        $this->patch(route('medical.problems.resolve', $via), [
            'resolved_date' => now()->subDays(3)->format('Y-m-d'),
        ])->assertSessionHasNoErrors();
        $via->refresh();
        $this->assertSame('resolved', $via->status);
        $this->assertSame(now()->subDays(3)->format('Y-m-d'), $via->resolved_date->format('Y-m-d'));
    }

    public function test_invalid_transitions_rejected(): void
    {
        $patient = $this->createPatient();
        $problem = $this->createProblem($patient);
        $this->patch(route('medical.problems.resolve', $problem), [])->assertSessionHasNoErrors();

        // Resolved is terminal: reactivate + inactivate refused.
        $this->patch(route('medical.problems.reactivate', $problem), [])->assertSessionHas('error');
        $this->patch(route('medical.problems.inactivate', $problem), [])->assertSessionHas('error');
        $this->assertSame('resolved', $problem->fresh()->status);

        // Double resolve refused, single resolve audit.
        $this->patch(route('medical.problems.resolve', $problem), [])->assertSessionHas('error');
        $this->assertSame(1, ClinicalAuditLog::where('auditable_type', PatientProblem::class)
            ->where('auditable_id', $problem->id)->where('action', 'problem_resolved')->count());
    }

    public function test_no_destructive_problem_paths(): void
    {
        $this->assertFalse(Route::has('medical.problems.destroy'));

        $patient = $this->createPatient();
        $problem = $this->createProblem($patient);
        $this->patch(route('medical.problems.resolve', $problem), [])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('medical_patient_problems', ['id' => $problem->id, 'status' => 'resolved']);
    }

    public function test_link_and_unlink(): void
    {
        $patient = $this->createPatient();
        $encounter = $this->openEncounter($patient);
        $diagnosis = $this->addDiagnosis($encounter, 'Linkable');
        $problem = $this->createProblem($patient);

        $this->patch(route('medical.problems.link', $problem), [
            'encounter_id' => $encounter->id,
            'encounter_diagnosis_id' => $diagnosis->id,
        ])->assertSessionHasNoErrors();
        $this->assertSame($encounter->id, (int) $problem->fresh()->encounter_id);

        $this->patch(route('medical.problems.unlink', $problem), [
            'reason' => 'Wrong source',
        ])->assertSessionHasNoErrors();
        $problem->refresh();
        $this->assertNull($problem->encounter_id);
        $this->assertNull($problem->encounter_diagnosis_id);
        // Source diagnosis untouched.
        $this->assertSame('active', $diagnosis->fresh()->status);

        $this->assertTrue(ClinicalAuditLog::where('auditable_type', PatientProblem::class)
            ->where('auditable_id', $problem->id)->where('action', 'problem_linked')->exists());
        $row = ClinicalAuditLog::where('auditable_type', PatientProblem::class)
            ->where('auditable_id', $problem->id)->where('action', 'problem_unlinked')->firstOrFail();
        $this->assertSame('Wrong source', $row->reason);
    }

    // --- Follow-up ----------------------------------------------------------------------------------------

    public function test_create_planned_followup(): void
    {
        $patient = $this->createPatient();
        $encounter = $this->openEncounter($patient);
        $problem = $this->createProblem($patient);

        $followup = $this->createFollowUp($patient, [
            'encounter_id' => $encounter->id,
            'problem_id' => $problem->id,
            'assigned_to' => $this->doctor->id,
        ]);

        $this->assertSame('planned', $followup->status);
        $this->assertSame($encounter->id, (int) $followup->encounter_id);
        $this->assertSame($problem->id, (int) $followup->problem_id);
        $this->assertSame($this->doctor->id, (int) $followup->assigned_to);
        $this->assertSame(0, \App\Models\Medical\Appointment::where('patient_id', $patient->id)->count());
        $this->get(route('medical.patients.show', $patient))->assertOk()->assertSee('Review persistent cough');
    }

    public function test_followup_validation(): void
    {
        $patient = $this->createPatient();
        $this->post(route('medical.patients.followups.store', $patient), [
            'reason' => 'No date',
        ])->assertSessionHasErrors(['planned_date']);
        $this->post(route('medical.patients.followups.store', $patient), [
            'planned_date' => now()->addWeek()->format('Y-m-d'),
        ])->assertSessionHasErrors(['reason']);
        $this->assertSame(0, FollowUp::where('patient_id', $patient->id)->count());
    }

    public function test_followup_complete_and_cancel(): void
    {
        $patient = $this->createPatient();
        $completing = $this->createFollowUp($patient, ['reason' => 'Complete me']);
        $cancelling = $this->createFollowUp($patient, ['reason' => 'Cancel me']);

        $this->post(route('medical.followups.complete', $completing), [])->assertSessionHasNoErrors();
        $completing->refresh();
        $this->assertSame('completed', $completing->status);
        $this->assertNotNull($completing->completed_at);

        $this->post(route('medical.followups.cancel', $cancelling), [])->assertSessionHas('error');
        $this->assertSame('planned', $cancelling->fresh()->status);
        $this->post(route('medical.followups.cancel', $cancelling), ['reason' => 'No longer needed'])
            ->assertSessionHasNoErrors();
        $cancelling->refresh();
        $this->assertSame('cancelled', $cancelling->status);
        $this->assertSame('No longer needed', $cancelling->cancel_reason);
    }

    public function test_followup_terminal_states_final(): void
    {
        $patient = $this->createPatient();
        $followup = $this->createFollowUp($patient);
        $this->post(route('medical.followups.complete', $followup), [])->assertSessionHasNoErrors();

        $this->post(route('medical.followups.cancel', $followup), ['reason' => 'x'])->assertSessionHas('error');
        $this->post(route('medical.followups.complete', $followup), [])->assertSessionHas('error');
        $this->assertSame('completed', $followup->fresh()->status);
        $this->assertDatabaseHas('medical_follow_ups', ['id' => $followup->id, 'status' => 'completed']);
        $this->assertFalse(Route::has('medical.followups.destroy'));
    }

    public function test_followup_wrong_patient_links_rejected(): void
    {
        $patient = $this->createPatient('Owner');
        $other = $this->createPatient('Other');
        $otherEncounter = $this->openEncounter($other);
        $otherProblem = $this->createProblem($other);

        $this->post(route('medical.patients.followups.store', $patient), [
            'planned_date' => now()->addWeek()->format('Y-m-d'),
            'reason' => 'Foreign encounter',
            'encounter_id' => $otherEncounter->id,
        ])->assertSessionHas('error');
        $this->post(route('medical.patients.followups.store', $patient), [
            'planned_date' => now()->addWeek()->format('Y-m-d'),
            'reason' => 'Foreign problem',
            'problem_id' => $otherProblem->id,
        ])->assertSessionHas('error');
        $this->assertSame(0, FollowUp::where('patient_id', $patient->id)->count());
    }

    // --- Security ---------------------------------------------------------------------------------------------

    public function test_cross_institute_problem_matrix(): void
    {
        $patient = $this->createPatient();
        $encounter = $this->openEncounter($patient);
        $diagnosis = $this->addDiagnosis($encounter, 'Home diagnosis');
        $problem = $this->createProblem($patient);
        $followup = $this->createFollowUp($patient);

        $this->rivalContext();

        // Viewing, creating, modifying or resolving the foreign problem is refused.
        $this->post(route('medical.patients.problems.store', $patient), [
            'label' => 'Foreign', 'problem_type' => 'acute',
        ])->assertForbidden();
        $this->patch(route('medical.problems.inactivate', $problem), [])->assertForbidden();
        $this->patch(route('medical.problems.resolve', $problem), [])->assertForbidden();
        $this->patch(route('medical.problems.link', $problem), [
            'encounter_id' => $encounter->id,
        ])->assertForbidden();
        $this->post(route('medical.patients.followups.store', $patient), [
            'planned_date' => now()->addWeek()->format('Y-m-d'),
            'reason' => 'Foreign',
        ])->assertForbidden();
        $this->post(route('medical.followups.complete', $followup), [])->assertForbidden();
        $this->post(route('medical.followups.cancel', $followup), ['reason' => 'x'])->assertForbidden();
        $this->get(route('medical.patients.show', $patient))->assertForbidden();

        $this->assertSame('active', $problem->fresh()->status);
        $this->assertSame('planned', $followup->fresh()->status);
        $this->assertSame('active', $diagnosis->fresh()->status);
    }

    public function test_cross_patient_isolation(): void
    {
        $patient = $this->createPatient('Owner');
        $other = $this->createPatient('Other');
        $problem = $this->createProblem($other);
        $followup = $this->createFollowUp($other);

        // Same institute, other patient: store routes are patient-scoped so
        // nothing leaks; member routes resolve the problem's own patient.
        $this->get(route('medical.patients.show', $patient))->assertOk()
            ->assertDontSee($problem->label);
        $this->assertSame($other->id, (int) $problem->fresh()->patient_id);
        $this->assertSame($other->id, (int) $followup->fresh()->patient_id);
    }

    public function test_doctor_fence_isolation(): void
    {
        $patient = $this->createPatient();
        $encounter = $this->openEncounter($patient);
        $problem = $this->createProblem($patient);
        $followup = $this->createFollowUp($patient);

        // Second doctor with no relationship to this patient.
        $stranger = User::factory()->create(['account_type' => 'staff', 'status' => 'active']);
        Doctor::create([
            'institute_id' => $this->institute->id,
            'user_id' => $stranger->id,
            'registration_number' => 'REG-'.strtoupper(uniqid()),
        ]);
        $this->actingAs($stranger, 'web');
        Workspace::set($this->institute->id);

        // Unrelated doctor: patient invisible entirely (owner-less role, no
        // grants) — problem/follow-up pages unreachable through the patient.
        $this->get(route('medical.patients.show', $patient))->assertForbidden();
        $this->post(route('medical.patients.problems.store', $patient), [
            'label' => 'Stranger entry', 'problem_type' => 'acute',
        ])->assertForbidden();
        $this->patch(route('medical.problems.resolve', $problem), [])->assertForbidden();
        $this->post(route('medical.followups.complete', $followup), [])->assertForbidden();

        $this->assertSame('active', $problem->fresh()->status);
        $this->assertSame('planned', $followup->fresh()->status);
    }

    public function test_unauthorized_actions_rejected(): void
    {
        $patient = $this->createPatient();
        $problem = $this->createProblem($patient);
        $followup = $this->createFollowUp($patient);

        $viewer = $this->roleWith(['medical_patients.view']);
        $this->actingAs($viewer, 'web');
        Workspace::set($this->institute->id);

        $this->post(route('medical.patients.problems.store', $patient), [
            'label' => 'No grant', 'problem_type' => 'acute',
        ])->assertForbidden();
        $this->patch(route('medical.problems.inactivate', $problem), [])->assertForbidden();
        $this->patch(route('medical.problems.resolve', $problem), [])->assertForbidden();
        $this->post(route('medical.patients.followups.store', $patient), [
            'planned_date' => now()->addWeek()->format('Y-m-d'),
            'reason' => 'No grant',
        ])->assertForbidden();
        $this->post(route('medical.followups.complete', $followup), [])->assertForbidden();
        $this->post(route('medical.followups.cancel', $followup), ['reason' => 'x'])->assertForbidden();

        $editor = $this->roleWith([
            'medical_patients.view', 'medical_problems.create', 'medical_problems.edit',
            'medical_followups.create', 'medical_followups.edit',
        ]);
        $this->actingAs($editor, 'web');
        Workspace::set($this->institute->id);
        $this->post(route('medical.patients.problems.store', $patient), [
            'label' => 'Granted entry', 'problem_type' => 'symptom',
        ])->assertSessionHasNoErrors();
        $this->patch(route('medical.problems.inactivate', $problem), [])->assertSessionHasNoErrors();
    }

    // --- Audit ----------------------------------------------------------------------------------------------------

    public function test_problem_audit_trail(): void
    {
        $patient = $this->createPatient();
        $problem = $this->createProblem($patient);
        $this->patch(route('medical.problems.inactivate', $problem), [])->assertSessionHasNoErrors();
        $this->patch(route('medical.problems.resolve', $problem), [
            'reason' => 'Clinically resolved',
        ])->assertSessionHasNoErrors();

        $actions = ClinicalAuditLog::where('auditable_type', PatientProblem::class)
            ->where('auditable_id', $problem->id)
            ->orderBy('id')->pluck('action')->all();
        $this->assertSame(['problem_created', 'problem_status_changed', 'problem_resolved'], $actions);
        $resolve = ClinicalAuditLog::where('auditable_type', PatientProblem::class)
            ->where('auditable_id', $problem->id)->where('action', 'problem_resolved')->firstOrFail();
        $this->assertSame('Clinically resolved', $resolve->reason);
        $this->assertSame($this->institute->id, (int) $resolve->institute_id);
        $this->assertSame($patient->id, (int) $resolve->patient_id);
    }

    public function test_followup_audit_trail(): void
    {
        $patient = $this->createPatient();
        $completing = $this->createFollowUp($patient, ['reason' => 'Audit complete']);
        $cancelling = $this->createFollowUp($patient, ['reason' => 'Audit cancel']);
        $this->post(route('medical.followups.complete', $completing), [])->assertSessionHasNoErrors();
        $this->post(route('medical.followups.cancel', $cancelling), ['reason' => 'Changed plan'])
            ->assertSessionHasNoErrors();

        foreach ([
            [$completing->id, 'followup_completed', null],
            [$cancelling->id, 'followup_cancelled', 'Changed plan'],
        ] as [$id, $action, $reason]) {
            $row = ClinicalAuditLog::where('auditable_type', FollowUp::class)
                ->where('auditable_id', $id)->where('action', $action)->firstOrFail();
            $this->assertSame($reason, $row->reason);
            $this->assertSame($patient->id, (int) $row->patient_id);
        }
        $this->assertTrue(ClinicalAuditLog::where('auditable_type', FollowUp::class)
            ->where('auditable_id', $completing->id)->where('action', 'followup_created')->exists());
    }

    public function test_reads_generate_no_audit_rows(): void
    {
        $patient = $this->createPatient();
        $encounter = $this->openEncounter($patient);
        $this->createProblem($patient);
        $this->createFollowUp($patient);
        $before = ClinicalAuditLog::where('institute_id', $this->institute->id)->count();

        $this->get(route('medical.patients.show', $patient))->assertOk();
        $this->get(route('medical.patients.history', $patient))->assertOk();
        $this->get(route('medical.encounters.show', $encounter))->assertOk();

        $this->assertSame($before, ClinicalAuditLog::where('institute_id', $this->institute->id)->count());
    }

    // --- Integrity ------------------------------------------------------------------------------------------------------

    public function test_source_records_unchanged(): void
    {
        $patient = $this->createPatient();
        $encounter = $this->openEncounter($patient);
        $diagnosis = $this->addDiagnosis($encounter, 'Immutable diagnosis');
        $diagnosisStamp = $diagnosis->updated_at->toDateTimeString();
        $encounterStamp = $encounter->updated_at->toDateTimeString();

        $problem = $this->createProblem($patient, [
            'encounter_id' => $encounter->id,
            'encounter_diagnosis_id' => $diagnosis->id,
        ]);
        $this->patch(route('medical.problems.resolve', $problem), [])->assertSessionHasNoErrors();
        $this->patch(route('medical.problems.unlink', $problem), [])->assertSessionHasNoErrors();

        $diagnosis->refresh();
        $encounter->refresh();
        $this->assertSame('Immutable diagnosis', $diagnosis->label);
        $this->assertSame('active', $diagnosis->status);
        $this->assertSame('open', $encounter->status);
        $this->assertSame($diagnosisStamp, $diagnosis->updated_at->toDateTimeString());
        $this->assertSame($encounterStamp, $encounter->updated_at->toDateTimeString());
    }

    public function test_timeline_includes_problems_and_followups(): void
    {
        $patient = $this->createPatient();
        $problem = $this->createProblem($patient, ['label' => 'Timeline asthma']);
        $followup = $this->createFollowUp($patient, ['reason' => 'Timeline review visit']);

        $response = $this->get(route('medical.patients.history', $patient))->assertOk();
        $response->assertSee('Timeline asthma');
        $response->assertSee('Timeline review visit');

        $resolved = $this->createProblem($patient, ['label' => 'Healed fracture']);
        $this->patch(route('medical.problems.resolve', $resolved), [])->assertSessionHasNoErrors();
        $this->get(route('medical.patients.history', $patient))->assertOk()
            ->assertSee('Problem resolved');
    }

    public function test_timeline_bounds_hold_with_new_types(): void
    {
        $patient = $this->createPatient();
        for ($i = 0; $i < 105; $i++) {
            PatientProblem::create([
                'institute_id' => $this->institute->id,
                'patient_id' => $patient->id,
                'label' => "Cap problem $i",
                'problem_type' => 'other',
            ]);
        }

        $service = app(PatientTimelineService::class);
        $events = $service->collect($patient, [], PatientTimelineService::TYPES, null);
        $problems = array_filter($events, fn ($e) => $e['type'] === 'problem');
        $this->assertLessThanOrEqual(PatientTimelineService::PER_TYPE_LIMIT, count($problems));

        DB::enableQueryLog();
        $service->collect($patient, [], PatientTimelineService::TYPES, null);
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();
        $this->assertLessThanOrEqual(25, $count);
    }

    public function test_existing_safety_intact(): void
    {
        $patient = $this->createPatient();
        $encounter = $this->openEncounter($patient);
        $problem = $this->createProblem($patient);
        $allergic = $this->createPatient('Allergic');
        $allergic->update(['allergies' => 'Problemycin 50mg']);

        $this->post(route('medical.prescriptions.store'), [
            'patient_id' => $allergic->id,
            'doctor_id' => $this->doctor->id,
            'prescription_date' => now()->format('Y-m-d'),
            'encounter_id' => $encounter->id,
            'items' => [[
                'medicine_id' => null,
                'medicine_name' => 'Problemycin 50mg',
                'dosage' => '50mg',
                'frequency' => '1+0+0',
                'quantity' => 5,
            ]],
        ])->assertSessionHas('error');

        // Problem display is context only: no CDS/safety side effects, and
        // the problem record itself is untouched by prescribing.
        $this->assertSame('active', $problem->fresh()->status);
    }

    public function test_problem_list_bounded_and_filtered(): void
    {
        $patient = $this->createPatient();
        $this->createProblem($patient, ['label' => 'Active one', 'problem_type' => 'chronic']);
        $resolved = $this->createProblem($patient, ['label' => 'Gone one', 'problem_type' => 'acute']);
        $this->patch(route('medical.problems.resolve', $resolved), [])->assertSessionHasNoErrors();
        $this->get(route('medical.patients.show', $patient))->assertOk(); // drain flashes

        $response = $this->get(route('medical.patients.show', [$patient, 'problem_status' => 'resolved']))
            ->assertOk();
        $response->assertSee('Gone one');
        $response->assertDontSee('Active one');
    }
}

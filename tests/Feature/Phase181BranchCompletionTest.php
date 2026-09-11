<?php

namespace Tests\Feature;

use App\Livewire\Medical\QueueManager;
use App\Models\Branch;
use App\Models\Institute;
use App\Models\Medical\Appointment;
use App\Models\Medical\Doctor;
use App\Models\Medical\Encounter;
use App\Models\Medical\Patient;
use App\Models\Medical\PharmacyStock;
use App\Models\Medical\Ward;
use App\Models\Membership;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\BranchContext;
use App\Support\Workspace;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Phase 18.1 — branch operational completion.
 *
 * Closes the deferred Phase 18 edges: branch administration, doctor
 * assignment, context derivation, Livewire queue enforcement, branch-safe
 * FEFO dispensing, service-level stock/expiry filtering, forged-input
 * create-form hardening, and deterministic legacy backfill.
 */
class Phase181BranchCompletionTest extends TestCase
{
    use DatabaseTransactions;

    private Institute $instituteA;

    private Institute $instituteB;

    private Branch $a1;

    private Branch $a2;

    private Branch $b1;

    private User $adminA;

    private User $userA1;

    private User $userB;

    private User $doctorA1;

    private User $legacyDoctor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ValidateCsrfToken::class);

        $this->instituteA = $this->makeInstitute('Completion Alpha');
        $this->instituteB = $this->makeInstitute('Completion Beta');

        $this->a1 = $this->makeBranch($this->instituteA, 'Alpha Main');
        $this->a2 = $this->makeBranch($this->instituteA, 'Alpha North');
        $this->b1 = $this->makeBranch($this->instituteB, 'Beta Main');

        $this->adminA = $this->makeOwner($this->instituteA);
        $this->userA1 = $this->makeStaff($this->instituteA, $this->a1->id, $this->branchPerms());
        $this->userB = $this->makeOwner($this->instituteB);
        $this->doctorA1 = $this->makeDoctor($this->instituteA, $this->a1->id, [$this->a1->id]);
        $this->legacyDoctor = $this->makeDoctor($this->instituteA, null, []);

        $this->actingAs($this->adminA, 'web');
        Workspace::set($this->instituteA->id);
    }

    private function branchPerms(): array
    {
        return [
            'medical_patients.view', 'medical_patients.create',
            'medical_appointments.view', 'medical_appointments.create', 'medical_appointments.edit',
            'medical_encounters.view', 'medical_encounters.create', 'medical_encounters.edit',
            'medical_admissions.view', 'medical_admissions.create',
            'medical_lab.view', 'medical_lab.create', 'medical_lab.edit',
            'medical_prescriptions.view', 'medical_prescriptions.create', 'medical_prescriptions.edit',
            'medical_problems.view', 'medical_problems.create',
            'medical_followups.view', 'medical_followups.create', 'medical_followups.edit',
            'medical_billing.view', 'medical_billing.create',
            'medical_pharmacy.view', 'medical_pharmacy.create', 'medical_pharmacy.edit',
            'medical_pharmacy.dispense',
            'medical_wards.view',
            'medical_queue.reorder',
        ];
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

    private function makeStaff(Institute $institute, ?int $branchId, array $perms = []): User
    {
        $user = User::factory()->create(['account_type' => 'staff', 'status' => 'active']);
        $role = Role::create([
            'institute_id' => $institute->id,
            'name' => 'C Role '.uniqid(),
            'slug' => 'c-role-'.uniqid(),
            'status' => 'active',
        ]);
        foreach ($perms as $slug) {
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

    private function makeDoctor(Institute $institute, ?int $branchId, array $assigned): User
    {
        $user = $this->makeStaff($institute, $branchId, $this->branchPerms());
        $doctor = Doctor::create([
            'institute_id' => $institute->id,
            'user_id' => $user->id,
            'registration_number' => 'REG-'.strtoupper(uniqid()),
        ]);
        foreach ($assigned as $branchId) {
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
    }

    private function createPatient(string $first = 'Completion'): Patient
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

    private function branchManager(): User
    {
        return $this->makeStaff($this->instituteA, null, ['medical_branches.view', 'medical_branches.manage']);
    }

    // --- A. Branch management -------------------------------------------------------------------------------

    public function test_admin_can_create_and_edit_branch(): void
    {
        $manager = $this->branchManager();
        $this->actAs($manager, $this->instituteA);

        $this->get(route('medical.branches.index'))->assertOk();
        $this->post(route('medical.branches.store'), [
            'name' => 'Completion East',
            'phone' => '01000000000',
        ])->assertSessionHasNoErrors();
        $branch = Branch::where('institute_id', $this->instituteA->id)->latest('id')->firstOrFail();
        $this->assertSame('active', $branch->status);
        $this->assertNotEmpty($branch->code);

        $this->put(route('medical.branches.update', $branch), [
            'name' => 'Completion East Renamed',
        ])->assertSessionHasNoErrors();
        $this->assertSame('Completion East Renamed', $branch->fresh()->name);
        $this->assertSame($this->instituteA->id, (int) $branch->fresh()->institute_id);

        $this->assertTrue(\App\Models\AuditLog::where('module', 'hms_branches')
            ->where('record_id', $branch->id)->where('action', 'branch_created')->exists());
    }

    public function test_branch_user_cannot_manage_branches(): void
    {
        $this->actAs($this->userA1, $this->instituteA);

        $this->get(route('medical.branches.index'))->assertForbidden();
        $this->post(route('medical.branches.store'), ['name' => 'Rogue'])->assertForbidden();
        $this->put(route('medical.branches.update', $this->a1), ['name' => 'Rogue'])->assertForbidden();
        $this->post(route('medical.branches.toggle-status', $this->a1))->assertForbidden();
    }

    public function test_cross_institute_branch_mutation_rejected(): void
    {
        $manager = $this->branchManager();
        $this->actAs($manager, $this->instituteA);

        // Branch route binding carries the existing TenantScoped institute
        // scope, so foreign branches resolve as missing (404 reveals less
        // than a 403 oracle and matches the alumni-module convention).
        $this->get(route('medical.branches.show', $this->b1))->assertNotFound();
        $this->put(route('medical.branches.update', $this->b1), ['name' => 'Hijack'])->assertNotFound();
        $this->post(route('medical.branches.doctors.assign', $this->b1), [
            'doctor_id' => 1,
        ])->assertNotFound();
        $this->assertSame('Beta Main', substr($this->b1->fresh()->name, 0, 9));
    }

    public function test_deactivation_preserves_clinical_data(): void
    {
        $patient = $this->createPatient();
        $this->post(route('medical.encounters.store'), [
            'patient_id' => $patient->id,
            'doctor_id' => $this->legacyDoctor->id,
            'encounter_type' => 'OPD',
            'branch_id' => $this->a2->id,
        ])->assertSessionHasNoErrors();
        $encounter = Encounter::where('institute_id', $this->instituteA->id)->latest('id')->firstOrFail();

        $manager = $this->branchManager();
        $this->actAs($manager, $this->instituteA);
        $this->post(route('medical.branches.toggle-status', $this->a2))->assertRedirect();
        $this->assertSame('inactive', $this->a2->fresh()->status);

        // Historical record intact with its branch.
        $this->assertSame($this->a2->id, (int) $encounter->fresh()->branch_id);
        $this->actAs($this->adminA, $this->instituteA);
        $this->get(route('medical.encounters.show', $encounter))->assertOk();

        // No NEW clinical records into an inactive branch.
        $this->post(route('medical.encounters.store'), [
            'patient_id' => $patient->id,
            'doctor_id' => $this->legacyDoctor->id,
            'encounter_type' => 'OPD',
            'branch_id' => $this->a2->id,
        ])->assertForbidden();

        // No hard-delete route exists.
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('medical.branches.destroy'));
    }

    // --- B. Doctor assignment ------------------------------------------------------------------------------------------------

    public function test_doctor_assignment_lifecycle(): void
    {
        $manager = $this->branchManager();
        $this->actAs($manager, $this->instituteA);
        $profile = Doctor::where('institute_id', $this->instituteA->id)
            ->where('user_id', $this->legacyDoctor->id)->firstOrFail();

        // Assign same-institute doctor.
        $this->post(route('medical.branches.doctors.assign', $this->a1), [
            'doctor_id' => $profile->id,
        ])->assertSessionHasNoErrors();
        $this->assertTrue(DB::table('doctor_branch')
            ->where('branch_id', $this->a1->id)->where('doctor_id', $profile->id)->exists());

        // Duplicate prevented.
        $this->post(route('medical.branches.doctors.assign', $this->a1), [
            'doctor_id' => $profile->id,
        ])->assertSessionHas('error');

        // Cross-institute doctor rejected.
        $foreignUser = User::factory()->create(['account_type' => 'staff', 'status' => 'active']);
        $foreignDoctor = Doctor::create([
            'institute_id' => $this->instituteB->id,
            'user_id' => $foreignUser->id,
            'registration_number' => 'REG-'.strtoupper(uniqid()),
        ]);
        $this->post(route('medical.branches.doctors.assign', $this->a1), [
            'doctor_id' => $foreignDoctor->id,
        ])->assertSessionHas('error');
        $this->assertFalse(DB::table('doctor_branch')->where('doctor_id', $foreignDoctor->id)->exists());

        // Removal restores legacy institute-wide semantics.
        $this->delete(route('medical.branches.doctors.remove', [$this->a1, $profile->id]))
            ->assertSessionHasNoErrors();
        $this->assertFalse(DB::table('doctor_branch')->where('doctor_id', $profile->id)->exists());

        $this->assertTrue(\App\Models\AuditLog::where('module', 'hms_branches')
            ->where('action', 'doctor_assigned')->exists());
        $this->assertTrue(\App\Models\AuditLog::where('module', 'hms_branches')
            ->where('action', 'doctor_unassigned')->exists());
    }

    public function test_branch_user_cannot_assign_doctors(): void
    {
        $this->actAs($this->userA1, $this->instituteA);
        $profile = Doctor::where('institute_id', $this->instituteA->id)
            ->where('user_id', $this->legacyDoctor->id)->firstOrFail();

        $this->post(route('medical.branches.doctors.assign', $this->a1), [
            'doctor_id' => $profile->id,
        ])->assertForbidden();
    }

    // --- C. Context --------------------------------------------------------------------------------------------------------------------

    public function test_context_derives_from_membership_and_cannot_be_spoofed(): void
    {
        $this->actAs($this->userA1, $this->instituteA);
        $this->assertSame($this->a1->id, BranchContext::id());

        // Request parameters never influence context: timeline/history with
        // a foreign branch hint still resolves to the member's branch.
        $patient = $this->createPatient();
        $this->get(route('medical.patients.history', [$patient, 'branch_id' => $this->a2->id]))->assertOk();
        $this->assertSame($this->a1->id, BranchContext::id());

        // Institute-wide admin stays unconstrained.
        $this->actAs($this->adminA, $this->instituteA);
        $this->assertNull(BranchContext::id());
        $this->assertNull(BranchContext::accessibleBranchIds());
        $this->assertSame([$this->a1->id], array_values((function () {
            $this->actAs($this->userA1, $this->instituteA);

            return BranchContext::accessibleBranchIds() ?? [];
        })()));
    }

    // --- D. Queue --------------------------------------------------------------------------------------------------------------------------

    private function queueFixture(): array
    {
        $patientA = $this->createPatient('QueueA');
        $patientB = $this->createPatient('QueueB');
        $date = now()->format('Y-m-d');
        $mk = function (Patient $p, int $branch) use ($date) {
            $this->post(route('medical.appointments.store'), [
                'patient_id' => $p->id,
                'doctor_id' => $this->legacyDoctor->id,
                'appointment_date' => $date,
                'appointment_time' => '09:00',
                'branch_id' => $branch,
            ])->assertSessionHasNoErrors();

            return Appointment::where('institute_id', $this->instituteA->id)->latest('id')->firstOrFail();
        };
        $a1Appt = $mk($patientA, $this->a1->id);
        $a2Appt = $mk($patientB, $this->a2->id);
        foreach ([$a1Appt, $a2Appt] as $appt) {
            $this->post(route('medical.appointments.checkin', $appt))->assertRedirect();
        }

        return [$date, $a1Appt->fresh(), $a2Appt->fresh()];
    }

    public function test_livewire_queue_branch_enforcement(): void
    {
        [$date, $a1Appt, $a2Appt] = $this->queueFixture();
        $this->actAs($this->userA1, $this->instituteA);

        // Own-branch queue loads; foreign rows excluded.
        $component = Livewire::test(QueueManager::class, [
            'doctorUserId' => $this->legacyDoctor->id,
            'date' => $date,
            'instituteId' => $this->instituteA->id,
        ]);
        $ids = array_column($component->get('items'), 'id');
        $this->assertContains($a1Appt->id, $ids);
        $this->assertNotContains($a2Appt->id, $ids);

        // Direct actions on foreign rows: reorder ignores, mutations 403.
        Livewire::test(QueueManager::class, [
            'doctorUserId' => $this->legacyDoctor->id,
            'date' => $date,
            'instituteId' => $this->instituteA->id,
        ])->call('updateQueueOrder', [$a2Appt->id, $a1Appt->id])
            ->assertOk();
        $this->assertNull($a2Appt->fresh()->queue_order);

        Livewire::test(QueueManager::class, [
            'doctorUserId' => $this->legacyDoctor->id,
            'date' => $date,
            'instituteId' => $this->instituteA->id,
        ])->call('complete', $a2Appt->id)
            ->assertForbidden();
        $this->assertSame('checked_in', $a2Appt->fresh()->status);

        Livewire::test(QueueManager::class, [
            'doctorUserId' => $this->legacyDoctor->id,
            'date' => $date,
            'instituteId' => $this->instituteA->id,
        ])->call('cancel', $a2Appt->id)
            ->assertForbidden();

        // Own-branch actions still work.
        Livewire::test(QueueManager::class, [
            'doctorUserId' => $this->legacyDoctor->id,
            'date' => $date,
            'instituteId' => $this->instituteA->id,
        ])->call('complete', $a1Appt->id)
            ->assertOk();
        $this->assertSame('completed', $a1Appt->fresh()->status);
    }

    public function test_livewire_queue_doctor_filter_and_refresh_scoped(): void
    {
        [$date] = $this->queueFixture();
        $this->actAs($this->userA1, $this->instituteA);

        // Mounting an exclusively-foreign doctor's queue is refused, even
        // though the doctor exists in the institute.
        Livewire::test(QueueManager::class, [
            'doctorUserId' => $this->doctorA1->id,
            'date' => $date,
            'instituteId' => $this->instituteA->id,
        ])->assertOk();
        $onlyA2 = $this->makeDoctor($this->instituteA, $this->a2->id, [$this->a2->id]);
        Livewire::test(QueueManager::class, [
            'doctorUserId' => $onlyA2->id,
            'date' => $date,
            'instituteId' => $this->instituteA->id,
        ])->assertForbidden();

        // Refresh stays scoped.
        $component = Livewire::test(QueueManager::class, [
            'doctorUserId' => $this->legacyDoctor->id,
            'date' => $date,
            'instituteId' => $this->instituteA->id,
        ]);
        $component->call('loadQueue')->assertOk();
        $this->assertNotEmpty($component->get('items'));
    }

    public function test_queue_serials_unchanged_by_branch(): void
    {
        $patient = $this->createPatient();
        $date = now()->addDay()->format('Y-m-d');
        $mk = function () use ($patient, $date) {
            $this->post(route('medical.appointments.store'), [
                'patient_id' => $patient->id,
                'doctor_id' => $this->legacyDoctor->id,
                'appointment_date' => $date,
                'appointment_time' => '09:00',
            ])->assertSessionHasNoErrors();

            return Appointment::where('institute_id', $this->instituteA->id)->latest('id')->firstOrFail();
        };
        $first = $mk();
        $second = $mk();
        $this->assertSame(1, (int) $first->serial_number);
        $this->assertSame(2, (int) $second->serial_number);
    }

    // --- E. Pharmacy FEFO ---------------------------------------------------------------------------------------------------------------------

    private function makeMedicine(): \App\Models\Medical\Medicine
    {
        return \App\Models\Medical\Medicine::create([
            'institute_id' => $this->instituteA->id,
            'code' => 'MED-'.strtoupper(uniqid()),
            'generic_name' => 'Completion Salt',
            'brand_name' => 'Completion Salt 100',
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

    private function makeStock(\App\Models\Medical\Medicine $medicine, ?int $branchId, int $qty, string $expiry): \App\Models\Medical\PharmacyStock
    {
        return \App\Models\Medical\PharmacyStock::create([
            'institute_id' => $this->instituteA->id,
            'branch_id' => $branchId,
            'medicine_id' => $medicine->id,
            'batch_number' => 'BN-'.strtoupper(uniqid()),
            'expiry_date' => $expiry,
            'quantity' => $qty,
            'current_quantity' => $qty,
            'purchase_price' => 5,
        ]);
    }

    private function dispenseSetup(): array
    {
        $medicine = $this->makeMedicine();
        // Two A1 batches: earlier expiry first (FEFO), plus an A2 batch.
        $early = $this->makeStock($medicine, $this->a1->id, 10, now()->addMonth()->format('Y-m-d'));
        $late = $this->makeStock($medicine, $this->a1->id, 10, now()->addMonths(6)->format('Y-m-d'));
        $foreign = $this->makeStock($medicine, $this->a2->id, 10, now()->addDays(10)->format('Y-m-d'));

        $patient = $this->createPatient('Dispense');
        $this->post(route('medical.prescriptions.store'), [
            'patient_id' => $patient->id,
            'doctor_id' => $this->legacyDoctor->id,
            'prescription_date' => now()->format('Y-m-d'),
            'branch_id' => $this->a1->id,
            'items' => [[
                'medicine_id' => $medicine->id,
                'medicine_name' => $medicine->display_name,
                'dosage' => '100mg',
                'frequency' => '1+0+0',
                'quantity' => 10,
            ]],
        ])->assertSessionHasNoErrors();
        $rx = \App\Models\Medical\Prescription::where('institute_id', $this->instituteA->id)->latest('id')->firstOrFail();
        $this->post(route('medical.prescriptions.finalize', $rx))->assertRedirect();
        $item = $rx->items()->firstOrFail();

        return [$medicine, $early, $late, $foreign, $item];
    }

    public function test_branch_cannot_consume_foreign_stock_and_fefo_preserved(): void
    {
        [$medicine, $early, $late, $foreign, $item] = $this->dispenseSetup();
        $this->actAs($this->userA1, $this->instituteA);

        // FEFO within the branch: earliest-expiry batch first.
        $splits = app(\App\Services\Medical\PharmacyStockService::class)
            ->getStockBatches($this->instituteA->id, $medicine->id, 12, $this->a1->id);
        $this->assertSame($early->id, $splits[0]['stock_id']);
        $this->assertSame(10, $splits[0]['quantity']);
        $this->assertSame($late->id, $splits[1]['stock_id']);
        // Foreign batch never selected despite earlier expiry.
        $this->assertNotContains($foreign->id, array_column($splits, 'stock_id'));

        // Single dispense from a foreign batch is refused.
        $this->post(route('medical.pharmacy.dispense', $item->id), [
            'prescription_item_id' => $item->id,
            'stock_id' => $foreign->id,
            'quantity_dispensed' => 10,
        ])->assertSessionHas('error');
        $this->assertSame('pending', $item->fresh()->status);

        // Dispense from the own-branch batch succeeds (full prescribed qty).
        $this->post(route('medical.pharmacy.dispense', $item->id), [
            'prescription_item_id' => $item->id,
            'stock_id' => $early->id,
            'quantity_dispensed' => 10,
        ])->assertSessionHasNoErrors();
        $this->assertSame('dispensed', $item->fresh()->status);
        $this->assertSame(0, (int) $early->fresh()->current_quantity);
        $this->assertSame($this->a1->id, (int) \App\Models\Medical\PharmacyDispense::latest('id')->firstOrFail()->branch_id);
    }

    public function test_concurrent_dispense_safe_and_release_restores(): void
    {
        [$medicine, $early] = array_slice($this->dispenseSetup(), 0, 2);
        $service = app(\App\Services\Medical\PharmacyStockService::class);

        // Two back-to-back dispenses exceeding stock: second fails, no negative.
        $service->deductStock($this->instituteA->id, $early->id, 7);
        try {
            $service->deductStock($this->instituteA->id, $early->id, 7);
            $this->fail('Second over-deduction must fail.');
        } catch (\RuntimeException) {
            $this->assertTrue(true);
        }
        $this->assertSame(3, (int) $early->fresh()->current_quantity);

        // Adjustment (release path) restores availability; negatives refused.
        $service->adjustStock($this->instituteA->id, $early->id, 10, 'Release test');
        $this->assertSame(10, (int) $early->fresh()->current_quantity);
        try {
            $service->adjustStock($this->instituteA->id, $early->id, -1, 'Bad adjust');
            $this->fail('Negative adjust must fail.');
        } catch (\RuntimeException) {
            $this->assertSame(10, (int) $early->fresh()->current_quantity);
        }
    }

    // --- F. Stock alerts ---------------------------------------------------------------------------------------------------------------------------

    public function test_stock_alerts_branch_scoped_at_service(): void
    {
        $medicine = $this->makeMedicine();
        $this->makeStock($medicine, $this->a1->id, 5, now()->addDays(3)->format('Y-m-d'));
        $this->makeStock($medicine, $this->a2->id, 5, now()->addDays(3)->format('Y-m-d'));

        $stockService = app(\App\Services\Medical\PharmacyStockService::class);
        $expiryService = app(\App\Services\Medical\ExpiryAlertService::class);

        $scoped = $stockService->getLowStockItems($this->instituteA->id, $this->a1->id);
        $this->assertSame(5, (int) $scoped->first()->available_stock);
        $wide = $stockService->getLowStockItems($this->instituteA->id, null);
        $this->assertSame(10, (int) $wide->first()->available_stock);

        $alerts = $expiryService->checkAndAlert($this->instituteA->id, $this->a1->id);
        $this->assertCount(1, $alerts['critical']);
        $summary = $expiryService->getAlertSummary($this->instituteA->id, $this->a1->id);
        $this->assertSame(1, $summary['critical_count']);

        // HTTP dashboards inherit the same scoping.
        $this->actAs($this->userA1, $this->instituteA);
        $this->get(route('medical.pharmacy.expiry-alerts'))->assertOk()
            ->assertDontSee($this->a2->name);
    }

    // --- G. Forged create-form inputs ------------------------------------------------------------------------------------------------------------------

    public function test_forged_branch_inputs_fail_everywhere(): void
    {
        $patient = $this->createPatient();
        $forged = ['branch_id' => $this->a2->id];

        $this->actAs($this->userA1, $this->instituteA);

        $this->post(route('medical.appointments.store'), [
            'patient_id' => $patient->id,
            'doctor_id' => $this->legacyDoctor->id,
            'appointment_date' => now()->addDay()->format('Y-m-d'),
            'appointment_time' => '09:00',
        ] + $forged)->assertForbidden();

        $this->post(route('medical.encounters.store'), [
            'patient_id' => $patient->id,
            'doctor_id' => $this->legacyDoctor->id,
            'encounter_type' => 'OPD',
        ] + $forged)->assertForbidden();

        $this->post(route('medical.admissions.store'), [
            'patient_id' => $patient->id,
            'admitting_doctor_id' => $this->legacyDoctor->id,
            'admission_date' => now()->format('Y-m-d'),
            'admission_time' => '10:00',
            'primary_diagnosis' => 'Forged',
        ] + $forged)->assertForbidden();

        $this->post(route('medical.prescriptions.store'), [
            'patient_id' => $patient->id,
            'doctor_id' => $this->legacyDoctor->id,
            'prescription_date' => now()->format('Y-m-d'),
            'items' => [[
                'medicine_id' => null, 'medicine_name' => 'X 1mg',
                'dosage' => '1mg', 'frequency' => '1+0+0', 'quantity' => 1,
            ]],
        ] + $forged)->assertForbidden();

        $this->post(route('medical.patients.followups.store', $patient), [
            'planned_date' => now()->addWeek()->format('Y-m-d'),
            'reason' => 'Forged follow-up',
        ] + $forged)->assertForbidden();

        $this->post(route('medical.pharmacy.stock.store'), [
            'medicine_id' => $this->makeMedicine()->id,
            'batch_number' => 'BN-'.strtoupper(uniqid()),
            'expiry_date' => now()->addYear()->format('Y-m-d'),
            'quantity_received' => 10,
            'current_quantity' => 10,
            'purchase_price' => 5,
            'selling_price' => 8,
        ] + $forged)->assertForbidden();

        foreach (['appointments', 'medical_encounters', 'admissions', 'prescriptions', 'medical_follow_ups'] as $table) {
            $this->assertSame(0, DB::table($table)->where('institute_id', $this->instituteA->id)->where('branch_id', $this->a2->id)->count());
        }
    }

    public function test_lab_and_invoice_forged_inputs_fail(): void
    {
        $patient = $this->createPatient();
        $this->actAs($this->userA1, $this->instituteA);

        $test = \App\Models\Medical\LabTest::create([
            'institute_id' => $this->instituteA->id,
            'code' => 'LT-'.strtoupper(uniqid()),
            'name' => 'Forged Panel',
            'price' => 100,
            'is_active' => true,
        ]);
        $this->post(route('medical.lab.orders.store'), [
            'patient_id' => $patient->id,
            'doctor_id' => $this->legacyDoctor->id,
            'priority' => 'routine',
            'tests' => [['lab_test_id' => $test->id]],
            'branch_id' => $this->a2->id,
        ])->assertForbidden();

        $this->post(route('medical.billing.invoices.store'), [
            'patient_id' => $patient->id,
            'type' => 'opd',
            'branch_id' => $this->a2->id,
            'items' => [[
                'description' => 'Forged',
                'amount' => 100,
                'quantity' => 1,
                'discount' => 0,
            ]],
        ])->assertForbidden();
    }

    // --- H. Legacy NULL + backfill -----------------------------------------------------------------------------------------------------------------------------

    public function test_legacy_null_rows_accessible_and_never_reassigned(): void
    {
        $patient = $this->createPatient();
        $this->actAs($this->adminA, $this->instituteA);
        $this->post(route('medical.encounters.store'), [
            'patient_id' => $patient->id,
            'doctor_id' => $this->legacyDoctor->id,
            'encounter_type' => 'OPD',
        ])->assertSessionHasNoErrors();
        $legacy = Encounter::where('institute_id', $this->instituteA->id)->latest('id')->firstOrFail();
        $this->assertNull($legacy->branch_id);

        $this->actAs($this->userA1, $this->instituteA);
        $this->get(route('medical.encounters.show', $legacy))->assertOk();
    }

    public function test_backfill_command_dry_run_and_deterministic_fill(): void
    {
        $ward = Ward::create([
            'institute_id' => $this->instituteA->id,
            'branch_id' => $this->a1->id,
            'name' => 'Backfill Ward '.uniqid(),
            'type' => 'general',
            'total_beds' => 2,
            'available_beds' => 2,
            'daily_rate' => 100,
            'is_active' => true,
        ]);
        $bed = \App\Models\Medical\Bed::create([
            'institute_id' => $this->instituteA->id,
            'ward_id' => $ward->id,
            'branch_id' => null,
            'bed_number' => 'BB-'.uniqid(),
            'status' => 'available',
        ]);

        $this->artisan('medical:backfill-branch')->assertSuccessful();
        $this->assertNull($bed->fresh()->branch_id); // dry-run writes nothing

        $this->artisan('medical:backfill-branch', ['--execute' => true])->assertSuccessful();
        $this->assertSame($this->a1->id, (int) $bed->fresh()->branch_id);
    }

    // --- Matrix ----------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_branch_matrix_for_clinical_transactions(): void
    {
        $patient = $this->createPatient();
        $this->post(route('medical.encounters.store'), [
            'patient_id' => $patient->id,
            'doctor_id' => $this->legacyDoctor->id,
            'encounter_type' => 'OPD',
            'branch_id' => $this->a1->id,
        ])->assertSessionHasNoErrors();
        $encounter = Encounter::where('institute_id', $this->instituteA->id)->latest('id')->firstOrFail();

        // A2 user: denied. B1 user: denied. Admin: allowed.
        $a2user = $this->makeStaff($this->instituteA, $this->a2->id, $this->branchPerms());
        $this->actAs($a2user, $this->instituteA);
        $this->get(route('medical.encounters.show', $encounter))->assertForbidden();

        $b1user = $this->makeStaff($this->instituteB, $this->b1->id, []);
        $this->actAs($b1user, $this->instituteB);
        $this->get(route('medical.encounters.show', $encounter))->assertForbidden();

        $this->actAs($this->adminA, $this->instituteA);
        $this->get(route('medical.encounters.show', $encounter))->assertOk();
    }
}

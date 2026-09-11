<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Institute;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Phase 1 — HMS Patient & OPD permissions.
 *
 * Standalone seeder (the repo has no PermissionSeeder to extend, and
 * DatabaseSeeder is left untouched). Run explicitly:
 *
 *   php artisan db:seed --class=MedicalPermissionSeeder
 *
 * Adapted to this codebase's permission convention:
 *   - table columns are (module, name, slug) — there is no guard_name column
 *   - slugs are `module.action` (e.g. medical_patients.view), matching
 *     CheckPermission usage like `permission:students.view`
 *   - institute owners bypass permission checks, so existing owners keep
 *     working; grant these to staff roles via the role_permissions matrix
 */
class MedicalPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $medicalPermissions = [
            'medical_patients' => [
                'view' => 'View Patients',
                'create' => 'Register Patients',
                'edit' => 'Edit Patients',
                'delete' => 'Delete Patients',
            ],
            'medical_appointments' => [
                'view' => 'View Appointments',
                'create' => 'Book Appointments',
                'edit' => 'Manage Appointment Queue',
                'delete' => 'Cancel Appointments',
            ],
            // Seeded now so Phase 2+ (IPD, Pharmacy, Lab, Billing) can rely
            // on the slugs existing; no Phase 1 code references them yet.
            'medical_admissions' => [
                'view' => 'View Admissions',
                'create' => 'Admit Patients',
                'edit' => 'Edit Admissions',
                'delete' => 'Delete Admissions',
            ],
            'medical_beds' => [
                'view' => 'View Beds',
                'create' => 'Create Beds',
                'edit' => 'Edit Beds',
                'delete' => 'Delete Beds',
            ],
            'medical_wards' => [
                'view' => 'View Wards',
                'create' => 'Create Wards',
                'edit' => 'Edit Wards',
                'delete' => 'Delete Wards',
            ],
            'medical_prescriptions' => [
                'view' => 'View Prescriptions',
                'create' => 'Create Prescriptions',
                'edit' => 'Edit Prescriptions',
                'delete' => 'Delete Prescriptions',
            ],
            'medical_encounters' => [
                'view' => 'View Encounters',
                'create' => 'Open Encounters',
                'edit' => 'Document Encounters',
                'complete' => 'Complete Encounters',
                'amend' => 'Amend Completed Encounters',
            ],
            'medical_pharmacy' => [
                'view' => 'View Pharmacy',
                'create' => 'Add Pharmacy Stock',
                'edit' => 'Edit Pharmacy',
                'delete' => 'Delete Pharmacy Records',
                'dispense' => 'Dispense Medicines',
            ],
            'medical_lab' => [
                'view' => 'View Lab',
                'create' => 'Create Lab Orders',
                'edit' => 'Enter Lab Results',
                'delete' => 'Delete Lab Records',
            ],
            'medical_billing' => [
                'view' => 'View Billing',
                'create' => 'Create Invoices',
                'edit' => 'Edit Invoices',
                'delete' => 'Delete Invoices',
            ],
            'medical_tpa' => [
                'view' => 'View TPA Claims',
                'create' => 'Create TPA Claims',
                'edit' => 'Edit TPA Claims',
                'delete' => 'Delete TPA Claims',
                'approve' => 'Approve TPA Claims',
            ],
            'medical_reports' => [
                'view' => 'View Medical Reports',
            ],
        ];

        foreach ($medicalPermissions as $module => $actions) {
            foreach ($actions as $action => $label) {
                Permission::firstOrCreate(
                    ['slug' => $module.'.'.$action],
                    ['module' => $module, 'name' => $label]
                );
            }
        }

        // Phase 15 — Diagnosis extras (additive): structured encounter
        // diagnoses. firstOrCreate by slug keeps this idempotent, and all
        // earlier slugs are left untouched. No generic order permissions:
        // orders reuse the existing lab/prescription grants.
        $diagnosisExtras = [
            'medical_diagnoses' => [
                'view' => 'View Diagnoses',
                'create' => 'Record Diagnoses',
                'remove' => 'Remove Diagnoses',
            ],
        ];

        foreach ($diagnosisExtras as $module => $actions) {
            foreach ($actions as $action => $label) {
                Permission::firstOrCreate(
                    ['slug' => $module.'.'.$action],
                    ['module' => $module, 'name' => $label]
                );
            }
        }

        // Phase 17 — Problem + follow-up extras (additive): the minimum
        // grants covering the longitudinal workflow (view/create/edit per
        // module; lifecycle actions reuse edit). Idempotent firstOrCreate.
        // Phase 18.1 — branch administration (admin-only via templates;
        // owners bypass through the standard permission check).
        $branchExtras = [
            'medical_branches' => [
                'view' => 'View Branches',
                'manage' => 'Manage Branches',
            ],
        ];

        foreach ($branchExtras as $module => $actions) {
            foreach ($actions as $action => $label) {
                Permission::firstOrCreate(
                    ['slug' => $module.'.'.$action],
                    ['module' => $module, 'name' => $label]
                );
            }
        }

        $phase17Extras = [
            'medical_problems' => [
                'view' => 'View Problems',
                'create' => 'Record Problems',
                'edit' => 'Manage Problems',
            ],
            'medical_followups' => [
                'view' => 'View Follow-ups',
                'create' => 'Plan Follow-ups',
                'edit' => 'Manage Follow-ups',
            ],
        ];

        foreach ($phase17Extras as $module => $actions) {
            foreach ($actions as $action => $label) {
                Permission::firstOrCreate(
                    ['slug' => $module.'.'.$action],
                    ['module' => $module, 'name' => $label]
                );
            }
        }

        // Phase 2 — IPD extras (additive): granular actions the Phase 1 map
        // did not include. firstOrCreate by slug keeps this idempotent, and
        // existing Phase 1 slugs are left untouched.
        $ipdExtras = [
            'medical_beds' => ['allocate' => 'Allocate Beds'],
            'medical_admissions' => ['discharge' => 'Discharge Patients'],
            'medical_vitals' => [
                'view' => 'View Vitals',
                'create' => 'Record Vitals & Notes',
                'update' => 'Edit Vitals',
                'delete' => 'Delete Vitals',
            ],
        ];

        foreach ($ipdExtras as $module => $actions) {
            foreach ($actions as $action => $label) {
                Permission::firstOrCreate(
                    ['slug' => $module.'.'.$action],
                    ['module' => $module, 'name' => $label]
                );
            }
        }

        // Phase 3 — Pharmacy extras (additive). medical_pharmacy.* and
        // medical_prescriptions.* were already seeded in Phase 1; only the
        // medicine catalog slugs are new here.
        $pharmacyExtras = [
            'medical_medicines' => [
                'view' => 'View Medicines',
                'create' => 'Add Medicines',
                'edit' => 'Edit Medicines',
                'delete' => 'Delete Medicines',
            ],
        ];

        foreach ($pharmacyExtras as $module => $actions) {
            foreach ($actions as $action => $label) {
                Permission::firstOrCreate(
                    ['slug' => $module.'.'.$action],
                    ['module' => $module, 'name' => $label]
                );
            }
        }

        // Phase 4 — Lab/Billing extras (additive). medical_lab.*,
        // medical_billing view/create/edit/delete, medical_tpa
        // view/create/edit/approve and medical_reports.view were all seeded
        // in Phase 1; only the process/settle actions are new here. Slugs
        // stay `module.action` (the draft's `action-module` form is not used).
        $phase4Extras = [
            'medical_billing' => ['process' => 'Process Payments'],
            'medical_tpa' => ['settle' => 'Settle Claims'],
        ];

        foreach ($phase4Extras as $module => $actions) {
            foreach ($actions as $action => $label) {
                Permission::firstOrCreate(
                    ['slug' => $module.'.'.$action],
                    ['module' => $module, 'name' => $label]
                );
            }
        }

        // Doctor management (additive). Department → Specialty → Doctor with
        // weekly availability; slugs stay `module.action` like the rest.
        $doctorExtras = [
            'medical_doctors' => [
                'view' => 'View Doctors',
                'create' => 'Add Doctors',
                'edit' => 'Edit Doctors',
                'delete' => 'Delete Doctors',
            ],
        ];

        foreach ($doctorExtras as $module => $actions) {
            foreach ($actions as $action => $label) {
                Permission::firstOrCreate(
                    ['slug' => $module.'.'.$action],
                    ['module' => $module, 'name' => $label]
                );
            }
        }

        // Queue reordering (additive). Granted to existing doctor and
        // receptionist roles; institute owners bypass permission checks.
        $queuePermission = Permission::firstOrCreate(
            ['slug' => 'medical_queue.reorder'],
            ['module' => 'medical_queue', 'name' => 'Reorder Patient Queue']
        );

        $queueRoleIds = Role::whereIn('slug', ['doctor', 'receptionist'])
            ->pluck('id')
            ->toArray();

        if (! empty($queueRoleIds)) {
            $existing = DB::table('role_permissions')
                ->where('permission_id', $queuePermission->id)
                ->whereIn('role_id', $queueRoleIds)
                ->pluck('role_id')
                ->toArray();

            foreach (array_diff($queueRoleIds, $existing) as $roleId) {
                DB::table('role_permissions')->insert([
                    'role_id' => $roleId,
                    'permission_id' => $queuePermission->id,
                ]);
            }
        }

        $this->command->info('Medical permissions seeded successfully!');

        // Vitals editing (additive). Granted to existing doctor and nurse
        // roles; institute owners bypass permission checks.
        $vitalsSlugs = [
            'medical_vitals.view' => 'View Vitals',
            'medical_vitals.create' => 'Record Vitals & Notes',
            'medical_vitals.update' => 'Edit Vitals',
            'medical_vitals.delete' => 'Delete Vitals',
        ];
        $vitalsPermIds = [];
        foreach ($vitalsSlugs as $slug => $label) {
            $vitalsPermIds[] = Permission::firstOrCreate(
                ['slug' => $slug],
                ['module' => 'medical_vitals', 'name' => $label]
            )->id;
        }

        $vitalsRoleIds = Role::whereIn('slug', ['doctor', 'nurse'])
            ->pluck('id')
            ->toArray();

        if (! empty($vitalsRoleIds)) {
            $existing = DB::table('role_permissions')
                ->whereIn('permission_id', $vitalsPermIds)
                ->whereIn('role_id', $vitalsRoleIds)
                ->get(['role_id', 'permission_id']);

            $have = [];
            foreach ($existing as $row) {
                $have[$row->role_id.'-'.$row->permission_id] = true;
            }
            foreach ($vitalsRoleIds as $roleId) {
                foreach ($vitalsPermIds as $permId) {
                    if (! isset($have[$roleId.'-'.$permId])) {
                        DB::table('role_permissions')->insert([
                            'role_id' => $roleId,
                            'permission_id' => $permId,
                        ]);
                    }
                }
            }
        }

        // Create diagnostic-staff role for diagnostic center institutes
        $diagnosticInstitutes = Institute::where('industry', 'healthcare')
            ->where('sub_industry', 'diagnostic_center')
            ->get();

        foreach ($diagnosticInstitutes as $institute) {
            $role = Role::firstOrCreate(
                ['institute_id' => $institute->id, 'slug' => 'diagnostic-staff'],
                [
                    'name' => 'Diagnostic Staff',
                    'is_system' => false,
                    'status' => 'active',
                ]
            );

            $labPermissions = Permission::whereIn('module', ['medical_lab', 'medical_reports'])
                ->pluck('id')
                ->toArray();

            if (! empty($labPermissions)) {
                $existing = DB::table('role_permissions')
                    ->where('role_id', $role->id)
                    ->whereIn('permission_id', $labPermissions)
                    ->pluck('permission_id')
                    ->toArray();

                foreach (array_diff($labPermissions, $existing) as $permId) {
                    DB::table('role_permissions')->insert([
                        'role_id' => $role->id,
                        'permission_id' => $permId,
                    ]);
                }
            }
        }

        if ($diagnosticInstitutes->isNotEmpty()) {
            $this->command->info("Diagnostic staff role created for {$diagnosticInstitutes->count()} diagnostic center(s).");
        }
    }
}

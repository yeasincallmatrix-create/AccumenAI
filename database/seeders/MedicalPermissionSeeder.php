<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;

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

        // Phase 2 — IPD extras (additive): granular actions the Phase 1 map
        // did not include. firstOrCreate by slug keeps this idempotent, and
        // existing Phase 1 slugs are left untouched.
        $ipdExtras = [
            'medical_beds' => ['allocate' => 'Allocate Beds'],
            'medical_admissions' => ['discharge' => 'Discharge Patients'],
            'medical_vitals' => [
                'view' => 'View Vitals',
                'create' => 'Record Vitals & Notes',
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

        $this->command->info('Medical permissions seeded successfully!');
    }
}

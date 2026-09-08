<?php

namespace App\Services;

use App\Models\Institute;
use App\Models\InstituteModuleOverride;
use App\Models\ModuleRegistry;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Activates the medical module for healthcare tenants.
 *
 * Called automatically when an institute is created or its industry changes
 * to 'healthcare'. Ensures:
 *   1. Medical module exists in module_registry
 *   2. Medical permissions are seeded (idempotent)
 *   3. Permissions are assigned to the institute's admin/owner role
 *
 * Module access is controlled via InstituteModuleOverride (created here
 * and by syncIndustryModule), not via package_modules (which is shared
 * across all tenants on the same package).
 */
class MedicalModuleActivator
{
    /**
     * Activate medical module for a healthcare institute.
     * No-op for non-healthcare industries.
     */
    public function activateForHealthcare(Institute $institute): void
    {
        if (($institute->industry ?? '') !== 'healthcare') {
            return;
        }

        $this->ensureModuleRegistered();

        InstituteModuleOverride::updateOrCreate(
            ['institute_id' => $institute->id, 'module_key' => 'medical'],
            ['enabled' => true]
        );

        $this->seedPermissions();
        $this->assignToAdminRole($institute);

        try {
            app(\App\Services\ModuleAccessService::class)->flushCache($institute->id);
        } catch (\Throwable $e) {
            Log::warning('MedicalModuleActivator: cache flush failed', [
                'institute_id' => $institute->id,
                'error' => $e->getMessage(),
            ]);
        }

        Log::info('Medical module activated for tenant', [
            'tenant_id' => $institute->id,
            'identifier' => $institute->identifier,
        ]);
    }

    /**
     * Ensure the medical module exists in module_registry.
     */
    private function ensureModuleRegistered(): void
    {
        ModuleRegistry::updateOrCreate(
            ['key' => 'medical'],
            [
                'name' => 'Medical / Hospital Management',
                'type' => 'industry',
                'description' => 'Complete Hospital Management System (OPD, IPD, Pharmacy, Lab, Billing)',
                'status' => 'active',
                'sort_order' => 50,
            ]
        );
    }

    /**
     * Seed all medical permissions (idempotent — uses firstOrCreate).
     */
    private function seedPermissions(): void
    {
        $medicalPermissions = [
            'medical_patients' => [
                'view' => 'View Patients', 'create' => 'Register Patients',
                'edit' => 'Edit Patients', 'delete' => 'Delete Patients',
            ],
            'medical_appointments' => [
                'view' => 'View Appointments', 'create' => 'Book Appointments',
                'edit' => 'Manage Appointment Queue', 'delete' => 'Cancel Appointments',
            ],
            'medical_admissions' => [
                'view' => 'View Admissions', 'create' => 'Admit Patients',
                'edit' => 'Edit Admissions', 'delete' => 'Delete Admissions',
                'discharge' => 'Discharge Patients',
            ],
            'medical_beds' => [
                'view' => 'View Beds', 'create' => 'Create Beds',
                'edit' => 'Edit Beds', 'delete' => 'Delete Beds',
                'allocate' => 'Allocate Beds',
            ],
            'medical_wards' => [
                'view' => 'View Wards', 'create' => 'Create Wards',
                'edit' => 'Edit Wards', 'delete' => 'Delete Wards',
            ],
            'medical_prescriptions' => [
                'view' => 'View Prescriptions', 'create' => 'Create Prescriptions',
                'edit' => 'Edit Prescriptions', 'delete' => 'Delete Prescriptions',
            ],
            'medical_pharmacy' => [
                'view' => 'View Pharmacy', 'create' => 'Add Pharmacy Stock',
                'edit' => 'Edit Pharmacy', 'delete' => 'Delete Pharmacy Records',
                'dispense' => 'Dispense Medicines',
            ],
            'medical_lab' => [
                'view' => 'View Lab', 'create' => 'Create Lab Orders',
                'edit' => 'Enter Lab Results', 'delete' => 'Delete Lab Records',
            ],
            'medical_billing' => [
                'view' => 'View Billing', 'create' => 'Create Invoices',
                'edit' => 'Edit Invoices', 'delete' => 'Delete Invoices',
                'process' => 'Process Payments',
            ],
            'medical_tpa' => [
                'view' => 'View TPA Claims', 'create' => 'Create TPA Claims',
                'edit' => 'Edit TPA Claims', 'delete' => 'Delete TPA Claims',
                'approve' => 'Approve TPA Claims', 'settle' => 'Settle Claims',
            ],
            'medical_reports' => ['view' => 'View Medical Reports'],
            'medical_vitals' => [
                'view' => 'View Vitals', 'create' => 'Record Vitals & Notes',
                'delete' => 'Delete Vitals',
            ],
            'medical_medicines' => [
                'view' => 'View Medicines', 'create' => 'Add Medicines',
                'edit' => 'Edit Medicines', 'delete' => 'Delete Medicines',
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
    }

    /**
     * Assign all medical permissions to the institute's admin/owner role.
     */
    private function assignToAdminRole(Institute $institute): void
    {
        $adminRole = Role::where('institute_id', $institute->id)
            ->whereIn('slug', ['institute-owner', 'admin'])
            ->first();

        if (! $adminRole) {
            Log::warning('MedicalModuleActivator: no admin role found for institute', [
                'institute_id' => $institute->id,
            ]);
            return;
        }

        $medicalPermissions = Permission::where('module', 'like', 'medical_%')->pluck('id')->toArray();

        if (! empty($medicalPermissions)) {
            $existing = DB::table('role_permissions')
                ->where('role_id', $adminRole->id)
                ->whereIn('permission_id', $medicalPermissions)
                ->pluck('permission_id')
                ->toArray();

            $toAttach = array_diff($medicalPermissions, $existing);

            foreach ($toAttach as $permId) {
                DB::table('role_permissions')->insert([
                    'role_id' => $adminRole->id,
                    'permission_id' => $permId,
                ]);
            }
        }
    }
}

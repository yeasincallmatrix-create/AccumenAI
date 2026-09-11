<?php

namespace App\Services;

use App\Models\Institute;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Support\Collection;

/**
 * Industry-aware default staff roles.
 *
 * Seeds per-institute roles (hospital-admin, doctor, nurse, ...) with the
 * permission bundles each industry needs, so /staff/invite offers meaningful
 * choices from day one. Idempotent: roles are firstOrCreate'd by
 * (institute_id, slug) and permissions attached with syncWithoutDetaching
 * (manual grants are never wiped).
 *
 * Reuse hook for the institute-creation flow:
 *
 *   app(RoleTemplateService::class)->seedForInstitute($institute);
 */
class RoleTemplateService
{
    /**
     * Role templates keyed by canonical industry.
     *
     * Only slugs that exist in the permissions table take effect — unknown
     * slugs are skipped with a report entry (see expandPermissions()).
     * 'medical_*' is a wildcard expanding to every existing medical_* slug.
     */
    public function templates(): array
    {
        return [
            'healthcare' => [
                'hospital-admin' => [
                    'name' => 'Hospital Administrator',
                    'permissions' => ['medical_*', 'staff.manage', 'roles.manage'],
                ],
                'doctor' => [
                    'name' => 'Doctor',
                    'permissions' => [
                        'medical_patients.view', 'medical_patients.create', 'medical_patients.edit',
                        'medical_appointments.view', 'medical_appointments.create', 'medical_appointments.edit',
                        'medical_queue.reorder',
                        'medical_prescriptions.view', 'medical_prescriptions.create', 'medical_prescriptions.edit',
                        'medical_encounters.view', 'medical_encounters.create', 'medical_encounters.edit',
                        'medical_encounters.complete', 'medical_encounters.amend',
                        'medical_diagnoses.view', 'medical_diagnoses.create', 'medical_diagnoses.remove',
                        'medical_problems.view', 'medical_problems.create', 'medical_problems.edit',
                        'medical_followups.view', 'medical_followups.create', 'medical_followups.edit',
                        'medical_lab.view', 'medical_lab.create', 'medical_lab.edit',
                        'medical_doctors.view',
                        'medical_billing.view',
                    ],
                ],
                'nurse' => [
                    'name' => 'Nurse',
                    'permissions' => [
                        'medical_vitals.view', 'medical_vitals.create', 'medical_vitals.delete',
                        'medical_patients.view',
                        'medical_appointments.view',
                        'medical_admissions.view',
                        'medical_doctors.view',
                    ],
                ],
                'pharmacist' => [
                    'name' => 'Pharmacist',
                    'permissions' => [
                        'medical_pharmacy.view', 'medical_pharmacy.create', 'medical_pharmacy.edit', 'medical_pharmacy.dispense',
                        'medical_medicines.view', 'medical_medicines.create', 'medical_medicines.edit',
                        'medical_prescriptions.view',
                    ],
                ],
                'lab-technician' => [
                    'name' => 'Lab Technician',
                    'permissions' => [
                        'medical_lab.view', 'medical_lab.create', 'medical_lab.edit',
                        'medical_reports.view',
                    ],
                ],
                'receptionist' => [
                    'name' => 'Receptionist',
                    'permissions' => [
                        'medical_patients.view', 'medical_patients.create', 'medical_patients.edit',
                        'medical_appointments.view', 'medical_appointments.create', 'medical_appointments.edit',
                        'medical_queue.reorder',
                        'medical_doctors.view',
                    ],
                ],
                'billing-officer' => [
                    'name' => 'Billing Officer',
                    'permissions' => [
                        'medical_billing.view', 'medical_billing.create', 'medical_billing.edit', 'medical_billing.process',
                        'medical_tpa.view',
                    ],
                ],
                'records-officer' => [
                    'name' => 'Medical Records Officer',
                    'permissions' => [
                        'medical_patients.view',
                        'medical_reports.view',
                        'medical_doctors.view',
                    ],
                ],
            ],
            // Industries without dedicated permission modules yet get minimal
            // placeholder roles so the invite dropdown is never empty; bundles
            // grow once those modules ship their permissions. Unknown slugs
            // (e.g. dashboard.view) are skipped with a report entry.
            'training_center' => [
                'center-admin' => ['name' => 'Training Admin', 'permissions' => ['staff.manage', 'roles.manage']],
                'trainer' => ['name' => 'Trainer', 'permissions' => []],
                'center-accountant' => ['name' => 'Accountant', 'permissions' => []],
                'coordinator' => ['name' => 'Coordinator', 'permissions' => []],
            ],
            'education' => [
                'school-admin' => ['name' => 'School Admin', 'permissions' => ['roles.manage', 'dashboard.view', 'settings.view']],
                'teacher' => ['name' => 'Teacher', 'permissions' => ['dashboard.view']],
                'edu-accountant' => ['name' => 'Accountant', 'permissions' => ['dashboard.view']],
            ],
            'hotel' => [
                'hotel-manager' => ['name' => 'Manager', 'permissions' => ['roles.manage', 'dashboard.view', 'settings.view']],
                'hotel-staff' => ['name' => 'Staff', 'permissions' => ['dashboard.view']],
            ],
            'restaurant' => [
                'restaurant-manager' => ['name' => 'Manager', 'permissions' => ['roles.manage', 'dashboard.view', 'settings.view']],
                'restaurant-staff' => ['name' => 'Staff', 'permissions' => ['dashboard.view']],
            ],
            'retail' => [
                'retail-manager' => ['name' => 'Manager', 'permissions' => ['roles.manage', 'dashboard.view', 'settings.view']],
                'retail-staff' => ['name' => 'Staff', 'permissions' => ['dashboard.view']],
            ],
            'real_estate' => [
                'estate-manager' => ['name' => 'Manager', 'permissions' => ['roles.manage', 'dashboard.view', 'settings.view']],
                'estate-staff' => ['name' => 'Staff', 'permissions' => ['dashboard.view']],
            ],
            'manufacturing' => [
                'factory-manager' => ['name' => 'Manager', 'permissions' => ['roles.manage', 'dashboard.view', 'settings.view']],
                'factory-staff' => ['name' => 'Staff', 'permissions' => ['dashboard.view']],
            ],
            'service' => [
                'service-manager' => ['name' => 'Manager', 'permissions' => ['roles.manage', 'dashboard.view', 'settings.view']],
                'service-staff' => ['name' => 'Staff', 'permissions' => ['dashboard.view']],
            ],
            '_default' => [
                'manager' => ['name' => 'Manager', 'permissions' => ['staff.manage', 'roles.manage']],
                'staff' => ['name' => 'Staff', 'permissions' => []],
            ],
        ];
    }

    /**
     * Seed template roles for one institute.
     *
     * @return array{roles_created: int, roles_existing: int, permissions_attached: int, skipped_slugs: string[]}
     */
    public function seedForInstitute(Institute $institute): array
    {
        $summary = ['roles_created' => 0, 'roles_existing' => 0, 'permissions_attached' => 0, 'skipped_slugs' => []];

        $templates = $this->templates();
        $industry = strtolower(trim((string) ($institute->industry ?? '')));
        $roles = $templates[$industry] ?? $templates['_default'];

        foreach ($roles as $slug => $data) {
            $role = Role::firstOrCreate(
                ['institute_id' => $institute->id, 'slug' => $slug],
                ['name' => $data['name'], 'status' => 'active']
            );

            if ($role->wasRecentlyCreated) {
                $summary['roles_created']++;
            } else {
                $summary['roles_existing']++;
            }

            [$permissions, $skipped] = $this->expandPermissions($data['permissions'] ?? []);
            $summary['skipped_slugs'] = array_values(array_unique(array_merge($summary['skipped_slugs'], $skipped)));

            if ($permissions->isNotEmpty()) {
                $before = $role->permissions()->count();
                $role->permissions()->syncWithoutDetaching($permissions->pluck('id')->all());
                $summary['permissions_attached'] += $role->permissions()->count() - $before;
            }
        }

        return $summary;
    }

    /**
     * Resolve template slugs to existing Permission models.
     *
     * Supports the 'medical_*' wildcard (all slugs in medical_* modules).
     *
     * @return array{0: Collection, 1: string[]}
     */
    public function expandPermissions(array $slugs): array
    {
        $expanded = [];
        foreach ($slugs as $slug) {
            if (str_ends_with($slug, '_*')) {
                $prefix = substr($slug, 0, -1);
                $expanded = array_merge($expanded, Permission::where('module', 'LIKE', $prefix . '%')->pluck('slug')->all());
            } else {
                $expanded[] = $slug;
            }
        }
        $expanded = array_values(array_unique($expanded));

        $found = Permission::whereIn('slug', $expanded)->pluck('slug')->all();
        $permissions = Permission::whereIn('slug', $found)->get();

        return [$permissions, array_values(array_diff($expanded, $found))];
    }
}

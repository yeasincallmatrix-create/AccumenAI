<?php

namespace Database\Seeders;

use App\Models\Institute;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * Specialized functional roles for every Medical sub-module.
 *
 * Design notes (verified against the live schema — see audit):
 * - roles table columns are (institute_id, name, slug, is_system,
 *   status); there is NO display_name/guard_name/description column.
 * - Role identity is (institute_id, slug); slugs use hyphens per repo
 *   convention (lab-technician, billing-officer, records-officer).
 * - Permissions attach by SLUG through the role_permissions pivot.
 * - Only permission slugs that EXIST in the permissions table are
 *   attached; unknown slugs are reported, never created.
 * - Idempotent and additive: firstOrCreate + syncWithoutDetaching, so
 *   re-runs never wipe manual grants and never touch existing roles'
 *   current grants (except the two documented additive extensions).
 * - Seeded per healthcare institute (roles are tenant-scoped). Future
 *   institutes receive the same bundles via RoleTemplateService.
 *
 * Run explicitly:
 *
 *   php artisan db:seed --class=MedicalRoleSeeder
 */
class MedicalRoleSeeder extends Seeder
{
    /**
     * Department grouping for the staff-invite role dropdown.
     * Every new role slug appears in exactly one group.
     *
     * @return array<string, string[]>
     */
    public static function groups(): array
    {
        return [
            'Management' => ['institute-owner', 'hospital-admin'],
            'OPD' => ['doctor', 'senior-consultant', 'medical-officer', 'intern-doctor', 'receptionist'],
            'IPD / Nursing' => ['nurse', 'head-nurse', 'ward-nurse', 'ward-boy', 'patient-attendant'],
            'Pharmacy' => ['pharmacist', 'pharmacy-assistant', 'store-manager'],
            'Laboratory' => ['lab-technician', 'pathologist', 'phlebotomist'],
            'Billing' => ['billing-officer', 'cashier', 'billing-clerk', 'insurance-coordinator'],
            'Emergency' => ['er-doctor', 'triage-nurse', 'paramedic', 'er-attendant'],
            'Radiology' => ['radiologist', 'radiology-technician', 'sonographer'],
            'Blood Bank' => ['blood-bank-technician', 'phlebotomist'],
            'Physiotherapy' => ['physiotherapist', 'physio-assistant'],
            'Dental' => ['dentist', 'dental-assistant', 'dental-hygienist'],
            'Vaccination' => ['vaccinator', 'cold-chain-manager'],
            'Medical Records' => ['records-officer', 'medical-records-officer', 'health-information-manager'],
            'Diet & Nutrition' => ['dietitian', 'nutritionist', 'kitchen-staff'],
            'Ambulance' => ['ambulance-driver', 'ambulance-paramedic', 'ambulance-dispatcher'],
        ];
    }

    /**
     * New role definitions: slug => [name, permissions].
     * All permission slugs verified to exist in the permissions table.
     *
     * @return array<string, array{name: string, permissions: string[]}>
     */
    public static function roles(): array
    {
        $opdView = [
            'medical_patients.view', 'medical.patients.view',
            'medical_appointments.view', 'medical.opd.appointments.view',
            'medical_prescriptions.view', 'medical.opd.prescriptions.view',
            'medical_encounters.view', 'medical.opd.encounters.view',
            'medical_diagnoses.view', 'medical.opd.diagnoses.view',
            'medical_vitals.view', 'medical.opd.vitals.view',
            'medical_doctors.view', 'medical.doctors.view',
        ];
        $opdCreate = [
            'medical_patients.create', 'medical.patients.create',
            'medical_appointments.create', 'medical.opd.appointments.create',
            'medical_prescriptions.create', 'medical.opd.prescriptions.create',
            'medical_encounters.create', 'medical.opd.encounters.create',
        ];
        $ipdView = [
            'medical_admissions.view', 'medical.ipd.admissions.view',
            'medical_beds.view', 'medical.ipd.beds.view',
            'medical_wards.view', 'medical.ipd.wards.view',
        ];
        $emergencyView = ['medical_emergency.view', 'medical.emergency.visits.view'];
        $recordsView = [
            'medical.records.view', 'medical.records.timeline.view',
            'medical_patients.view', 'medical.patients.view',
        ];

        return [
            // === OPD & General ===
            'senior-consultant' => [
                'name' => 'Senior Consultant',
                'permissions' => array_merge($opdView, $opdCreate, [
                    'medical_patients.edit', 'medical.patients.edit',
                    'medical_prescriptions.edit', 'medical.opd.prescriptions.edit',
                    'medical_prescriptions.amend', 'medical.opd.prescriptions.amend',
                    'medical_encounters.edit', 'medical.opd.encounters.edit',
                    'medical_encounters.complete', 'medical.opd.encounters.complete',
                    'medical_encounters.amend', 'medical.opd.encounters.amend',
                    'medical_diagnoses.create', 'medical.opd.diagnoses.create',
                    'medical_problems.view', 'medical.opd.problems.view',
                    'medical_problems.create', 'medical.opd.problems.create',
                    'medical_followups.view', 'medical.opd.followups.view',
                    'medical_queue.reorder', 'medical.opd.queue.reorder',
                    'medical_admissions.view', 'medical.ipd.admissions.view',
                    'medical_emergency.view', 'medical.emergency.visits.view',
                    'medical.records.view', 'medical.records.timeline.view',
                    'medical_lab.view', 'medical.laboratory.tests.view',
                    'medical_radiology.view', 'medical.radiology.orders.view',
                    'medical_reports.view', 'medical.reports.view',
                ]),
            ],
            'medical-officer' => [
                'name' => 'Medical Officer',
                'permissions' => array_merge($opdView, $opdCreate, [
                    'medical_prescriptions.edit', 'medical.opd.prescriptions.edit',
                    'medical_admissions.view', 'medical.ipd.admissions.view',
                    'medical_emergency.view', 'medical.emergency.visits.view',
                    'medical_lab.view', 'medical.laboratory.tests.view',
                    'medical_radiology.view', 'medical.radiology.orders.view',
                ]),
            ],
            'intern-doctor' => [
                'name' => 'Intern Doctor',
                'permissions' => [
                    'medical_patients.view', 'medical.patients.view',
                    'medical_appointments.view', 'medical.opd.appointments.view',
                    'medical_prescriptions.view', 'medical.opd.prescriptions.view',
                    'medical_vitals.view', 'medical.opd.vitals.view',
                    'medical_admissions.view', 'medical.ipd.admissions.view',
                ],
            ],

            // === IPD / Nursing ===
            'head-nurse' => [
                'name' => 'Head Nurse',
                'permissions' => [
                    'medical_patients.view', 'medical.patients.view',
                    'medical_appointments.view', 'medical.opd.appointments.view',
                    'medical_vitals.view', 'medical.opd.vitals.view',
                    'medical_vitals.create', 'medical.opd.vitals.create',
                    'medical_vitals.update', 'medical.opd.vitals.update',
                    'medical_admissions.view', 'medical.ipd.admissions.view',
                    'medical_admissions.edit', 'medical.ipd.admissions.edit',
                    'medical_admissions.discharge', 'medical.ipd.admissions.discharge',
                    'medical_beds.view', 'medical.ipd.beds.view',
                    'medical_emergency.view', 'medical.emergency.visits.view',
                    'medical_emergency.triage', 'medical.emergency.visits.triage',
                    'medical.records.view', 'medical.records.timeline.view',
                ],
            ],
            'ward-nurse' => [
                'name' => 'Ward Nurse',
                'permissions' => [
                    'medical_patients.view', 'medical.patients.view',
                    'medical_vitals.view', 'medical.opd.vitals.view',
                    'medical_vitals.create', 'medical.opd.vitals.create',
                    'medical_admissions.view', 'medical.ipd.admissions.view',
                    'medical_beds.view', 'medical.ipd.beds.view',
                    'medical_emergency.view', 'medical.emergency.visits.view',
                    'medical_emergency.triage', 'medical.emergency.visits.triage',
                    'medical.records.view', 'medical.records.timeline.view',
                ],
            ],
            'ward-boy' => [
                'name' => 'Ward Boy',
                'permissions' => [
                    'medical_admissions.view', 'medical.ipd.admissions.view',
                    'medical_emergency.view', 'medical.emergency.visits.view',
                ],
            ],
            'patient-attendant' => [
                'name' => 'Patient Attendant',
                'permissions' => [
                    'medical_admissions.view', 'medical.ipd.admissions.view',
                ],
            ],

            // === Pharmacy ===
            'pharmacy-assistant' => [
                'name' => 'Pharmacy Assistant',
                'permissions' => [
                    'medical_pharmacy.view', 'medical_pharmacy.dispense',
                    'medical.pharmacy.dispense', 'medical.pharmacy.stock.view',
                    'medical_medicines.view', 'medical.pharmacy.medicines.view',
                ],
            ],
            'store-manager' => [
                'name' => 'Store Manager',
                'permissions' => [
                    'medical_pharmacy.view',
                    'medical.pharmacy.stock.view', 'medical.pharmacy.stock.create', 'medical.pharmacy.stock.edit',
                    'medical_medicines.view', 'medical.pharmacy.medicines.view',
                    'medical_medicines.create', 'medical.pharmacy.medicines.create',
                    'medical_medicines.edit', 'medical.pharmacy.medicines.edit',
                ],
            ],

            // === Laboratory ===
            'pathologist' => [
                'name' => 'Pathologist',
                'permissions' => [
                    'medical_lab.view', 'medical_lab.edit',
                    'medical.laboratory.tests.view', 'medical.laboratory.tests.edit',
                    'medical_reports.view', 'medical.reports.view',
                ],
            ],
            'phlebotomist' => [
                'name' => 'Phlebotomist',
                'permissions' => [
                    'medical_lab.view', 'medical.laboratory.tests.view',
                    'medical_bloodbank.view', 'medical.bloodbank.view',
                ],
            ],

            // === Billing & Finance ===
            'cashier' => [
                'name' => 'Cashier',
                'permissions' => [
                    'medical_billing.view', 'medical_billing.process',
                    'medical.billing.invoices.view', 'medical.billing.invoices.process',
                ],
            ],
            'billing-clerk' => [
                'name' => 'Billing Clerk',
                'permissions' => [
                    'medical_billing.view', 'medical_billing.create', 'medical_billing.edit',
                    'medical.billing.invoices.view', 'medical.billing.invoices.create', 'medical.billing.invoices.edit',
                    'medical_tpa.view', 'medical.billing.tpa.view',
                ],
            ],
            'insurance-coordinator' => [
                'name' => 'Insurance Coordinator',
                'permissions' => [
                    'medical_billing.view', 'medical.billing.invoices.view',
                    'medical_tpa.view', 'medical_tpa.approve', 'medical_tpa.settle',
                    'medical.billing.tpa.view', 'medical.billing.tpa.approve', 'medical.billing.tpa.settle',
                ],
            ],

            // === Emergency ===
            'er-doctor' => [
                'name' => 'ER Doctor',
                'permissions' => [
                    'medical_emergency.view', 'medical_emergency.create', 'medical_emergency.edit',
                    'medical_emergency.triage', 'medical_emergency.discharge',
                    'medical.emergency.visits.view', 'medical.emergency.visits.create',
                    'medical.emergency.visits.edit', 'medical.emergency.visits.triage',
                    'medical.emergency.visits.discharge',
                    'medical_patients.view', 'medical.patients.view',
                    'medical_appointments.view', 'medical.opd.appointments.view',
                    'medical_prescriptions.view', 'medical.opd.prescriptions.view',
                ],
            ],
            'triage-nurse' => [
                'name' => 'Triage Nurse',
                'permissions' => [
                    'medical_emergency.view', 'medical_emergency.create', 'medical_emergency.triage',
                    'medical.emergency.visits.view', 'medical.emergency.visits.create', 'medical.emergency.visits.triage',
                    'medical_vitals.view', 'medical.opd.vitals.view',
                    'medical_vitals.create', 'medical.opd.vitals.create',
                ],
            ],
            'paramedic' => [
                'name' => 'Paramedic',
                'permissions' => array_merge($emergencyView, [
                    'medical_emergency.create', 'medical.emergency.visits.create',
                    'medical.ambulance.view', 'medical.ambulance.trip.create',
                ]),
            ],
            'er-attendant' => [
                'name' => 'ER Attendant',
                'permissions' => $emergencyView,
            ],

            // === Radiology ===
            'radiologist' => [
                'name' => 'Radiologist',
                'permissions' => [
                    'medical_radiology.view', 'medical_radiology.report', 'medical_radiology.verify',
                    'medical.radiology.orders.view', 'medical.radiology.orders.report', 'medical.radiology.orders.verify',
                    'medical_reports.view', 'medical.reports.view',
                ],
            ],
            'radiology-technician' => [
                'name' => 'Radiology Technician',
                'permissions' => [
                    'medical_radiology.view', 'medical_radiology.perform',
                    'medical.radiology.orders.view', 'medical.radiology.orders.perform',
                ],
            ],
            'sonographer' => [
                'name' => 'Sonographer',
                'permissions' => [
                    'medical_radiology.view', 'medical_radiology.perform', 'medical_radiology.report',
                    'medical.radiology.orders.view', 'medical.radiology.orders.perform', 'medical.radiology.orders.report',
                ],
            ],

            // === Blood Bank ===
            'blood-bank-technician' => [
                'name' => 'Blood Bank Technician',
                'permissions' => [
                    'medical_bloodbank.view', 'medical_bloodbank.create', 'medical_bloodbank.edit',
                    'medical_bloodbank.issue',
                    'medical.bloodbank.view', 'medical.bloodbank.create', 'medical.bloodbank.edit',
                    'medical.bloodbank.issue',
                ],
            ],

            // === Physiotherapy ===
            'physiotherapist' => [
                'name' => 'Physiotherapist',
                'permissions' => [
                    'medical.physiotherapy.view', 'medical.physiotherapy.plan.create',
                    'medical.physiotherapy.plan.edit', 'medical.physiotherapy.session.attend',
                    'medical.physiotherapy.exercise.manage',
                ],
            ],
            'physio-assistant' => [
                'name' => 'Physio Assistant',
                'permissions' => [
                    'medical.physiotherapy.view', 'medical.physiotherapy.session.attend',
                ],
            ],

            // === Dental ===
            'dentist' => [
                'name' => 'Dentist',
                'permissions' => [
                    'medical.dental.view', 'medical.dental.chart.edit',
                    'medical.dental.procedure.create', 'medical.dental.procedure.edit',
                    'medical.dental.plan.manage',
                ],
            ],
            'dental-assistant' => [
                'name' => 'Dental Assistant',
                'permissions' => [
                    'medical.dental.view', 'medical.dental.chart.edit',
                ],
            ],
            'dental-hygienist' => [
                'name' => 'Dental Hygienist',
                'permissions' => [
                    'medical.dental.view', 'medical.dental.chart.edit',
                    'medical.dental.procedure.create',
                ],
            ],

            // === Vaccination ===
            'vaccinator' => [
                'name' => 'Vaccinator',
                'permissions' => [
                    'medical.vaccination.view', 'medical.vaccination.administer',
                ],
            ],
            'cold-chain-manager' => [
                'name' => 'Cold Chain Manager',
                'permissions' => [
                    'medical.vaccination.view', 'medical.vaccination.manage',
                    'medical.vaccination.stock.manage',
                ],
            ],

            // === Medical Records / EMR ===
            'medical-records-officer' => [
                'name' => 'Medical Records Officer',
                'permissions' => array_merge($recordsView, [
                    'medical.records.document.upload', 'medical.records.document.download',
                    'medical.records.note.create',
                    'medical_reports.view', 'medical.reports.view',
                ]),
            ],
            'health-information-manager' => [
                'name' => 'Health Information Manager',
                'permissions' => array_merge($recordsView, [
                    'medical.records.document.upload', 'medical.records.document.download',
                    'medical.records.discharge.create',
                    'medical_reports.view', 'medical.reports.view',
                ]),
            ],

            // === Diet & Nutrition ===
            'dietitian' => [
                'name' => 'Dietitian',
                'permissions' => [
                    'medical.diet.view', 'medical.diet.plan.create', 'medical.diet.plan.edit',
                    'medical.diet.meal.serve', 'medical.diet.template.manage',
                ],
            ],
            'nutritionist' => [
                'name' => 'Nutritionist',
                'permissions' => [
                    'medical.diet.view', 'medical.diet.plan.create',
                ],
            ],
            'kitchen-staff' => [
                'name' => 'Kitchen Staff',
                'permissions' => [
                    'medical.diet.view', 'medical.diet.meal.serve',
                ],
            ],

            // === Ambulance ===
            'ambulance-driver' => [
                'name' => 'Ambulance Driver',
                'permissions' => [
                    'medical.ambulance.view', 'medical.ambulance.trip.complete',
                ],
            ],
            'ambulance-paramedic' => [
                'name' => 'Ambulance Paramedic',
                'permissions' => [
                    'medical.ambulance.view', 'medical.ambulance.trip.create',
                    'medical.ambulance.trip.dispatch',
                ],
            ],
            'ambulance-dispatcher' => [
                'name' => 'Ambulance Dispatcher',
                'permissions' => [
                    'medical.ambulance.view', 'medical.ambulance.trip.create',
                    'medical.ambulance.trip.dispatch', 'medical.ambulance.trip.complete',
                ],
            ],
        ];
    }

    /**
     * Additive-only extensions for PRE-EXISTING roles. These roles keep
     * every grant they already have; the seeder only attaches the listed
     * new-convention slugs (syncWithoutDetaching — never detaches).
     *
     * @return array<string, string[]>
     */
    public static function existingRoleExtensions(): array
    {
        return [
            // Pre-existing 'lab-technician' already holds the underscore
            // lab grants; add the dot-notation twins so new-convention
            // checks pass for lab staff.
            'lab-technician' => [
                'medical.laboratory.tests.view',
                'medical.laboratory.tests.create',
                'medical.laboratory.tests.edit',
            ],
            // Pre-existing 'records-officer' predates the EMR module;
            // grant the EMR read/upload bundle it was always meant for.
            'records-officer' => [
                'medical.records.view',
                'medical.records.timeline.view',
                'medical.records.document.upload',
                'medical.records.document.download',
            ],
        ];
    }

    public function run(): void
    {
        $institutes = Institute::where('industry', 'healthcare')->orderBy('id')->get();

        if ($institutes->isEmpty()) {
            $this->command?->warn('No healthcare institutes found; nothing seeded.');

            return;
        }

        $defs = self::roles();
        $created = 0;
        $existing = 0;
        $attached = 0;
        $skipped = [];

        foreach ($institutes as $institute) {
            foreach ($defs as $slug => $def) {
                $role = Role::firstOrCreate(
                    ['institute_id' => $institute->id, 'slug' => $slug],
                    ['name' => $def['name'], 'status' => 'active']
                );

                if ($role->wasRecentlyCreated) {
                    $created++;
                } else {
                    $existing++;
                }

                $permissions = Permission::whereIn('slug', $def['permissions'])->get();
                $missing = array_diff($def['permissions'], $permissions->pluck('slug')->all());
                foreach ($missing as $miss) {
                    $skipped[$miss] = true;
                }

                if ($permissions->isNotEmpty()) {
                    $before = $role->permissions()->count();
                    $role->permissions()->syncWithoutDetaching($permissions->pluck('id')->all());
                    $attached += $role->permissions()->count() - $before;
                }
            }

            foreach (self::existingRoleExtensions() as $slug => $slugs) {
                $role = Role::where('institute_id', $institute->id)->where('slug', $slug)->first();
                if (! $role) {
                    continue;
                }
                $permissions = Permission::whereIn('slug', $slugs)->get();
                if ($permissions->isNotEmpty()) {
                    $before = $role->permissions()->count();
                    $role->permissions()->syncWithoutDetaching($permissions->pluck('id')->all());
                    $attached += $role->permissions()->count() - $before;
                }
            }
        }

        $this->command?->info(
            "Medical roles: {$created} created, {$existing} existing, {$attached} permissions attached, "
            . count($institutes) . ' healthcare institute(s).'
        );
        if (! empty($skipped)) {
            $this->command?->warn('Skipped unknown permission slugs: ' . implode(', ', array_keys($skipped)));
        }
    }
}

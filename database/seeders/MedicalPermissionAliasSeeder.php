<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MedicalPermissionAliasSeeder extends Seeder
{
    public function run(): void
    {
        // Map old slugs → new dot-notation slugs grouped by sub-module
        $permissionMap = [
            // OPD
            'medical_prescriptions.view'    => 'medical.opd.prescriptions.view',
            'medical_prescriptions.create'  => 'medical.opd.prescriptions.create',
            'medical_prescriptions.edit'    => 'medical.opd.prescriptions.edit',
            'medical_prescriptions.delete'  => 'medical.opd.prescriptions.delete',
            'medical_prescriptions.amend'   => 'medical.opd.prescriptions.amend',
            'medical_appointments.view'     => 'medical.opd.appointments.view',
            'medical_appointments.create'   => 'medical.opd.appointments.create',
            'medical_appointments.edit'     => 'medical.opd.appointments.edit',
            'medical_appointments.delete'   => 'medical.opd.appointments.delete',
            'medical_vitals.view'           => 'medical.opd.vitals.view',
            'medical_vitals.create'         => 'medical.opd.vitals.create',
            'medical_vitals.update'         => 'medical.opd.vitals.update',
            'medical_vitals.delete'         => 'medical.opd.vitals.delete',
            'medical_encounters.view'       => 'medical.opd.encounters.view',
            'medical_encounters.create'     => 'medical.opd.encounters.create',
            'medical_encounters.edit'       => 'medical.opd.encounters.edit',
            'medical_encounters.complete'   => 'medical.opd.encounters.complete',
            'medical_encounters.amend'      => 'medical.opd.encounters.amend',
            'medical_diagnoses.view'        => 'medical.opd.diagnoses.view',
            'medical_diagnoses.create'      => 'medical.opd.diagnoses.create',
            'medical_diagnoses.remove'      => 'medical.opd.diagnoses.remove',
            'medical_problems.create'       => 'medical.opd.problems.create',
            'medical_problems.edit'         => 'medical.opd.problems.edit',
            'medical_problems.view'         => 'medical.opd.problems.view',
            'medical_followups.create'      => 'medical.opd.followups.create',
            'medical_followups.edit'        => 'medical.opd.followups.edit',
            'medical_followups.view'        => 'medical.opd.followups.view',
            'medical_queue.reorder'         => 'medical.opd.queue.reorder',
            // IPD
            'medical_admissions.view'       => 'medical.ipd.admissions.view',
            'medical_admissions.create'     => 'medical.ipd.admissions.create',
            'medical_admissions.edit'       => 'medical.ipd.admissions.edit',
            'medical_admissions.discharge'  => 'medical.ipd.admissions.discharge',
            'medical_admissions.delete'     => 'medical.ipd.admissions.delete',
            'medical_wards.view'            => 'medical.ipd.wards.view',
            'medical_wards.create'          => 'medical.ipd.wards.create',
            'medical_wards.edit'            => 'medical.ipd.wards.edit',
            'medical_wards.delete'          => 'medical.ipd.wards.delete',
            'medical_beds.view'             => 'medical.ipd.beds.view',
            'medical_beds.create'           => 'medical.ipd.beds.create',
            'medical_beds.edit'             => 'medical.ipd.beds.edit',
            'medical_beds.delete'           => 'medical.ipd.beds.delete',
            'medical_beds.allocate'         => 'medical.ipd.beds.allocate',
            // Pharmacy
            'medical_medicines.view'        => 'medical.pharmacy.medicines.view',
            'medical_medicines.create'      => 'medical.pharmacy.medicines.create',
            'medical_medicines.edit'        => 'medical.pharmacy.medicines.edit',
            'medical_medicines.delete'      => 'medical.pharmacy.medicines.delete',
            'medical_pharmacy.view'         => 'medical.pharmacy.stock.view',
            'medical_pharmacy.create'       => 'medical.pharmacy.stock.create',
            'medical_pharmacy.edit'         => 'medical.pharmacy.stock.edit',
            'medical_pharmacy.delete'       => 'medical.pharmacy.stock.delete',
            'medical_pharmacy.dispense'     => 'medical.pharmacy.dispense',
            // Laboratory
            'medical_lab.view'              => 'medical.laboratory.tests.view',
            'medical_lab.create'            => 'medical.laboratory.tests.create',
            'medical_lab.edit'              => 'medical.laboratory.tests.edit',
            'medical_lab.delete'            => 'medical.laboratory.tests.delete',
            // Billing
            'medical_billing.view'          => 'medical.billing.invoices.view',
            'medical_billing.create'        => 'medical.billing.invoices.create',
            'medical_billing.edit'          => 'medical.billing.invoices.edit',
            'medical_billing.delete'        => 'medical.billing.invoices.delete',
            'medical_billing.process'       => 'medical.billing.invoices.process',
            'medical_tpa.view'              => 'medical.billing.tpa.view',
            'medical_tpa.create'            => 'medical.billing.tpa.create',
            'medical_tpa.edit'              => 'medical.billing.tpa.edit',
            'medical_tpa.delete'            => 'medical.billing.tpa.delete',
            'medical_tpa.approve'           => 'medical.billing.tpa.approve',
            'medical_tpa.settle'            => 'medical.billing.tpa.settle',
            // Cross-cutting (keep at medical.* root)
            'medical_patients.view'         => 'medical.patients.view',
            'medical_patients.create'       => 'medical.patients.create',
            'medical_patients.edit'         => 'medical.patients.edit',
            'medical_patients.delete'       => 'medical.patients.delete',
            'medical_doctors.view'          => 'medical.doctors.view',
            'medical_doctors.create'        => 'medical.doctors.create',
            'medical_doctors.edit'          => 'medical.doctors.edit',
            'medical_doctors.delete'        => 'medical.doctors.delete',
            'medical_branches.view'         => 'medical.branches.view',
            'medical_branches.manage'       => 'medical.branches.manage',
            'medical_reports.view'          => 'medical.reports.view',
            // Emergency
            'medical_emergency.view'        => 'medical.emergency.visits.view',
            'medical_emergency.create'      => 'medical.emergency.visits.create',
            'medical_emergency.edit'        => 'medical.emergency.visits.edit',
            'medical_emergency.triage'      => 'medical.emergency.visits.triage',
            'medical_emergency.discharge'   => 'medical.emergency.visits.discharge',
            'medical_emergency.delete'      => 'medical.emergency.visits.delete',
            // Radiology
            'medical_radiology.view'        => 'medical.radiology.orders.view',
            'medical_radiology.create'      => 'medical.radiology.orders.create',
            'medical_radiology.edit'        => 'medical.radiology.orders.edit',
            'medical_radiology.delete'      => 'medical.radiology.orders.delete',
            'medical_radiology.perform'     => 'medical.radiology.orders.perform',
            'medical_radiology.report'      => 'medical.radiology.orders.report',
            'medical_radiology.verify'      => 'medical.radiology.orders.verify',
            // Blood Bank
            'medical_bloodbank.view'        => 'medical.bloodbank.view',
            'medical_bloodbank.create'      => 'medical.bloodbank.create',
            'medical_bloodbank.edit'        => 'medical.bloodbank.edit',
            'medical_bloodbank.delete'      => 'medical.bloodbank.delete',
            'medical_bloodbank.issue'       => 'medical.bloodbank.issue',
            // Physiotherapy
            'medical.physiotherapy.view'          => 'medical.physiotherapy.view',
            'medical.physiotherapy.plan.create'   => 'medical.physiotherapy.plan.create',
            'medical.physiotherapy.plan.edit'     => 'medical.physiotherapy.plan.edit',
            'medical.physiotherapy.session.attend' => 'medical.physiotherapy.session.attend',
            'medical.physiotherapy.exercise.manage' => 'medical.physiotherapy.exercise.manage',
            // Dental
            'medical.dental.view'              => 'medical.dental.view',
            'medical.dental.chart.edit'        => 'medical.dental.chart.edit',
            'medical.dental.procedure.create'  => 'medical.dental.procedure.create',
            'medical.dental.procedure.edit'    => 'medical.dental.procedure.edit',
            'medical.dental.plan.manage'       => 'medical.dental.plan.manage',
            'medical.dental.catalog.manage'    => 'medical.dental.catalog.manage',
            // Vaccination
            'medical.vaccination.view'         => 'medical.vaccination.view',
            'medical.vaccination.manage'       => 'medical.vaccination.manage',
            'medical.vaccination.schedule'     => 'medical.vaccination.schedule',
            'medical.vaccination.administer'   => 'medical.vaccination.administer',
            'medical.vaccination.stock.manage' => 'medical.vaccination.stock.manage',
            // Medical Records (EMR)
            'medical.records.view'              => 'medical.records.view',
            'medical.records.timeline.view'     => 'medical.records.timeline.view',
            'medical.records.document.upload'   => 'medical.records.document.upload',
            'medical.records.document.download' => 'medical.records.document.download',
            'medical.records.document.delete'   => 'medical.records.document.delete',
            'medical.records.discharge.create'  => 'medical.records.discharge.create',
            'medical.records.note.create'       => 'medical.records.note.create',
            'medical.records.note.sign'         => 'medical.records.note.sign',
            // Diet & Nutrition
            'medical.diet.view'              => 'medical.diet.view',
            'medical.diet.plan.create'       => 'medical.diet.plan.create',
            'medical.diet.plan.edit'         => 'medical.diet.plan.edit',
            'medical.diet.meal.serve'        => 'medical.diet.meal.serve',
            'medical.diet.template.manage'   => 'medical.diet.template.manage',
            // Ambulance
            'medical.ambulance.view'          => 'medical.ambulance.view',
            'medical.ambulance.fleet.manage'  => 'medical.ambulance.fleet.manage',
            'medical.ambulance.driver.manage' => 'medical.ambulance.driver.manage',
            'medical.ambulance.trip.create'   => 'medical.ambulance.trip.create',
            'medical.ambulance.trip.dispatch' => 'medical.ambulance.trip.dispatch',
            'medical.ambulance.trip.complete' => 'medical.ambulance.trip.complete',
        ];

        foreach ($permissionMap as $oldSlug => $newSlug) {
            // Find old permission
            $oldPerm = DB::table('permissions')->where('slug', $oldSlug)->first();
            if (! $oldPerm) {
                continue;
            }

            // Create new permission if it doesn't exist
            $newPermId = DB::table('permissions')->where('slug', $newSlug)->value('id');
            if (! $newPermId) {
                $newPermId = DB::table('permissions')->insertGetId([
                    'module'     => $oldPerm->module,
                    'name'       => $oldPerm->name,
                    'slug'       => $newSlug,
                    'created_at' => now(),
                ]);
            }

            // Copy role assignments from old to new
            $roleAssignments = DB::table('role_permissions')
                ->where('permission_id', $oldPerm->id)
                ->get();

            foreach ($roleAssignments as $ra) {
                DB::table('role_permissions')->insertOrIgnore([
                    'role_id'       => $ra->role_id,
                    'permission_id' => $newPermId,
                ]);
            }
        }
    }
}

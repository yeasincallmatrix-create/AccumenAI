<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Phase 18.1 — deterministic HMS branch backfill assessment + fill.
 *
 * Only fills branch_id where the parent relationship is unambiguous
 * (bed→ward, encounter→appointment, prescription/lab→encounter,
 * invoice→admission, follow-up→encounter, audit→resolvable parent).
 * Everything ambiguous stays NULL (legacy-valid). Columns remain
 * nullable; nothing is renumbered, nothing clinical is rewritten.
 *
 * --dry-run (default): report only, no writes.
 * --execute: perform the deterministic fills inside transactions.
 */
class BackfillHmsBranch extends Command
{
    protected $signature = 'medical:backfill-branch {--execute : Perform the fills (default is dry-run report only)}';

    protected $description = 'Report (and optionally fill) deterministic HMS branch_id values from parent relationships.';

    public function handle(): int
    {
        $execute = (bool) $this->option('execute');
        $this->info($execute ? 'EXECUTE mode: filling deterministic branches.' : 'DRY-RUN mode: report only, no writes.');

        $maps = [
            'beds <- wards' => [
                'table' => 'beds',
                'join' => 'JOIN wards w ON w.id = beds.ward_id',
                'parent_branch' => 'w.branch_id',
            ],
            'medical_encounters <- appointments' => [
                'table' => 'medical_encounters',
                'join' => 'JOIN appointments a ON a.id = medical_encounters.appointment_id',
                'parent_branch' => 'a.branch_id',
            ],
            'prescriptions <- encounters' => [
                'table' => 'prescriptions',
                'join' => 'JOIN medical_encounters e ON e.id = prescriptions.encounter_id',
                'parent_branch' => 'e.branch_id',
            ],
            'lab_orders <- encounters' => [
                'table' => 'lab_orders',
                'join' => 'JOIN medical_encounters e ON e.id = lab_orders.encounter_id',
                'parent_branch' => 'e.branch_id',
            ],
            'medical_invoices <- admissions' => [
                'table' => 'medical_invoices',
                'join' => 'JOIN admissions a ON a.id = medical_invoices.admission_id',
                'parent_branch' => 'a.branch_id',
            ],
            'medical_follow_ups <- encounters' => [
                'table' => 'medical_follow_ups',
                'join' => 'JOIN medical_encounters e ON e.id = medical_follow_ups.encounter_id',
                'parent_branch' => 'e.branch_id',
            ],
        ];

        $rows = [];
        foreach ($maps as $label => $map) {
            $total = (int) DB::table($map['table'])->count();
            $nulls = (int) DB::table($map['table'])->whereNull('branch_id')->count();
            $candidates = (int) DB::selectOne(
                "SELECT COUNT(*) AS c FROM {$map['table']} {$map['join']} WHERE {$map['table']}.branch_id IS NULL AND {$map['parent_branch']} IS NOT NULL"
            )->c;

            $filled = 0;
            if ($execute && $candidates > 0) {
                $filled = DB::transaction(function () use ($map) {
                    return DB::update(
                        "UPDATE {$map['table']} {$map['join']} SET {$map['table']}.branch_id = {$map['parent_branch']} WHERE {$map['table']}.branch_id IS NULL AND {$map['parent_branch']} IS NOT NULL"
                    );
                });
            }

            $rows[] = [$label, $total, $nulls, $candidates, $filled, $nulls - $filled];
        }

        // Clinical audit rows: derivable parents only (direct branch carriers
        // + encounter/admission/order/prescription children). Patient-level
        // and orphan rows stay NULL.
        $auditTotal = (int) DB::table('clinical_audit_logs')->count();
        $auditNulls = (int) DB::table('clinical_audit_logs')->whereNull('branch_id')->count();
        $auditFilled = 0;
        if ($execute) {
            $auditFilled = DB::transaction(function () {
                $n = 0;
                // Direct carriers.
                foreach (['appointments', 'medical_encounters', 'admissions', 'prescriptions', 'lab_orders', 'medical_invoices', 'medical_follow_ups'] as $parent) {
                    $type = $this->auditableType($parent);
                    if (! $type) {
                        continue;
                    }
                    $n += DB::update(
                        "UPDATE clinical_audit_logs cal JOIN {$parent} p ON p.id = cal.auditable_id SET cal.branch_id = p.branch_id WHERE cal.branch_id IS NULL AND cal.auditable_type = ? AND p.branch_id IS NOT NULL",
                        [$type]
                    );
                }
                // Encounter diagnoses via encounter.
                $n += DB::update(
                    "UPDATE clinical_audit_logs cal JOIN encounter_diagnoses d ON d.id = cal.auditable_id JOIN medical_encounters e ON e.id = d.encounter_id SET cal.branch_id = e.branch_id WHERE cal.branch_id IS NULL AND cal.auditable_type = ? AND e.branch_id IS NOT NULL",
                    ['App\\Models\\Medical\\EncounterDiagnosis']
                );
                // Vitals/notes via admission, else appointment (OPD vitals).
                foreach (['vital_signs' => 'App\\Models\\Medical\\VitalSign', 'nursing_notes' => 'App\\Models\\Medical\\NursingNote'] as $child => $type) {
                    $n += DB::update(
                        "UPDATE clinical_audit_logs cal JOIN {$child} c ON c.id = cal.auditable_id JOIN admissions a ON a.id = c.admission_id SET cal.branch_id = a.branch_id WHERE cal.branch_id IS NULL AND cal.auditable_type = ? AND a.branch_id IS NOT NULL",
                        [$type]
                    );
                }
                $n += DB::update(
                    "UPDATE clinical_audit_logs cal JOIN vital_signs v ON v.id = cal.auditable_id JOIN appointments ap ON ap.id = v.appointment_id SET cal.branch_id = ap.branch_id WHERE cal.branch_id IS NULL AND cal.auditable_type = ? AND ap.branch_id IS NOT NULL AND v.admission_id IS NULL",
                    ['App\\Models\\Medical\\VitalSign']
                );
                // Lab results via order.
                $n += DB::update(
                    "UPDATE clinical_audit_logs cal JOIN lab_results r ON r.id = cal.auditable_id JOIN lab_orders o ON o.id = r.lab_order_id SET cal.branch_id = o.branch_id WHERE cal.branch_id IS NULL AND cal.auditable_type = ? AND o.branch_id IS NOT NULL",
                    ['App\\Models\\Medical\\LabResult']
                );

                return $n;
            });
        }
        $rows[] = ['clinical_audit_logs <- parents', $auditTotal, $auditNulls, 'n/a (see dry-run note)', $auditFilled, $auditNulls - $auditFilled];

        $this->table(
            ['mapping', 'total', 'nulls', 'deterministic candidates', 'filled', 'remaining null'],
            $rows
        );
        $this->info('Admissions <- beds intentionally excluded (transfer history makes it ambiguous). Columns stay nullable.');

        return self::SUCCESS;
    }

    private function auditableType(string $table): ?string
    {
        return match ($table) {
            'appointments' => 'App\\Models\\Medical\\Appointment',
            'medical_encounters' => 'App\\Models\\Medical\\Encounter',
            'admissions' => 'App\\Models\\Medical\\Admission',
            'prescriptions' => 'App\\Models\\Medical\\Prescription',
            'lab_orders' => 'App\\Models\\Medical\\LabOrder',
            'medical_invoices' => 'App\\Models\\Medical\\Invoice',
            'medical_follow_ups' => 'App\\Models\\Medical\\FollowUp',
            default => null,
        };
    }
}

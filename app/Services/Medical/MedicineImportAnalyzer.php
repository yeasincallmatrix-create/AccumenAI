<?php

namespace App\Services\Medical;

use App\Models\Medical\Medicine;
use App\Support\MedicineDosageForm;

/**
 * Two-phase CSV import analysis: classifies parsed rows as clean,
 * conflicts (user must choose skip / update / create_new), or errors
 * (auto-skipped). Pure analysis — performs zero writes.
 */
class MedicineImportAnalyzer
{
    /**
     * Analyze parsed CSV rows and return classified results.
     *
     * @return array{
     *   clean: array,
     *   conflicts: array,
     *   errors: array,
     * }
     */
    public function analyze(array $rows, int $instituteId): array
    {
        $clean = [];
        $conflicts = [];
        $errors = [];
        $canonicalForms = MedicineDosageForm::all();

        // Track codes used within this batch
        $batchCodes = [];
        $batchNames = [];

        foreach ($rows as $index => $row) {
            $rowNum = $index + 2; // +2 because header is row 1
            $issues = [];

            // Required field validation
            if (empty($row['brand_name'])) {
                $errors[] = ['row' => $rowNum, 'data' => $row, 'reason' => 'brand_name is required'];
                continue;
            }
            if (empty($row['strength'])) {
                $errors[] = ['row' => $rowNum, 'data' => $row, 'reason' => 'strength is required'];
                continue;
            }
            if (empty($row['dosage_form'])) {
                $errors[] = ['row' => $rowNum, 'data' => $row, 'reason' => 'dosage_form is required'];
                continue;
            }

            // Dosage form validation
            $form = ucfirst(strtolower(trim($row['dosage_form'])));
            if (! in_array($form, $canonicalForms, true)) {
                $errors[] = [
                    'row' => $rowNum,
                    'data' => $row,
                    'reason' => "Invalid dosage_form '{$row['dosage_form']}'",
                ];
                continue;
            }

            // Code validation (same contract as the form + single-pass import:
            // empty = auto-generate, 4–6 digits, or legacy MED- prefix).
            $code = ! empty($row['code']) ? trim($row['code']) : null;
            if ($code !== null) {
                // Format check
                if (! preg_match('/^([0-9]{4,6}|MED-[A-Za-z0-9\-]+)$/', $code)) {
                    $issues[] = [
                        'type' => 'invalid_code_format',
                        'message' => "Code '{$code}' is invalid. Must be 4-6 digits.",
                        'severity' => 'error',
                    ];
                } else {
                    // Duplicate check within institute
                    $existing = Medicine::where('institute_id', $instituteId)
                        ->where('code', $code)
                        ->whereNull('deleted_at')
                        ->first();
                    if ($existing) {
                        $issues[] = [
                            'type' => 'code_exists',
                            'message' => "Code '{$code}' is already used by '{$existing->brand_name}'",
                            'existing_id' => $existing->id,
                            'existing_name' => $existing->brand_name,
                            'severity' => 'conflict',
                        ];
                    }

                    // Duplicate check within batch
                    if (in_array($code, $batchCodes)) {
                        $issues[] = [
                            'type' => 'code_in_batch',
                            'message' => "Code '{$code}' appears multiple times in this file",
                            'severity' => 'conflict',
                        ];
                    }
                    $batchCodes[] = $code;
                }
            }

            // Duplicate medicine check (generic + strength + form)
            $duplicateCheck = app(MedicineDuplicateService::class)->findDuplicates(
                $instituteId,
                $row['brand_name'],
                $row['strength'] ?? null
            );

            if ($duplicateCheck->isNotEmpty()) {
                $existing = $duplicateCheck->first();
                $issues[] = [
                    'type' => 'medicine_exists',
                    'message' => "Similar medicine exists: '{$existing->brand_name} {$existing->strength}'",
                    'existing_id' => $existing->id,
                    'existing_name' => $existing->brand_name,
                    'existing_strength' => $existing->strength,
                    'severity' => 'conflict',
                ];
            }

            // Duplicate within batch
            $batchKey = strtolower(trim($row['brand_name'] . ' ' . ($row['strength'] ?? '')));
            if (isset($batchNames[$batchKey])) {
                $issues[] = [
                    'type' => 'duplicate_in_batch',
                    'message' => "Duplicate in same file: '{$row['brand_name']} {$row['strength']}'",
                    'severity' => 'conflict',
                ];
            }
            $batchNames[$batchKey] = true;

            // Classify
            if (empty($issues)) {
                $row['_row_num'] = $rowNum;
                $clean[] = $row;
            } elseif (collect($issues)->every(fn ($i) => $i['severity'] === 'conflict')) {
                $conflicts[] = [
                    'row' => $rowNum,
                    'data' => $row,
                    'issues' => $issues,
                ];
            } else {
                $errors[] = [
                    'row' => $rowNum,
                    'data' => $row,
                    'issues' => $issues,
                ];
            }
        }

        return [
            'clean' => $clean,
            'conflicts' => $conflicts,
            'errors' => $errors,
        ];
    }

    /**
     * Apply user resolutions to conflicts.
     *
     * @param  array  $conflicts  Conflicts from analysis
     * @param  array  $resolutions  User choices: [row_num => 'skip' | 'update' | 'create_new']
     * @return array{rows: array, skipped: int}
     */
    public function applyResolutions(array $conflicts, array $resolutions): array
    {
        $rows = [];
        $skipped = 0;

        foreach ($conflicts as $conflict) {
            $rowNum = $conflict['row'];
            $choice = $resolutions[$rowNum] ?? 'skip';

            if ($choice === 'skip') {
                $skipped++;
                continue;
            }

            $data = $conflict['data'];
            $data['_row_num'] = $rowNum;
            $data['_resolution'] = $choice;
            $data['_existing_id'] = collect($conflict['issues'])
                ->firstWhere('existing_id')['existing_id'] ?? null;

            $rows[] = $data;
        }

        return ['rows' => $rows, 'skipped' => $skipped];
    }
}

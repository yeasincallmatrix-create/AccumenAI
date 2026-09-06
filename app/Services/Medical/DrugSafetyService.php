<?php

namespace App\Services\Medical;

use App\Models\Medical\Medicine;
use App\Models\Medical\Patient;
use App\Support\MedicalScope;
use Illuminate\Support\Collection;

/**
 * Heuristic drug-safety checks for e-prescriptions.
 *
 * There is no interaction database in the system, so checks are text-based
 * heuristics over the medicine catalog + patient record:
 *   - allergies: patient allergy list vs generic/brand/category (BLOCK)
 *   - contraindications: chronic conditions vs medicine text (BLOCK)
 *   - same generic twice: duplicate therapy (BLOCK, high)
 *   - same category: possible overlap (WARN only, medium)
 *
 * All medicine lookups are institute-scoped. Callers decide blocking: the
 * convention is block on allergies/contraindications/high, warn on medium.
 */
class DrugSafetyService
{
    public const SEVERITY_HIGH = 'high';

    public const SEVERITY_MEDIUM = 'medium';

    private function findMedicine(int $instituteId, int $medicineId): ?Medicine
    {
        return Medicine::where('institute_id', $instituteId)->find($medicineId);
    }

    /**
     * Check for drug allergies in a patient.
     */
    public function checkAllergies(Patient $patient, int $medicineId): array
    {
        $medicine = $this->findMedicine((int) $patient->institute_id, $medicineId);

        if (! $medicine) {
            return ['has_allergy' => false, 'message' => null, 'severity' => null];
        }

        $patientAllergies = $patient->allergies ?? '';

        if (trim($patientAllergies) === '') {
            return ['has_allergy' => false, 'message' => null, 'severity' => null];
        }

        $allergyList = array_map('strtolower', array_map('trim', explode(',', $patientAllergies)));

        // Check generic name.
        $genericMatch = in_array(strtolower((string) $medicine->generic_name), $allergyList, true);

        // Check brand name.
        $brandMatch = $medicine->brand_name && in_array(strtolower($medicine->brand_name), $allergyList, true);

        // Check category (e.g. "Penicillin").
        $categoryMatch = $medicine->category && in_array(strtolower($medicine->category), $allergyList, true);

        if ($genericMatch || $brandMatch || $categoryMatch) {
            return [
                'has_allergy' => true,
                'severity' => self::SEVERITY_HIGH,
                'message' => 'Patient has allergy to: '.
                    ($genericMatch ? $medicine->generic_name : '').
                    ($brandMatch ? ' ('.$medicine->brand_name.')' : '').
                    ($categoryMatch ? ' (Category: '.$medicine->category.')' : ''),
            ];
        }

        return ['has_allergy' => false, 'message' => null, 'severity' => null];
    }

    /**
     * Check drug-drug interaction between two medicines.
     */
    public function checkInteraction(int $instituteId, int $medicineId1, int $medicineId2): array
    {
        $med1 = $this->findMedicine($instituteId, $medicineId1);
        $med2 = $this->findMedicine($instituteId, $medicineId2);

        if (! $med1 || ! $med2) {
            return ['has_interaction' => false, 'message' => null, 'severity' => null];
        }

        $generic1 = strtolower(trim((string) $med1->generic_name));
        $generic2 = strtolower(trim((string) $med2->generic_name));

        // Same generic prescribed twice = duplicate therapy.
        if ($generic1 !== '' && $generic1 === $generic2) {
            return [
                'has_interaction' => true,
                'severity' => self::SEVERITY_HIGH,
                'message' => "Duplicate therapy: {$med1->generic_name} prescribed twice",
            ];
        }

        // Same category = possible overlap, warn only.
        $cat1 = strtolower(trim((string) ($med1->category ?? '')));
        $cat2 = strtolower(trim((string) ($med2->category ?? '')));

        if ($cat1 !== '' && $cat1 === $cat2) {
            return [
                'has_interaction' => true,
                'severity' => self::SEVERITY_MEDIUM,
                'message' => "Possible overlap: {$med1->generic_name} and {$med2->generic_name} share category '{$med1->category}'",
            ];
        }

        return ['has_interaction' => false, 'message' => null, 'severity' => null];
    }

    /**
     * Check all pairwise interactions for a set of items.
     */
    public function checkAllInteractions(int $instituteId, Collection $items): array
    {
        $interactions = [];
        $itemsArray = array_values($items->toArray());

        for ($i = 0; $i < count($itemsArray); $i++) {
            for ($j = $i + 1; $j < count($itemsArray); $j++) {
                if (isset($itemsArray[$i]['medicine_id']) && isset($itemsArray[$j]['medicine_id'])) {
                    $result = $this->checkInteraction(
                        $instituteId,
                        (int) $itemsArray[$i]['medicine_id'],
                        (int) $itemsArray[$j]['medicine_id']
                    );
                    if ($result['has_interaction']) {
                        $interactions[] = $result;
                    }
                }
            }
        }

        return $interactions;
    }

    /**
     * Check contraindications for a patient.
     */
    public function checkContraindications(Patient $patient, int $medicineId): array
    {
        $medicine = $this->findMedicine((int) $patient->institute_id, $medicineId);

        if (! $medicine) {
            return ['has_contraindication' => false, 'message' => null, 'severity' => null];
        }

        $contraindications = $patient->chronic_conditions ?? '';

        if (trim($contraindications) === '') {
            return ['has_contraindication' => false, 'message' => null, 'severity' => null];
        }

        $conditions = array_map('trim', explode(',', strtolower($contraindications)));

        // Check if medicine has any known contraindications.
        $medicineContra = strtolower($medicine->contraindications ?? '');

        foreach ($conditions as $condition) {
            if ($condition !== '' && strpos($medicineContra, $condition) !== false) {
                return [
                    'has_contraindication' => true,
                    'severity' => self::SEVERITY_HIGH,
                    'message' => "Contraindication: patient has '{$condition}' which conflicts with {$medicine->generic_name}",
                ];
            }
        }

        return ['has_contraindication' => false, 'message' => null, 'severity' => null];
    }

    /**
     * Full safety check for a prescription.
     *
     * Blocking vs warning is left to the caller; results carry severity so
     * the controller blocks on high (allergies, contraindications,
     * duplicate therapy) and warns on medium (same-category overlap).
     */
    public function fullSafetyCheck(Patient $patient, Collection $items): array
    {
        $instituteId = (int) $patient->institute_id;

        $results = [
            'allergies' => [],
            'interactions' => [],
            'contraindications' => [],
            'blocking' => [],
            'warnings' => [],
            'has_issues' => false,
            'has_blocking_issues' => false,
        ];

        foreach ($items as $item) {
            $medicineId = $item['medicine_id'] ?? null;
            if (! $medicineId) {
                continue;
            }

            // Check allergy.
            $allergy = $this->checkAllergies($patient, (int) $medicineId);
            if ($allergy['has_allergy']) {
                $results['allergies'][] = $allergy;
                $results['blocking'][] = $allergy['message'];
                $results['has_issues'] = true;
                $results['has_blocking_issues'] = true;
            }

            // Check contraindication.
            $contra = $this->checkContraindications($patient, (int) $medicineId);
            if ($contra['has_contraindication']) {
                $results['contraindications'][] = $contra;
                $results['blocking'][] = $contra['message'];
                $results['has_issues'] = true;
                $results['has_blocking_issues'] = true;
            }
        }

        // Check interactions between all items.
        foreach ($this->checkAllInteractions($instituteId, $items) as $interaction) {
            $results['interactions'][] = $interaction;
            $results['has_issues'] = true;
            if (($interaction['severity'] ?? null) === self::SEVERITY_HIGH) {
                $results['blocking'][] = $interaction['message'];
                $results['has_blocking_issues'] = true;
            } else {
                $results['warnings'][] = $interaction['message'];
            }
        }

        return $results;
    }

    /**
     * Institute id for the current request (services should not reach into
     * auth directly; MedicalScope owns that).
     */
    public function currentInstituteId(): int
    {
        return MedicalScope::instituteIdOrFail();
    }
}

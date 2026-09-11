<?php

namespace App\Services\Medical;

use App\Models\Medical\CdsFinding;
use App\Models\Medical\CdsRule;
use App\Models\Medical\CdsRuleVersion;
use App\Models\Medical\Medicine;
use App\Models\Medical\Patient;
use App\Models\Medical\PatientAllergy;
use Illuminate\Support\Collection;

/**
 * Phase 13 — deterministic CDS evaluation (pure: context in, findings out,
 * zero writes). Persistence lives in CdsFindingService; blocking decisions
 * live with the caller (prescription pipeline). Never throws for data
 * problems — those become unevaluated entries or per-rule errors, so a
 * caller can always distinguish NO_FINDING from EVALUATION_ERROR.
 */
final class CdsEngine
{
    /**
     * @param array<int, array{medicine_id?: ?int, medicine_name?: ?string}> $items
     */
    public function evaluate(Patient $patient, array $items, int $instituteId): CdsEvaluation
    {
        $started = microtime(true);

        if ((int) $patient->institute_id !== $instituteId) {
            throw new \InvalidArgumentException('CDS evaluation crossed a tenant boundary.');
        }

        $resolved = $this->resolveItems($items, $instituteId);
        $unevaluated = array_values(array_filter(
            array_map(fn ($r, $i) => $r === null ? $i : null, $resolved, array_keys($resolved))
        ));

        $result = new CdsEvaluation($patient->id, $instituteId);
        $result->unevaluatedItemIndexes = $unevaluated;

        $rules = CdsRule::active()
            ->with(['versions' => fn ($q) => $q->where('status', CdsRule::STATUS_ACTIVE)->orderByDesc('version')])
            ->get()
            ->filter(fn (CdsRule $rule) => $rule->isEffectiveNow());

        foreach ($rules as $rule) {
            $version = $rule->versions->first(fn (CdsRuleVersion $v) => $v->isEffectiveNow());
            if (! $version) {
                continue;
            }
            try {
                foreach ($this->applyRule($rule, $version, $patient, $resolved, $instituteId) as $finding) {
                    $result->addFinding($finding);
                }
            } catch (\Throwable $e) {
                $result->addError($rule->rule_key, $e->getMessage());
                report($e);
            }
        }

        $result->durationMs = (int) round((microtime(true) - $started) * 1000);

        return $result;
    }

    /**
     * Resolve each item to normalized ingredient + RXCUI sets.
     * Returns parallel array (null = insufficient terminology).
     */
    private function resolveItems(array $items, int $instituteId): array
    {
        $ids = collect($items)->pluck('medicine_id')->filter()->unique()->values()->all();
        $medicines = $ids === []
            ? collect()
            : Medicine::where('institute_id', $instituteId)
                ->whereIn('id', $ids)
                ->with(['product.productIngredients.ingredient', 'product.identifiers'])
                ->get()->keyBy('id');

        $out = [];
        foreach (array_values($items) as $item) {
            $medicine = isset($item['medicine_id']) ? ($medicines->get($item['medicine_id']) ?? null) : null;
            if (! $medicine || ! $medicine->product) {
                $out[] = null;
                continue;
            }
            $ingredients = $medicine->product->productIngredients;
            $out[] = [
                'medicine_id' => $medicine->id,
                'medicine_name' => $item['medicine_name'] ?? $medicine->display_name,
                'ingredient_ids' => $ingredients->pluck('medicine_ingredient_id')->map(fn ($v) => (int) $v)->all(),
                'ingredient_names' => $ingredients->map(
                    fn ($p) => MedicineTerminologyService::normalizeName($p->ingredient?->canonical_name)
                )->filter()->values()->all(),
                'rxcuis' => $medicine->product->identifiers
                    ->where('system', 'rxnorm')->where('identifier_type', 'rxcui')
                    ->pluck('value')->map(fn ($v) => (string) $v)->all(),
            ];
        }

        return $out;
    }

    private function applyRule(
        CdsRule $rule,
        CdsRuleVersion $version,
        Patient $patient,
        array $resolved,
        int $instituteId
    ): array {
        return match ($rule->rule_type) {
            CdsRule::TYPE_DUPLICATE_THERAPY => $this->checkDuplicateTherapy($rule, $version, $resolved),
            CdsRule::TYPE_ALLERGY => $this->checkAllergy($rule, $version, $patient, $resolved, $instituteId),
            CdsRule::TYPE_INTERACTION => $this->checkInteraction($rule, $version, $resolved),
            // Taxonomy without an interpreter in Phase 13: recorded as
            // skipped, never as findings (no validated data model yet).
            default => [],
        };
    }

    /**
     * Same active ingredient in two or more items (terminology identity,
     * not string equality — cross-brand duplicates included).
     */
    private function checkDuplicateTherapy(CdsRule $rule, CdsRuleVersion $version, array $resolved): array
    {
        $byIngredient = [];
        foreach ($resolved as $index => $item) {
            if ($item === null) {
                continue;
            }
            foreach ($item['ingredient_ids'] as $ingredientId) {
                $byIngredient[$ingredientId][] = $index;
            }
        }

        $findings = [];
        foreach ($byIngredient as $ingredientId => $indexes) {
            $unique = array_values(array_unique($indexes));
            if (count($unique) < 2) {
                continue;
            }
            $names = array_values(array_unique(array_map(
                fn ($i) => $resolved[$i]['medicine_name'],
                $unique
            )));
            $findings[] = $this->finding(
                $rule,
                $version,
                'Duplicate therapy: '.implode(' + ', $names).' share an active ingredient.',
                [
                    'what' => 'The same active ingredient appears in more than one prescribed item.',
                    'why' => 'Ingredient-level identity from normalized terminology (rule '.$rule->rule_key.' v'.$version->version.').',
                    'evidence' => 'Source: '.$rule->source.' ('.$rule->source_version.')',
                    'action' => 'Review the medication profile before finalizing; remove or justify the duplication.',
                ],
                ['ingredient_id' => $ingredientId, 'item_indexes' => $unique, 'medicine_names' => $names]
            );
        }

        return $findings;
    }

    /**
     * Patient-stated allergies (structured rows + legacy free text) matched
     * against prescribed ingredients. Ingredient/generic/brand exact matches
     * are conflicts; a bare category similarity is an uncertainty INFO
     * finding for review — never a conflict claim.
     */
    private function checkAllergy(
        CdsRule $rule,
        CdsRuleVersion $version,
        Patient $patient,
        array $resolved,
        int $instituteId
    ): array {
        [$allergenIngredients, $categoryTerms] = $this->patientAllergenSets($patient, $instituteId);

        $findings = [];
        foreach ($resolved as $index => $item) {
            if ($item === null) {
                continue;
            }
            $hit = null;
            foreach ($item['ingredient_ids'] as $ingredientId) {
                if (isset($allergenIngredients[$ingredientId])) {
                    $hit = $allergenIngredients[$ingredientId];
                    break;
                }
            }
            if ($hit !== null) {
                $findings[] = $this->finding(
                    $rule,
                    $version,
                    "Allergy conflict: {$item['medicine_name']} contains '{$hit}' recorded as a patient allergy.",
                    [
                        'what' => 'A prescribed item contains an ingredient the patient record flags as an allergen.',
                        'why' => 'Ingredient-level match between allergy record and terminology (rule '.$rule->rule_key.' v'.$version->version.').',
                        'evidence' => 'Source: '.$rule->source.' ('.$rule->source_version.')',
                        'action' => 'Do not finalize without clinician review; confirm or rule out the allergy first.',
                    ],
                    ['allergen' => $hit, 'item_index' => $index, 'medicine_name' => $item['medicine_name']]
                );
                continue;
            }
            // Category-level similarity only: review finding, not a conflict.
            $medicine = isset($item['medicine_id'])
                ? Medicine::where('institute_id', $instituteId)->find($item['medicine_id'])
                : null;
            $category = MedicineTerminologyService::normalizeName($medicine?->category);
            if ($category !== '' && in_array($category, $categoryTerms, true)) {
                $findings[] = $this->finding(
                    $rule,
                    $version,
                    "Possible sensitivity: {$item['medicine_name']} shares category '{$medicine->category}' with a recorded allergy term.",
                    [
                        'what' => 'Only a broad category matches — ingredient identity is unproven.',
                        'why' => 'Uncertainty finding from rule '.$rule->rule_key.' v'.$version->version.' (review, not evidence).',
                        'evidence' => 'Source: '.$rule->source.' ('.$rule->source_version.')',
                        'action' => 'Review; no action required by this finding alone.',
                    ],
                    ['category' => $category, 'item_index' => $index, 'uncertain' => true],
                    'warn'
                );
            }
        }

        return $findings;
    }

    /**
     * @return array{0: array<int, string>, 1: string[]}
     *   ingredient-id → allergen label, plus non-ingredient (category-level)
     *   allergen terms for uncertainty findings.
     */
    private function patientAllergenSets(Patient $patient, int $instituteId): array
    {
        $byIngredient = [];

        try {
            $rows = PatientAllergy::where('institute_id', $instituteId)
                ->where('patient_id', $patient->id)
                ->get();
        } catch (\Throwable) {
            $rows = collect();
        }

        $names = [];
        foreach ($rows as $row) {
            // Direct medicine link resolves to true ingredient identity.
            if ($row->medicine_id) {
                $medicine = Medicine::where('institute_id', $instituteId)->find($row->medicine_id);
                $product = $medicine?->product;
                if ($product) {
                    foreach ($product->productIngredients as $pivot) {
                        $byIngredient[(int) $pivot->medicine_ingredient_id] =
                            $pivot->ingredient?->canonical_name ?? $row->allergen_name;
                    }
                    continue;
                }
            }
            $name = MedicineTerminologyService::normalizeName($row->allergen_name);
            if ($name !== '') {
                $names[] = $name;
            }
        }

        foreach (explode(',', (string) ($patient->allergies ?? '')) as $raw) {
            $name = MedicineTerminologyService::normalizeName($raw);
            if ($name !== '') {
                $names[] = $name;
            }
        }

        // Map allergen names onto known ingredient identities; leftovers are
        // category-level or unknown terms (uncertainty, not conflicts).
        $known = \App\Models\Medical\MedicineIngredient::whereIn('normalized_name', array_unique($names))
            ->pluck('canonical_name', 'id');
        foreach ($known as $id => $canonical) {
            $byIngredient[(int) $id] = $canonical;
        }
        $knownNames = array_map([MedicineTerminologyService::class, 'normalizeName'], $known->values()->all());
        $categoryTerms = array_values(array_diff(array_unique($names), $knownNames));

        return [$byIngredient, $categoryTerms];
    }

    /**
     * Generic pair-set interpreter: definition {"pairs": [[x, y], …]} where
     * each side matches an item ingredient id or item RXCUI. With no active
     * INTERACTION rows this is inert; synthetic fixtures prove the path.
     */
    private function checkInteraction(CdsRule $rule, CdsRuleVersion $version, array $resolved): array
    {
        $pairs = $version->definition['pairs'] ?? null;
        if (! is_array($pairs) || $pairs === []) {
            return [];
        }

        $findings = [];
        $count = count($resolved);
        for ($i = 0; $i < $count; $i++) {
            for ($j = $i + 1; $j < $count; $j++) {
                if ($resolved[$i] === null || $resolved[$j] === null) {
                    continue;
                }
                foreach ($pairs as $pair) {
                    if (! is_array($pair) || count($pair) !== 2) {
                        continue;
                    }
                    if ($this->itemMatches($resolved[$i], $pair[0]) && $this->itemMatches($resolved[$j], $pair[1])) {
                        $findings[] = $this->finding(
                            $rule,
                            $version,
                            "Potential interaction: {$resolved[$i]['medicine_name']} + {$resolved[$j]['medicine_name']}.",
                            [
                                'what' => 'Two prescribed items match a known interaction pair.',
                                'why' => 'Pair definition in rule '.$rule->rule_key.' v'.$version->version.'.',
                                'evidence' => 'Source: '.$rule->source.' ('.$rule->source_version.')',
                                'action' => 'Review the combination before finalizing; this is information, not a directive.',
                            ],
                            ['pair' => array_values($pair), 'item_indexes' => [$i, $j]]
                        );
                    }
                }
            }
        }

        return $findings;
    }

    private function itemMatches(array $item, mixed $token): bool
    {
        if (is_int($token) || (is_string($token) && ctype_digit((string) $token))) {
            return in_array((int) $token, $item['ingredient_ids'], true);
        }

        return in_array((string) $token, $item['rxcuis'], true);
    }

    private function finding(
        CdsRule $rule,
        CdsRuleVersion $version,
        string $message,
        array $explanation,
        array $trigger,
        ?string $blockPolicyOverride = null
    ): array {
        return [
            'rule_id' => $rule->id,
            'rule_version_id' => $version->id,
            'severity' => $rule->severity,
            'block_policy' => $blockPolicyOverride ?? $rule->block_policy,
            'message' => $message,
            'explanation' => $explanation,
            'trigger_data' => $trigger + [
                'rule_key' => $rule->rule_key,
                'rule_version' => $version->version,
            ],
        ];
    }
}

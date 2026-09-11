<?php

namespace Database\Seeders;

use App\Models\Medical\CdsRule;
use App\Models\Medical\CdsRuleVersion;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Phase 13 — seed CDS rules.
 *
 * Only STRUCTURAL rules ship active: same-ingredient duplication and stated
 * allergy→ingredient conflicts are definitional truths over normalized
 * terminology + patient-stated data, not pharmacological claims. Every
 * INTERACTION/CONTRAINDICATION/DOSE rule requires external validated
 * knowledge and is deliberately absent — the engine supports those types,
 * but no active rows exist for them.
 *
 * Governance note: these seeds encode rule SHAPE with source
 * 'accumenai-clinical-governance (seed)'. A production deployment must have
 * its clinical governance board review and re-validate (new versions) before
 * relying on blocking behavior.
 */
class CdsRuleSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $this->seedRule(
                key: 'CDS-DUP-INGREDIENT-001',
                type: CdsRule::TYPE_DUPLICATE_THERAPY,
                severity: CdsRule::SEVERITY_HIGH,
                blockPolicy: CdsRule::POLICY_BLOCK,
                definition: ['mode' => 'shared_ingredient'],
            );
            $this->seedRule(
                key: 'CDS-ALG-INGREDIENT-001',
                type: CdsRule::TYPE_ALLERGY,
                severity: CdsRule::SEVERITY_CRITICAL,
                blockPolicy: CdsRule::POLICY_BLOCK,
                definition: ['match' => 'ingredient', 'include_brand' => true, 'include_category' => false],
            );
        });
    }

    private function seedRule(string $key, string $type, string $severity, string $blockPolicy, array $definition): void
    {
        $rule = CdsRule::firstOrCreate(
            ['rule_key' => $key],
            [
                'rule_type' => $type,
                'severity' => $severity,
                'block_policy' => $blockPolicy,
                'status' => CdsRule::STATUS_ACTIVE,
                'source' => 'accumenai-clinical-governance',
                'source_version' => 'seed-v1',
                'validated_by' => 'clinical-governance seed (requires board sign-off before production reliance)',
                'validated_at' => now(),
                'current_version' => 1,
            ]
        );

        CdsRuleVersion::firstOrCreate(
            ['cds_rule_id' => $rule->id, 'version' => 1],
            [
                'definition' => $definition,
                'status' => CdsRule::STATUS_ACTIVE,
                'validated_by' => $rule->validated_by,
                'validated_at' => $rule->validated_at,
            ]
        );
    }
}

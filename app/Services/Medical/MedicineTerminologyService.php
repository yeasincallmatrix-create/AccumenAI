<?php

namespace App\Services\Medical;

use App\Models\Medical\InstituteMedicine;
use App\Models\Medical\Medicine;
use App\Models\Medical\MedicineConcept;
use App\Models\Medical\MedicineForm;
use App\Models\Medical\MedicineIdentifier;
use App\Models\Medical\MedicineIngredient;
use App\Models\Medical\MedicineProduct;
use App\Models\Medical\MedicineProductIngredient;
use App\Models\Medical\MedicineRoute;

/**
 * Phase 10 — deterministic mapping between legacy `medicines` rows and the
 * normalized terminology layer. Used by the backfill command and the
 * medicine create/update paths. NEVER guesses: ambiguous values keep the
 * legacy raw strings and are reported as unresolved.
 */
final class MedicineTerminologyService
{
    /** Canonical dosage-form vocabulary seeded by the backfill command. */
    public const CANONICAL_FORMS = [
        'Tablet', 'Capsule', 'Syrup', 'Suspension', 'Injection', 'Drops',
        'Cream', 'Ointment', 'Inhaler', 'Sachet', 'Suppository', 'Powder',
        'Gel', 'Lotion', 'Spray', 'Patch',
    ];

    /** Canonical administration-route vocabulary (links stay curated/NULL). */
    public const CANONICAL_ROUTES = [
        'Oral', 'Intravenous', 'Intramuscular', 'Subcutaneous', 'Topical',
        'Ophthalmic', 'Otic', 'Nasal', 'Inhalation', 'Sublingual',
        'Rectal', 'Vaginal',
    ];

    public static function normalizeName(?string $value): string
    {
        return preg_replace('/\s+/', ' ', trim(mb_strtolower((string) $value)));
    }

    /**
     * Split "A + B" style names/strengths into parts.
     *
     * @return string[]
     */
    public static function splitParts(?string $value): array
    {
        return array_values(array_filter(array_map(
            fn ($p) => trim((string) $p),
            explode('+', (string) $value)
        )));
    }

    /**
     * Parse one strength part ("500mg", "100mcg/dose", "5%").
     * Returns [value, unit] or [null, null] when not confidently parseable.
     */
    public static function parseStrengthPart(?string $part): array
    {
        if (! is_string($part) || ! preg_match('/^([\d.]+)\s*([a-zA-Z%\/]+)$/', trim($part), $m)) {
            return [null, null];
        }

        return [(float) $m[1], substr($m[2], 0, 20)];
    }

    public static function seedVocabulary(): void
    {
        foreach (self::CANONICAL_FORMS as $name) {
            MedicineForm::firstOrCreate(['name' => $name], ['status' => 'active']);
        }
        foreach (self::CANONICAL_ROUTES as $name) {
            MedicineRoute::firstOrCreate(['name' => $name], ['status' => 'active']);
        }
    }

    /**
     * Map one legacy medicine row onto the terminology layer.
     *
     * @return array{product: ?MedicineProduct, unresolved: string[]}
     */
    public function mapMedicine(Medicine $medicine): array
    {
        $unresolved = [];

        $genericNames = self::splitParts($medicine->generic_name);
        if ($genericNames === []) {
            return ['product' => null, 'unresolved' => ['empty generic_name']];
        }

        $conceptName = implode(' + ', $genericNames);
        $concept = MedicineConcept::firstOrCreate(
            ['normalized_name' => self::normalizeName($conceptName)],
            [
                'canonical_name' => $conceptName,
                'concept_type' => count($genericNames) > 1 ? 'combination' : 'single',
                'status' => 'active',
            ]
        );

        $ingredients = [];
        foreach ($genericNames as $name) {
            $ingredients[] = MedicineIngredient::firstOrCreate(
                ['normalized_name' => self::normalizeName($name)],
                ['canonical_name' => $name, 'status' => 'active']
            );
        }

        $form = null;
        if (filled($medicine->dosage_form)) {
            $form = MedicineForm::where('name', $medicine->dosage_form)->first();
            if (! $form) {
                $unresolved[] = "unmapped dosage_form '{$medicine->dosage_form}'";
            }
        }

        $strengthParts = self::splitParts($medicine->strength);
        $parsed = array_map([self::class, 'parseStrengthPart'], $strengthParts);
        if ($strengthParts !== [] && count($strengthParts) !== count($genericNames)) {
            // Strength parts do not align with ingredients — keep raw only.
            $unresolved[] = "strength '{$medicine->strength}' does not align with ".count($genericNames).' ingredient(s)';
            $parsed = [];
        }

        $display = trim(implode(' ', array_filter([
            $medicine->generic_name
                ? $medicine->generic_name.($medicine->brand_name ? " ({$medicine->brand_name})" : '')
                : null,
            $medicine->strength,
            $form?->name ?? $medicine->dosage_form,
        ])));
        $normalized = self::normalizeName(
            $concept->normalized_name.'|'.($medicine->strength ?? '').'|'.($form?->name ?? '').'|'.($medicine->brand_name ?? '')
        );

        $product = MedicineProduct::firstOrCreate(
            ['normalized_name' => $normalized],
            [
                'medicine_concept_id' => $concept->id,
                'medicine_form_id' => $form?->id,
                'display_name' => mb_substr($display !== '' ? $display : $concept->canonical_name, 0, 200),
                'strength_raw' => $medicine->strength,
                'brand_name' => $medicine->brand_name,
                'category' => $medicine->category,
                'side_effects' => $medicine->side_effects,
                'contraindications' => $medicine->contraindications,
                'storage_conditions' => $medicine->storage_conditions,
                'requires_prescription' => (bool) $medicine->requires_prescription,
                'is_controlled' => (bool) $medicine->is_controlled,
                'status' => 'active',
            ]
        );

        foreach ($ingredients as $i => $ingredient) {
            $strength = $parsed[$i] ?? [null, null];
            MedicineProductIngredient::firstOrCreate(
                [
                    'medicine_product_id' => $product->id,
                    'medicine_ingredient_id' => $ingredient->id,
                    'sequence' => $i,
                ],
                ['strength_value' => $strength[0], 'strength_unit' => $strength[1]]
            );
        }

        // Migrate registry identifiers already present on the legacy row
        // (never invented — only values the row already carries). A code
        // already linked to a different product signals dirty source data;
        // it is reported, never re-pointed.
        foreach (['dgda_code' => 'code', 'dgda_dar_number' => 'dar_number', 'dgda_concept_id' => 'concept_id'] as $column => $type) {
            $value = trim((string) ($medicine->{$column} ?? ''));
            if ($value === '') {
                continue;
            }
            $existing = MedicineIdentifier::where('system', MedicineIdentifier::SYSTEM_DGDA)
                ->where('identifier_type', $type)
                ->where('value', $value)
                ->first();
            if ($existing && (int) $existing->medicine_product_id !== (int) $product->id) {
                $unresolved[] = "{$column} '{$value}' already linked to another product";
                continue;
            }
            MedicineIdentifier::firstOrCreate(
                ['system' => MedicineIdentifier::SYSTEM_DGDA, 'identifier_type' => $type, 'value' => $value],
                ['medicine_product_id' => $product->id, 'status' => 'active']
            );
        }

        $catalog = InstituteMedicine::firstOrCreate(
            ['institute_id' => $medicine->institute_id, 'local_code' => $medicine->code],
            [
                'medicine_product_id' => $product->id,
                'local_name' => mb_substr($display !== '' ? $display : $concept->canonical_name, 0, 200),
                'preferred' => false,
                'active' => (bool) $medicine->is_active,
                'purchase_price' => $medicine->purchase_price,
                'selling_price' => $medicine->selling_price,
                'vat_percentage' => $medicine->vat_percentage,
                'reorder_level' => $medicine->reorder_level,
                'reorder_quantity' => $medicine->reorder_quantity,
            ]
        );
        if ((int) $catalog->medicine_product_id !== (int) $product->id) {
            $catalog->update(['medicine_product_id' => $product->id]);
        }

        if ((int) ($medicine->medicine_product_id ?? 0) !== (int) $product->id) {
            $medicine->forceFill(['medicine_product_id' => $product->id])->save();
        }

        return ['product' => $product, 'unresolved' => $unresolved];
    }

    /**
     * Immutable snapshot payload for a prescription item at prescribing time.
     * Reads the mapped product when present, otherwise preserves the legacy
     * row's own values — either way the item never depends on future edits.
     */
    public function snapshotFor(Medicine $medicine): array
    {
        $medicine->loadMissing([
            'product.concept', 'product.form', 'product.route',
            'product.identifiers',
        ]);
        $product = $medicine->product;

        $rxnorm = null;
        if ($product) {
            $rxnorm = $product->identifiers
                ->first(fn ($id) => $id->system === MedicineIdentifier::SYSTEM_RXNORM
                    && $id->identifier_type === 'rxcui'
                    && $id->status === 'active')?->value;
        }

        return [
            'medicine_concept_id' => $product?->medicine_concept_id,
            'medicine_product_id' => $product?->id,
            'display_name_snapshot' => mb_substr($product?->display_name ?? $medicine->display_name, 0, 200),
            'strength_snapshot' => $product?->strength_raw ?? $medicine->strength,
            'dosage_form_snapshot' => $product?->form?->name ?? $medicine->dosage_form,
            'route_snapshot' => $product?->route?->name,
            'rxnorm_code_snapshot' => $rxnorm,
        ];
    }
}

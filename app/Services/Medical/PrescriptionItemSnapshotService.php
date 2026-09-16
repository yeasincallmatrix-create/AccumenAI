<?php

namespace App\Services\Medical;

use App\Models\Medical\Medicine;

/**
 * Builds the full snapshot payload for a prescription item at creation time.
 *
 * If a Medicine is resolved, its current fields are copied into snapshot
 * columns so the prescription text survives future medicine edits or deletes.
 * For free-text items the raw input values are used as-is.
 */
class PrescriptionItemSnapshotService
{
    /**
     * Build the complete snapshot array for a prescription item.
     *
     * @param  Medicine|null  $medicine  Resolved medicine (withTrashed) or null for free-text
     * @param  array          $itemData  Raw item input from the request
     * @return array                     Full payload ready for PrescriptionItem::create()
     */
    public static function build(?Medicine $medicine, array $itemData): array
    {
        // Base: user-supplied clinical fields (preserved as-is).
        $payload = [
            'prescription_id'          => $itemData['prescription_id'] ?? null,
            'medicine_id'              => null,
            'medicine_name'            => $itemData['medicine_name'] ?? null,
            'dgda_code'                => $itemData['dgda_code'] ?? null,
            // Terminology layer IDs
            'medicine_concept_id'      => $itemData['medicine_concept_id'] ?? null,
            'medicine_product_id'      => $itemData['medicine_product_id'] ?? null,
            // Snapshot columns
            'display_name_snapshot'    => $itemData['display_name_snapshot'] ?? null,
            'generic_name_snapshot'    => $itemData['generic_name_snapshot'] ?? null,
            'strength_snapshot'        => $itemData['strength_snapshot'] ?? null,
            'dosage_form_snapshot'     => $itemData['dosage_form_snapshot'] ?? null,
            'route_snapshot'           => $itemData['route_snapshot'] ?? null,
            'rxnorm_code_snapshot'     => $itemData['rxnorm_code_snapshot'] ?? null,
            'unit_snapshot'            => $itemData['unit_snapshot'] ?? null,
            'pack_size_snapshot'       => $itemData['pack_size_snapshot'] ?? null,
            'category_snapshot'        => $itemData['category_snapshot'] ?? null,
            // Clinical fields (always from input)
            'dosage'                   => $itemData['dosage'] ?? null,
            'frequency'                => $itemData['frequency'] ?? null,
            'duration_days'            => $itemData['duration_days'] ?? null,
            'quantity'                 => $itemData['quantity'] ?? 1,
            'special_instructions'     => $itemData['special_instructions'] ?? null,
            'status'                   => $itemData['status'] ?? 'pending',
            'item_status'              => $itemData['item_status'] ?? 'active',
            'discontinued_reason'      => $itemData['discontinued_reason'] ?? null,
            'discontinued_at'          => $itemData['discontinued_at'] ?? null,
            'continued_from_item_id'   => $itemData['continued_from_item_id'] ?? null,
        ];

        if ($medicine) {
            $payload['medicine_id'] = $medicine->id;

            // Use existing terminology service for concept/product IDs and
            // core snapshots (display_name, strength, dosage_form, route, rxnorm).
            $terminologySnap = app(MedicineTerminologyService::class)->snapshotFor($medicine);
            foreach ($terminologySnap as $key => $value) {
                if (empty($payload[$key])) {
                    $payload[$key] = $value;
                }
            }

            // Additional fields not covered by the terminology layer.
            if (empty($payload['medicine_name'])) {
                $payload['medicine_name'] = $medicine->brand_name
                    ?? $medicine->generic_name
                    ?? 'Unknown';
            }
            if (empty($payload['generic_name_snapshot'])) {
                $payload['generic_name_snapshot'] = $medicine->generic_name;
            }
            if (empty($payload['unit_snapshot'])) {
                $payload['unit_snapshot'] = $medicine->unit;
            }
            if (empty($payload['pack_size_snapshot'])) {
                $payload['pack_size_snapshot'] = $medicine->pack_size;
            }
            if (empty($payload['category_snapshot'])) {
                $payload['category_snapshot'] = $medicine->category;
            }
            if (empty($payload['dgda_code'])) {
                $payload['dgda_code'] = $medicine->dgda_code;
            }
        }

        return array_filter($payload, fn ($v) => $v !== null);
    }

    /**
     * Alias for build() — merges snapshot into the existing item data array.
     * Compatible with PrescriptionService call sites.
     */
    public static function merge(?Medicine $medicine, array $itemData): array
    {
        return self::build($medicine, $itemData);
    }
}

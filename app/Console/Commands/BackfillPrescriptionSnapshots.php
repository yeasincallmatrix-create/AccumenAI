<?php

namespace App\Console\Commands;

use App\Models\Medical\Medicine;
use App\Models\Medical\PrescriptionItem;
use Illuminate\Console\Command;

class BackfillPrescriptionSnapshots extends Command
{
    protected $signature = 'medical:backfill-prescription-snapshots';

    protected $description = 'Backfill missing snapshot columns on existing prescription items from their linked medicines';

    public function handle(): int
    {
        $count = 0;

        PrescriptionItem::whereNotNull('medicine_id')
            ->with(['medicine' => fn ($q) => $q->withTrashed()])
            ->chunkById(100, function ($items) use (&$count) {
                foreach ($items as $item) {
                    $medicine = $item->medicine;
                    if (! $medicine) {
                        continue;
                    }

                    // Load product relationships for terminology-based route/rxnorm.
                    $medicine->loadMissing([
                        'product.concept', 'product.form', 'product.route', 'product.identifiers',
                    ]);

                    $updates = [];

                    if (empty($item->display_name_snapshot) && $medicine->brand_name) {
                        $updates['display_name_snapshot'] = $medicine->brand_name;
                    }
                    if (empty($item->generic_name_snapshot) && $medicine->generic_name) {
                        $updates['generic_name_snapshot'] = $medicine->generic_name;
                    }
                    if (empty($item->strength_snapshot) && $medicine->strength) {
                        $updates['strength_snapshot'] = $medicine->strength;
                    }
                    if (empty($item->dosage_form_snapshot) && $medicine->dosage_form) {
                        $updates['dosage_form_snapshot'] = $medicine->dosage_form;
                    }
                    if (empty($item->route_snapshot) && $medicine->product?->route?->name) {
                        $updates['route_snapshot'] = $medicine->product->route->name;
                    }
                    if (empty($item->unit_snapshot) && $medicine->unit) {
                        $updates['unit_snapshot'] = $medicine->unit;
                    }
                    if (empty($item->pack_size_snapshot) && $medicine->pack_size) {
                        $updates['pack_size_snapshot'] = $medicine->pack_size;
                    }
                    if (empty($item->category_snapshot) && $medicine->category) {
                        $updates['category_snapshot'] = $medicine->category;
                    }
                    if (empty($item->dgda_code) && $medicine->dgda_code) {
                        $updates['dgda_code'] = $medicine->dgda_code;
                    }
                    if (empty($item->rxnorm_code_snapshot) && $medicine->product) {
                        $rxnorm = $medicine->product->identifiers
                            ->first(fn ($id) => $id->system === \App\Models\Medical\MedicineIdentifier::SYSTEM_RXNORM
                                && $id->identifier_type === 'rxcui'
                                && $id->status === 'active')?->value;
                        if ($rxnorm) {
                            $updates['rxnorm_code_snapshot'] = $rxnorm;
                        }
                    }

                    if (! empty($updates)) {
                        $item->update($updates);
                        $count++;
                    }
                }
            });

        $this->info("Backfilled {$count} prescription items.");

        return Command::SUCCESS;
    }
}

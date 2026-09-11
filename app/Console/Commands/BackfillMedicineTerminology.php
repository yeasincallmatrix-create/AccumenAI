<?php

namespace App\Console\Commands;

use App\Models\Medical\Medicine;
use App\Models\Medical\PrescriptionItem;
use App\Services\Medical\MedicineTerminologyService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Phase 10 — deterministic backfill from legacy `medicines` rows into the
 * normalized terminology layer. Idempotent and resumable (rows already
 * linked are skipped); ambiguous values are never guessed — they keep
 * legacy raw strings and are reported as unresolved.
 */
class BackfillMedicineTerminology extends Command
{
    protected $signature = 'medical:backfill-medicine-terminology
        {--dry-run : resolve mappings without persisting}
        {--limit=500 : max legacy rows per run}';

    protected $description = 'Map legacy medicines onto terminology concepts/products/catalog (Phase 10)';

    public function handle(MedicineTerminologyService $service): int
    {
        MedicineTerminologyService::seedVocabulary();

        $limit = max(1, (int) $this->option('limit'));
        $dryRun = (bool) $this->option('dry-run');

        $stats = ['examined' => 0, 'mapped' => 0, 'unresolved' => 0, 'items' => 0];
        $unresolvedSamples = [];

        $work = function () use ($service, $limit, &$stats, &$unresolvedSamples) {
            Medicine::whereNull('medicine_product_id')->orderBy('id')->limit($limit)->each(
                function (Medicine $medicine) use ($service, &$stats, &$unresolvedSamples) {
                    $stats['examined']++;
                    $result = $service->mapMedicine($medicine);
                    if ($result['product']) {
                        $stats['mapped']++;
                    }
                    if ($result['unresolved'] !== []) {
                        $stats['unresolved']++;
                        if (count($unresolvedSamples) < 10) {
                            $unresolvedSamples[] = "#{$medicine->id} {$medicine->code}: ".implode('; ', $result['unresolved']);
                        }
                    }
                }
            );

            // Snapshot backfill for items written before snapshots existed.
            PrescriptionItem::whereNotNull('medicine_id')
                ->whereNull('display_name_snapshot')
                ->orderBy('id')
                ->limit($limit)
                ->each(function (PrescriptionItem $item) use ($service, &$stats) {
                    $medicine = Medicine::find($item->medicine_id);
                    if (! $medicine) {
                        return;
                    }
                    if (! $medicine->medicine_product_id) {
                        $service->mapMedicine($medicine->fresh());
                        $medicine->refresh();
                    }
                    $item->forceFill($service->snapshotFor($medicine))->save();
                    $stats['items']++;
                });
        };

        if ($dryRun) {
            try {
                DB::transaction(function () use ($work) {
                    $work();
                    throw new \RuntimeException('dry-run rollback');
                });
            } catch (\RuntimeException $e) {
                if ($e->getMessage() !== 'dry-run rollback') {
                    throw $e;
                }
            }
            $this->info('[dry-run] no rows persisted.');
        } else {
            $work();
        }

        $this->table(
            ['metric', 'count'],
            [
                ['medicines examined', $stats['examined']],
                ['medicines mapped', $stats['mapped']],
                ['medicines unresolved (kept legacy)', $stats['unresolved']],
                ['prescription items snapshotted', $stats['items']],
            ]
        );
        foreach ($unresolvedSamples as $sample) {
            $this->warn('unresolved: '.$sample);
        }

        return self::SUCCESS;
    }
}

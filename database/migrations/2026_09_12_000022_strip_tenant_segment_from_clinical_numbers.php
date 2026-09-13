<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Rewrite existing clinical numbers to the tenant-segment-free shape.
 *
 * Stored legacy rows look like MR-2026-189-00002 (the 189 is the tenant
 * id); new allocations store MR-2026-00002 and display MR-26-00002. This
 * migration converts every legacy row to the stored shape so old and new
 * rows read identically:
 *
 *   PREFIX-YYYY-III-NNNNN  →  PREFIX-YYYY-NNNNN   (year + sequence kept)
 *
 * Safety:
 * - Only rows matching the legacy pattern are touched (manual numbers
 *   like '26474' or 'MR-TEST-1', and already-converted rows, are left
 *   alone), so the migration is idempotent — re-running is a no-op.
 * - Conversion keeps the trailing 5-digit sequence, so per-tenant yearly
 *   counters (number_sequences) stay correct without adjustment.
 * - Uniqueness is per tenant (composite institute_id + number keys from
 *   000021): if a stripped value already exists in the same tenant, that
 *   row is SKIPPED (never overwritten) and reported at the end.
 * - Audit-trail snapshots keep their historical text; only the live
 *   identifier columns are rewritten. Finalized prescriptions carry no
 *   automated re-verification against the stored number, so printed QR
 *   payloads remain informational.
 */
return new class extends Migration
{
    /**
     * @var array<int, array{table: string, column: string, prefix: string}>
     */
    private array $targets = [
        ['table' => 'patients', 'column' => 'mr_number', 'prefix' => 'MR'],
        ['table' => 'prescriptions', 'column' => 'prescription_number', 'prefix' => 'RX'],
        ['table' => 'lab_orders', 'column' => 'order_number', 'prefix' => 'LAB'],
        ['table' => 'medical_invoices', 'column' => 'invoice_number', 'prefix' => 'INV'],
        ['table' => 'tpa_claims', 'column' => 'claim_number', 'prefix' => 'TPA'],
        ['table' => 'medical_encounters', 'column' => 'encounter_number', 'prefix' => 'ENC'],
    ];

    public function up(): void
    {
        $totalUpdated = 0;
        $totalSkipped = 0;

        foreach ($this->targets as $target) {
            [$updated, $skipped] = $this->convertTable($target['table'], $target['column'], $target['prefix']);
            $totalUpdated += $updated;
            $totalSkipped += $skipped;
            $this->info("{$target['table']}: updated={$updated} skipped={$skipped}");
        }

        $this->info("Clinical number conversion complete: updated={$totalUpdated} skipped={$totalSkipped}");
    }

    /**
     * The migration has no meaningful down: re-adding a tenant segment
     * cannot distinguish converted rows from natively new rows, and the
     * old global-unique schema it belonged to no longer exists. Rolling
     * back only removes the batch record; data is intentionally kept.
     */
    public function down(): void
    {
        $this->info('No data rollback: converted clinical numbers are intentionally kept.');
    }

    /**
     * @return array{int, int} [updated, skipped]
     */
    private function convertTable(string $table, string $column, string $prefix): array
    {
        $pattern = '^'.$prefix.'-[0-9]{4}-[0-9]{3,}-[0-9]{5}$';
        $updated = 0;
        $skipped = 0;

        DB::table($table)
            ->select(['id', 'institute_id', $column])
            ->where($column, 'REGEXP', $pattern)
            ->orderBy('id')
            ->chunkById(500, function ($rows) use ($table, $column, &$updated, &$skipped) {
                foreach ($rows as $row) {
                    $old = (string) $row->{$column};
                    if (! preg_match('/^([A-Z]+)-(\d{4})-\d{3,}-(\d{5})$/', $old, $m)) {
                        continue;
                    }
                    $new = $m[1].'-'.$m[2].'-'.$m[3];

                    // Same tenant already holds the stripped value: keep
                    // both rows untouched rather than colliding.
                    $conflict = DB::table($table)
                        ->where('institute_id', $row->institute_id)
                        ->where($column, $new)
                        ->where('id', '!=', $row->id)
                        ->exists();

                    if ($conflict) {
                        $skipped++;
                        continue;
                    }

                    DB::table($table)->where('id', $row->id)->update([$column => $new]);
                    $updated++;
                }
            });

        return [$updated, $skipped];
    }

    private function info(string $message): void
    {
        if ($this->command ?? null) {
            $this->command->info($message);
        }
    }
};

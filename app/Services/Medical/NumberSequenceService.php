<?php

namespace App\Services\Medical;

use App\Models\Medical\Invoice;
use App\Models\Medical\LabOrder;
use App\Models\Medical\NumberSequence;
use App\Models\Medical\Patient;
use App\Models\Medical\Prescription;
use App\Models\Medical\TpaClaim;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Phase 04 — Central clinical numbering (revised: tenant segment removed).
 *
 * One row-locked counter per (institute, type, year); all HMS numbers share
 * the STORED shape PREFIX-YYYY-NNNNN. The institute id is NOT embedded in
 * the number anymore: numbers are unique per tenant via composite DB keys
 * (institute_id + number), so the old -III- segment (e.g. the 189 in
 * MR-2026-189-00002) was redundant.
 *
 * Display shape is PREFIX-YY-NNNNN (e.g. MR-26-00002): the database keeps
 * the full 4-digit year for unambiguous ordering, while every human-facing
 * surface renders the 2-digit year via display(). Search/scan input in
 * either shape is accepted via toStored()/expandShortYears().
 *
 * Concurrency: allocation runs SELECT … FOR UPDATE inside a
 * transaction (Laravel nests this in the caller's transaction via savepoint,
 * so the lock releases with the clinical write — no orphan increments on
 * rollback of the outer unit of work).
 *
 * Gap policy (explicit): numbers are UNIQUE (per tenant) and roughly
 * ordered, NOT gapless. A number is retired forever once allocated:
 * deleted/voided records (e.g. draft prescriptions) leave holes that are
 * never refilled. First allocation of a series backfills from the highest
 * existing suffix so historical identifiers are never renumbered, reused
 * or collided with (legacy PREFIX-YYYY-III-NNNNN rows included).
 */
final class NumberSequenceService
{
    /**
     * Allocate the next number and return it formatted.
     */
    public function next(string $type, int $instituteId): string
    {
        $year = (int) now()->format('Y');
        $n = $this->allocate($type, $instituteId, $year);

        return self::format($type, $instituteId, $year, $n);
    }

    /**
     * Non-consuming estimate of the next number (for form previews).
     * Never use for assignment — concurrent allocations may land first.
     */
    public function peek(string $type, int $instituteId): string
    {
        $year = (int) now()->format('Y');
        $row = NumberSequence::where('institute_id', $instituteId)
            ->where('sequence_type', $type)
            ->where('year', $year)
            ->first();

        $n = $row ? ((int) $row->last_number + 1) : ($this->backfill($type, $instituteId, $year) + 1);

        return self::format($type, $instituteId, $year, $n);
    }

    public static function format(string $type, int $instituteId, int $year, int $n): string
    {
        $prefix = match ($type) {
            NumberSequence::TYPE_MR => 'MR',
            NumberSequence::TYPE_PRESCRIPTION => 'RX',
            NumberSequence::TYPE_LAB_ORDER => 'LAB',
            NumberSequence::TYPE_INVOICE => 'INV',
            NumberSequence::TYPE_TPA_CLAIM => 'TPA',
            NumberSequence::TYPE_ENCOUNTER => 'ENC',
            default => throw new \InvalidArgumentException("Unknown sequence type [{$type}]."),
        };

        // Stored shape: PREFIX-YYYY-NNNNN (no tenant segment — uniqueness
        // is per tenant via composite DB keys). $instituteId is retained in
        // the signature because counters stay per (institute, type, year).
        return sprintf('%s-%d-%05d', $prefix, $year, $n);
    }

    /**
     * Human-facing shape: PREFIX-YY-NNNNN (e.g. MR-2026-00002 →
     * MR-26-00002). Legacy PREFIX-YYYY-III-NNNNN rows shorten the same way
     * (MR-2026-189-00002 → MR-26-00002). Anything unrecognized passes
     * through untouched (legacy bare numerics, manual MR-TEST-1 rows).
     */
    public static function display(?string $stored): string
    {
        if (! is_string($stored) || $stored === '') {
            return (string) $stored;
        }
        if (preg_match('/^(MR|RX|LAB|INV|TPA|ENC)-(\d{4})-(?:\d{3,}-)?(\d{5})$/', $stored, $m)) {
            return $m[1].'-'.substr($m[2], 2).'-'.$m[3];
        }

        return $stored;
    }

    /**
     * Normalize scan/search input to the stored shape: display short form
     * (MR-26-00002) → stored long form (MR-2026-00002, 2000s century like
     * the year column), legacy tenant form (MR-2026-189-00002) → stored
     * form (MR-2026-00002). Anything else passes through untouched.
     */
    public static function toStored(string $input): string
    {
        $input = trim($input);
        if (preg_match('/^(MR|RX|LAB|INV|TPA|ENC)-(\d{2})-(\d{5})$/', $input, $m)) {
            return $m[1].'-20'.$m[2].'-'.$m[3];
        }
        if (preg_match('/^(MR|RX|LAB|INV|TPA|ENC)-(\d{4})-\d{3,}-(\d{5})$/', $input, $m)) {
            return $m[1].'-'.$m[2].'-'.$m[3];
        }

        return $input;
    }

    /**
     * Expand short-year occurrences inside a free-text search term so a
     * LIKE against stored long-form numbers still hits: "MR-26-00002" →
     * "MR-2026-00002", "MR-26" → "MR-2026". Four-digit years are never
     * touched (the (\d{2})(?!\d) guard), nor are non-clinical digits.
     */
    public static function expandShortYears(string $term): string
    {
        return (string) preg_replace_callback(
            '/\b(MR|RX|LAB|INV|TPA|ENC)-(\d{2})(?!\d)/',
            fn (array $m): string => $m[1].'-20'.$m[2],
            $term
        );
    }

    /**
     * Atomically increment and return the new counter value. Retries once
     * wave of concurrent first-creates collide on the unique key.
     */
    private function allocate(string $type, int $instituteId, int $year): int
    {
        $attempts = 0;

        while (true) {
            $attempts++;

            try {
                return DB::transaction(function () use ($type, $instituteId, $year) {
                    $seq = NumberSequence::where('institute_id', $instituteId)
                        ->where('sequence_type', $type)
                        ->where('year', $year)
                        ->lockForUpdate()
                        ->first();

                    if (! $seq) {
                        $seq = NumberSequence::create([
                            'institute_id' => $instituteId,
                            'sequence_type' => $type,
                            'year' => $year,
                            'last_number' => $this->backfill($type, $instituteId, $year),
                        ]);
                    }

                    $seq->last_number = (int) $seq->last_number + 1;
                    $seq->save();

                    return (int) $seq->last_number;
                });
            } catch (QueryException $e) {
                // Concurrent first allocation for the same series: the loser
                // re-reads the winner's row on retry.
                if ($attempts >= 3 || $e->getCode() !== '23000') {
                    throw $e;
                }
            }
        }
    }

    /**
     * Highest suffix already present in clinical rows for this series, so a
     * fresh counter continues history instead of colliding with it. MR has
     * no parseable predecessors (legacy rows are bare 5-digit numerics in a
     * disjoint namespace), so it always starts at zero. The LIKE prefix is
     * the year only (PREFIX-YYYY-) so BOTH stored PREFIX-YYYY-NNNNN rows
     * and legacy PREFIX-YYYY-III-NNNNN rows are seen; the trailing-5-digit
     * regex then continues past whichever is highest.
     */
    private function backfill(string $type, int $instituteId, int $year): int
    {
        if ($type === NumberSequence::TYPE_MR) {
            return 0;
        }

        [$model, $column, $prefix] = match ($type) {
            NumberSequence::TYPE_PRESCRIPTION => [Prescription::class, 'prescription_number', "RX-{$year}-"],
            NumberSequence::TYPE_LAB_ORDER => [LabOrder::class, 'order_number', "LAB-{$year}-"],
            NumberSequence::TYPE_INVOICE => [Invoice::class, 'invoice_number', "INV-{$year}-"],
            NumberSequence::TYPE_TPA_CLAIM => [TpaClaim::class, 'claim_number', "TPA-{$year}-"],
            NumberSequence::TYPE_ENCOUNTER => [\App\Models\Medical\Encounter::class, 'encounter_number', "ENC-{$year}-"],
        };

        // Soft-deleted rows keep their numbers reserved too (where supported).
        $query = $model::where('institute_id', $instituteId)
            ->where($column, 'LIKE', $prefix.'%');
        if (in_array(\Illuminate\Database\Eloquent\SoftDeletes::class, class_uses_recursive($model), true)) {
            $query->withTrashed();
        }
        $max = $query->max($column);

        if (! is_string($max) || ! preg_match('/(\d{5})$/', $max, $m)) {
            return 0;
        }

        return (int) $m[1];
    }
}

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
 * Phase 04 — Central clinical numbering.
 *
 * One row-locked counter per (institute, type, year); all HMS numbers share
 * the shape PREFIX-YYYY-III-NNNNN (institute id left-padded to 3, sequence
 * to 5). Concurrency: allocation runs SELECT … FOR UPDATE inside a
 * transaction (Laravel nests this in the caller's transaction via savepoint,
 * so the lock releases with the clinical write — no orphan increments on
 * rollback of the outer unit of work).
 *
 * Gap policy (explicit): numbers are UNIQUE and roughly ordered, NOT
 * gapless. A number is retired forever once allocated: deleted/voided
 * records (e.g. draft prescriptions) leave holes that are never refilled.
 * First allocation of a series backfills from the highest existing suffix
 * so historical identifiers are never renumbered, reused or collided with.
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

        return sprintf('%s-%d-%03d-%05d', $prefix, $year, $instituteId, $n);
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
     * disjoint namespace), so it always starts at zero.
     */
    private function backfill(string $type, int $instituteId, int $year): int
    {
        if ($type === NumberSequence::TYPE_MR) {
            return 0;
        }

        [$model, $column, $prefix] = match ($type) {
            NumberSequence::TYPE_PRESCRIPTION => [Prescription::class, 'prescription_number', "RX-{$year}-".str_pad((string) $instituteId, 3, '0', STR_PAD_LEFT).'-'],
            NumberSequence::TYPE_LAB_ORDER => [LabOrder::class, 'order_number', "LAB-{$year}-".str_pad((string) $instituteId, 3, '0', STR_PAD_LEFT).'-'],
            NumberSequence::TYPE_INVOICE => [Invoice::class, 'invoice_number', "INV-{$year}-".str_pad((string) $instituteId, 3, '0', STR_PAD_LEFT).'-'],
            NumberSequence::TYPE_TPA_CLAIM => [TpaClaim::class, 'claim_number', "TPA-{$year}-".str_pad((string) $instituteId, 3, '0', STR_PAD_LEFT).'-'],
            NumberSequence::TYPE_ENCOUNTER => [\App\Models\Medical\Encounter::class, 'encounter_number', "ENC-{$year}-".str_pad((string) $instituteId, 3, '0', STR_PAD_LEFT).'-'],
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

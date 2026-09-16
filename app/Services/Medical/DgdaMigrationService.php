<?php

namespace App\Services\Medical;

use App\Models\Medical\DgdaRegistration;
use App\Models\Medical\Medicine;
use Illuminate\Support\Facades\DB;

class DgdaMigrationService
{
    /**
     * Analyze custom medicines against DGDA registry.
     */
    public function analyze(int $instituteId): array
    {
        $custom = Medicine::where('institute_id', $instituteId)
            ->whereNull('deleted_at')
            ->whereNull('dgda_code')
            ->get();

        $autoMatched = [];
        $needReview = [];
        $noMatch = [];

        foreach ($custom as $med) {
            $normalized = preg_replace('/[^a-z0-9]/', '', strtolower(trim(($med->brand_name ?? '').' '.($med->strength ?? ''))));

            // Exact match by DAR number first
            if (! empty($med->dgda_dar_number)) {
                $exact = DgdaRegistration::where('dar_number', $med->dgda_dar_number)
                    ->where('match_status', '!=', 'invalid')
                    ->first();
                if ($exact) {
                    $autoMatched[] = ['medicine' => $med, 'match' => $exact];
                    continue;
                }
            }

            // Fuzzy match by brand_name + strength
            $fuzzy = DgdaRegistration::where(function ($q) use ($med) {
                $q->where('brand_name', 'like', '%'.($med->brand_name ?? '').'%');
            })
                ->where(function ($q) use ($med) {
                    if (! empty($med->strength)) {
                        $q->where('strength_raw', 'like', '%'.$med->strength.'%');
                    }
                })
                ->where('match_status', '!=', 'invalid')
                ->limit(5)
                ->get();

            if ($fuzzy->count() === 1) {
                $autoMatched[] = ['medicine' => $med, 'match' => $fuzzy->first()];
            } elseif ($fuzzy->count() > 1) {
                $needReview[] = ['medicine' => $med, 'candidates' => $fuzzy];
            } else {
                $noMatch[] = ['medicine' => $med];
            }
        }

        return [
            'total' => $custom->count(),
            'auto_matched' => $autoMatched,
            'need_review' => $needReview,
            'no_match' => $noMatch,
        ];
    }

    /**
     * Apply auto-matched migrations.
     */
    public function applyAuto(int $instituteId): int
    {
        $analysis = $this->analyze($instituteId);
        $count = 0;

        DB::transaction(function () use ($analysis, &$count) {
            foreach ($analysis['auto_matched'] as $item) {
                $med = $item['medicine'];
                $match = $item['match'];
                $med->update([
                    'dgda_code' => $match->dar_number,
                    'dgda_dar_number' => $match->dar_number,
                    'dgda_synced_at' => now(),
                ]);
                $count++;
            }
        });

        return $count;
    }

    /**
     * Apply a single manual match.
     */
    public function applyManual(int $medicineId, int $dgdaRegistrationId): bool
    {
        $med = Medicine::whereNull('deleted_at')->find($medicineId);
        $reg = DgdaRegistration::find($dgdaRegistrationId);

        if (! $med || ! $reg) {
            return false;
        }

        $med->update([
            'dgda_code' => $reg->dar_number,
            'dgda_dar_number' => $reg->dar_number,
            'dgda_synced_at' => now(),
        ]);

        return true;
    }
}

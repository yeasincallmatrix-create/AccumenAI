<?php

namespace App\Services\Medical;

use App\Models\Medical\Medicine;
use Illuminate\Support\Collection;

class MedicineDuplicateService
{
    /**
     * Find potential duplicates for a brand name + strength within an institute,
     * using normalized_name for case-insensitive, punctuation-insensitive matching.
     */
    public function findDuplicates(int $instituteId, ?string $brandName, ?string $strength = null): Collection
    {
        $normalized = preg_replace(
            '/[^a-z0-9]/',
            '',
            strtolower(trim(($brandName ?? '').' '.($strength ?? '')))
        );

        if ($normalized === '') {
            return collect();
        }

        return Medicine::where('institute_id', $instituteId)
            ->where('normalized_name', $normalized)
            ->limit(5)
            ->get();
    }

    /**
     * Check if a normalized_name already exists in the institute.
     */
    public function exists(int $instituteId, ?string $brandName, ?string $strength = null, ?int $excludeId = null): bool
    {
        $query = $this->findDuplicates($instituteId, $brandName, $strength);

        if ($excludeId) {
            $query = $query->reject(fn ($m) => $m->id === $excludeId);
        }

        return $query->isNotEmpty();
    }
}

<?php

namespace App\Services;

use App\Models\CertificateType;
use App\Models\Institute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

/**
 * CRUD service for institute-scoped certificate types.
 *
 * Certificate types are simple configurable labels (Course Completion,
 * Graduation, Training, Achievement, etc.) that let institutes categorize
 * their certificates. Types are soft-deletable and tenant-isolated.
 */
class CertificateTypeService
{
    public function create(int $instituteId, array $data): CertificateType
    {
        $slug = Str::slug($data['name']);

        $seq = 1;
        $base = $slug;
        while (CertificateType::query()->where('institute_id', $instituteId)->where('slug', $slug)->exists()) {
            $slug = $base.'-'.($seq++);
        }

        return CertificateType::create(array_merge($data, [
            'institute_id' => $instituteId,
            'slug' => $slug,
        ]));
    }

    public function update(CertificateType $type, array $data): CertificateType
    {
        if (isset($data['name']) && $data['name'] !== $type->name) {
            $slug = Str::slug($data['name']);
            $base = $slug;
            $seq = 1;
            while (CertificateType::query()
                ->where('institute_id', $type->institute_id)
                ->where('slug', $slug)
                ->where('id', '!=', $type->id)
                ->exists()
            ) {
                $slug = $base.'-'.($seq++);
            }
            $data['slug'] = $slug;
        }

        $type->update($data);

        return $type->fresh();
    }

    public function destroy(CertificateType $type): void
    {
        $type->delete();
    }

    public function activeFor(int $instituteId): Collection
    {
        return CertificateType::query()
            ->where('institute_id', $instituteId)
            ->where('is_active', true)
            ->orderBy('display_order')
            ->orderBy('name')
            ->get();
    }
}

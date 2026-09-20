<?php

namespace App\Services\Accounting;

use App\Models\Shareholder;

class ShareholderService
{
    public function create(int $instituteId, array $data): Shareholder
    {
        $data['institute_id'] = $instituteId;

        return Shareholder::create($data);
    }

    public function update(Shareholder $shareholder, array $data): Shareholder
    {
        unset($data['institute_id']);
        $shareholder->update($data);

        return $shareholder->fresh();
    }

    public function delete(Shareholder $shareholder): void
    {
        $shareholder->delete();
    }

    public function totalShares(int $instituteId): int
    {
        return (int) Shareholder::where('institute_id', $instituteId)->sum('shares');
    }

    public function totalPaidUp(int $instituteId): float
    {
        return (float) Shareholder::where('institute_id', $instituteId)
            ->selectRaw('SUM(shares * face_value) as total')
            ->value('total') ?? 0;
    }
}

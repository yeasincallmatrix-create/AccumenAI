<?php

namespace App\Services\Accounting;

use App\Models\ChartOfAccount;
use App\Models\Partner;
use Illuminate\Support\Facades\DB;

class PartnerService
{
    /**
     * Create partner with auto-generated COA accounts.
     * Capital account: 3110, 3120, 3130...
     * Drawing account: 3210, 3220, 3230...
     */
    public function create(int $instituteId, array $data): Partner
    {
        return DB::transaction(function () use ($instituteId, $data) {
            $sequence = Partner::where('institute_id', $instituteId)->count() + 1;

            $capitalCode = '31'.str_pad((string) ($sequence * 10), 2, '0', STR_PAD_LEFT);
            $drawingCode = '32'.str_pad((string) ($sequence * 10), 2, '0', STR_PAD_LEFT);

            $capitalAccount = $this->createPartnerAccount(
                $instituteId, $capitalCode,
                $data['name'].' - Capital', 'equity'
            );

            $drawingAccount = $this->createPartnerAccount(
                $instituteId, $drawingCode,
                $data['name'].' - Drawings', 'equity'
            );

            $data['institute_id'] = $instituteId;
            $data['capital_account_id'] = $capitalAccount->id;
            $data['drawing_account_id'] = $drawingAccount->id;

            return Partner::create($data);
        });
    }

    public function update(Partner $partner, array $data): Partner
    {
        // Prevent ownership change
        unset($data['institute_id'], $data['capital_account_id'], $data['drawing_account_id']);
        $partner->update($data);

        return $partner->fresh();
    }

    public function delete(Partner $partner): void
    {
        DB::transaction(function () use ($partner) {
            // Keep accounts (audit trail); just soft-unlink
            Partner::where('id', $partner->id)->update([
                'capital_account_id' => null,
                'drawing_account_id' => null,
            ]);
            $partner->delete();
        });
    }

    protected function createPartnerAccount(
        int $instituteId, string $code, string $name, string $type
    ): ChartOfAccount {
        // Ensure code is unique within this tenant
        $code = $this->uniqueCode($instituteId, $code);

        // account_group_id is NOT NULL — prefer tenant equity group, fall back global.
        $groupId = \App\Models\AccountGroup::withoutGlobalScope('institute')
            ->where('institute_id', $instituteId)
            ->where('category', 'equity')
            ->value('id')
            ?? \App\Models\AccountGroup::withoutGlobalScope('institute')
                ->whereNull('institute_id')
                ->where('category', 'equity')
                ->value('id');

        return ChartOfAccount::withoutGlobalScope('institute')->create([
            'institute_id' => $instituteId,
            'account_group_id' => $groupId,
            'code' => $code,
            'name' => $name,
            'type' => $type,
            'is_system' => 0,
            'is_active' => 1,
        ]);
    }

    protected function uniqueCode(int $instituteId, string $code): string
    {
        while (ChartOfAccount::withoutGlobalScope('institute')
            ->where('institute_id', $instituteId)
            ->where('code', $code)
            ->exists()) {
            $code = (string) ((int) $code + 1);
        }

        return $code;
    }

    public function validateTotalShare(int $instituteId, ?int $excludeId = null): bool
    {
        $query = Partner::where('institute_id', $instituteId);
        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->sum('share_percent') <= 100;
    }
}

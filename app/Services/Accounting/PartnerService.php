<?php

namespace App\Services\Accounting;

use App\Models\ChartOfAccount;
use App\Models\Partner;
use Illuminate\Support\Facades\DB;

class PartnerService
{
    /**
     * Create partner with auto-generated COA accounts.
     * Capital account: 3200.1, 3200.2, 3200.3...
     * Drawing account: 3210.1, 3210.2, 3210.3...
     * Parent: global header 3200 (Partners' Capital).
     */
    public function create(int $instituteId, array $data): Partner
    {
        return DB::transaction(function () use ($instituteId, $data) {
            $sequence = Partner::where('institute_id', $instituteId)->count() + 1;

            $capitalCode = '3200.'.$sequence;
            $drawingCode = '3210.'.$sequence;

            $parentId = ChartOfAccount::withoutGlobalScope('institute')
                ->whereNull('institute_id')
                ->where('code', '3200')
                ->value('id');

            $capitalAccount = $this->createPartnerAccount(
                $instituteId, $capitalCode,
                $data['name'].' - Capital', 'equity', $parentId
            );

            $drawingAccount = $this->createPartnerAccount(
                $instituteId, $drawingCode,
                $data['name'].' - Drawings', 'equity', $parentId
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
        int $instituteId, string $code, string $name, string $type, ?int $parentId = null
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
            'parent_id' => $parentId,
            'code' => $code,
            'name' => $name,
            'type' => $type,
            'is_header' => false,
            'is_postable' => true,
            'is_system' => 0,
            'is_active' => 1,
        ]);
    }

    protected function uniqueCode(int $instituteId, string $code): string
    {
        $base = $code;
        while (ChartOfAccount::withoutGlobalScope('institute')
            ->where('institute_id', $instituteId)
            ->where('code', $code)
            ->exists()) {
            if (str_contains($base, '.')) {
                [$prefix, $suffix] = explode('.', $base, 2);
                $code = $prefix.'.'.((int) $suffix + 1);
                $base = $code;
            } else {
                $code = (string) ((int) $code + 1);
                $base = $code;
            }
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

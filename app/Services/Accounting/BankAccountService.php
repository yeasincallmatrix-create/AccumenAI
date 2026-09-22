<?php

namespace App\Services\Accounting;

use App\Models\ChartOfAccount;
use Illuminate\Support\Facades\DB;

class BankAccountService
{
    public function ensureBankHeader(int $instituteId): ChartOfAccount
    {
        $header = ChartOfAccount::withoutGlobalScope('institute')
            ->where('institute_id', $instituteId)
            ->where('name', 'Bank Accounts')
            ->first();

        if ($header) {
            return $header;
        }

        $global = ChartOfAccount::withoutGlobalScope('institute')
            ->whereNull('institute_id')
            ->where('code', '1100')
            ->first();

        return ChartOfAccount::withoutGlobalScope('institute')->create([
            'institute_id' => $instituteId,
            'code' => '1100',
            'name' => 'Bank Accounts',
            'account_group_id' => $global?->account_group_id ?? 1,
            'type' => 'asset',
            'is_header' => true,
            'is_postable' => false,
            'is_bank' => true,
            'is_active' => true,
        ]);
    }

    public function createBankAccount(int $instituteId, array $data): ChartOfAccount
    {
        $header = $this->ensureBankHeader($instituteId);
        $code = $this->nextBankCode($instituteId, $header->code);

        return ChartOfAccount::withoutGlobalScope('institute')->create([
            'institute_id' => $instituteId,
            'code' => $code,
            'name' => $data['name'],
            'account_group_id' => $header->account_group_id,
            'type' => 'asset',
            'parent_id' => $header->id,
            'is_bank' => true,
            'is_header' => false,
            'is_postable' => true,
            'is_active' => true,
        ]);
    }

    protected function nextBankCode(int $instituteId, string $parentCode): string
    {
        $existing = ChartOfAccount::withoutGlobalScope('institute')
            ->where('institute_id', $instituteId)
            ->where('code', 'LIKE', $parentCode . '.%')
            ->pluck('code')
            ->map(fn (string $c) => (int) substr($c, strrpos($c, '.') + 1))
            ->toArray();

        $next = 1;
        while (in_array($next, $existing, true)) {
            $next++;
        }
        return $parentCode . '.' . $next;
    }
}

<?php

namespace App\Services\Accounting;

use App\Models\ChartOfAccount;
use App\Models\TdsCertificateReceived;
use App\Models\TdsReceivable;
use Illuminate\Support\Facades\DB;

class TdsReceivableService
{
    public function record(int $instituteId, array $data): TdsReceivable
    {
        $country = tenant_country($instituteId);
        $currency = tenant_currency($instituteId);

        return DB::transaction(function () use ($instituteId, $country, $currency, $data) {
            $this->ensureReceivableAccount($instituteId);

            $gross = (float) $data['gross_amount'];
            $rate = (float) $data['rate_percent'];
            $tds = round($gross * ($rate / 100), 2);

            return TdsReceivable::create([
                'institute_id' => $instituteId,
                'country_code' => $country,
                'party_id' => $data['party_id'],
                'invoice_id' => $data['invoice_id'] ?? null,
                'reference_no' => $data['reference_no'] ?? null,
                'gross_amount' => $gross,
                'rate_percent' => $rate,
                'tds_amount' => $tds,
                'net_amount' => round($gross - $tds, 2),
                'currency_code' => $currency,
                'deduction_date' => $data['deduction_date'] ?? now()->toDateString(),
                'tax_period' => $data['tax_period'],
                'financial_year' => $data['financial_year'],
                'status' => 'pending_certificate',
                'notes' => $data['notes'] ?? null,
                'created_by' => auth()->id(),
            ]);
        });
    }

    public function attachCertificate(TdsReceivable $rec, TdsCertificateReceived $cert): void
    {
        $rec->update([
            'certificate_id' => $cert->id,
            'status' => 'certified',
        ]);
    }

    public function markReconciled(TdsReceivable $rec): void
    {
        $rec->update(['status' => 'reconciled']);
    }

    protected function ensureReceivableAccount(int $instituteId): int
    {
        $acc = ChartOfAccount::withoutGlobalScope('institute')
            ->where('institute_id', $instituteId)
            ->where('name', 'TDS Receivable')
            ->first();

        if ($acc) {
            return $acc->id;
        }

        $code = 1305;
        while (ChartOfAccount::withoutGlobalScope('institute')
            ->where('institute_id', $instituteId)
            ->where('code', (string) $code)
            ->exists()) {
            $code++;
        }

        return ChartOfAccount::withoutGlobalScope('institute')->create([
            'institute_id' => $instituteId,
            'code' => (string) $code,
            'name' => 'TDS Receivable',
            'type' => 'asset',
            'account_group_id' => 1,
            'is_system' => 0,
            'is_active' => 1,
        ])->id;
    }
}

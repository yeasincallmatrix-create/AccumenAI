<?php

namespace App\Services\Accounting;

use App\Models\TdsCertificateReceived;
use App\Models\TdsReceivable;
use Illuminate\Support\Facades\DB;

class TdsCertificateReceivedService
{
    public function record(int $instituteId, array $data): TdsCertificateReceived
    {
        return DB::transaction(function () use ($instituteId, $data) {
            $cert = TdsCertificateReceived::create([
                'institute_id' => $instituteId,
                'country_code' => tenant_country($instituteId),
                'party_id' => $data['party_id'],
                'certificate_no' => $data['certificate_no'],
                'certificate_date' => $data['certificate_date'],
                'tax_period' => $data['tax_period'],
                'financial_year' => $data['financial_year'],
                'total_base' => $data['total_base'],
                'total_tds' => $data['total_tds'],
                'currency_code' => tenant_currency($instituteId),
                'attachment_path' => $data['attachment_path'] ?? null,
                'status' => 'received',
                'notes' => $data['notes'] ?? null,
                'created_by' => auth()->id(),
            ]);

            // Auto-link matching pending receivables for same party/FY
            $pending = TdsReceivable::where('institute_id', $instituteId)
                ->where('party_id', $data['party_id'])
                ->where('financial_year', $data['financial_year'])
                ->whereNull('certificate_id')
                ->orderBy('deduction_date')
                ->get();

            $remaining = (float) $cert->total_tds;
            foreach ($pending as $rec) {
                if ($remaining <= 0) {
                    break;
                }
                $rec->update(['certificate_id' => $cert->id, 'status' => 'certified']);
                $remaining -= (float) $rec->tds_amount;
            }

            return $cert;
        });
    }

    public function verify(TdsCertificateReceived $cert): void
    {
        $cert->update(['status' => 'verified']);
    }

    public function dispute(TdsCertificateReceived $cert, string $reason): void
    {
        $cert->update(['status' => 'disputed', 'notes' => $reason]);
    }
}

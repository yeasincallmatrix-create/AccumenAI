<?php

namespace App\Services\Accounting;

use App\Models\TdsCertificateReceived;
use App\Models\TdsReceivable;

class TdsCreditRegisterService
{
    public function register(int $instituteId, string $fy): array
    {
        $rows = TdsReceivable::where('institute_id', $instituteId)
            ->where('financial_year', $fy)
            ->with('party')
            ->get()
            ->groupBy('party_id')
            ->map(function ($group) {
                $first = $group->first();
                return [
                    'party_id' => $first->party_id,
                    'party_name' => $first->party?->name,
                    'gross_total' => (float) $group->sum('gross_amount'),
                    'tds_total' => (float) $group->sum('tds_amount'),
                    'net_total' => (float) $group->sum('net_amount'),
                    'count' => $group->count(),
                    'pending_cert' => $group->where('status', 'pending_certificate')->count(),
                    'certified' => $group->where('status', 'certified')->count(),
                ];
            })->values()->toArray();

        return [
            'financial_year' => $fy,
            'country_code' => tenant_country($instituteId),
            'currency_code' => tenant_currency($instituteId),
            'rows' => $rows,
            'totals' => [
                'gross' => (float) TdsReceivable::where('institute_id', $instituteId)->where('financial_year', $fy)->sum('gross_amount'),
                'tds' => (float) TdsReceivable::where('institute_id', $instituteId)->where('financial_year', $fy)->sum('tds_amount'),
                'net' => (float) TdsReceivable::where('institute_id', $instituteId)->where('financial_year', $fy)->sum('net_amount'),
            ],
        ];
    }

    public function certificateMismatch(int $instituteId, string $fy): array
    {
        $certs = TdsCertificateReceived::where('institute_id', $instituteId)
            ->where('financial_year', $fy)
            ->with('party')
            ->get();

        $unmatched = [];
        foreach ($certs as $c) {
            if ($c->receivables()->count() === 0) {
                $unmatched[] = [
                    'certificate_no' => $c->certificate_no,
                    'party_name' => $c->party?->name,
                    'total_tds' => (float) $c->total_tds,
                    'certificate_date' => $c->certificate_date->format('Y-m-d'),
                ];
            }
        }

        return $unmatched;
    }
}

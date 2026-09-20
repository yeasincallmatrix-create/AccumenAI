<?php

namespace App\Services\Accounting;

use App\Models\Dividend;
use App\Models\DividendPayout;
use App\Models\Institute;
use App\Models\Shareholder;
use Illuminate\Support\Facades\DB;

class DividendService
{
    /**
     * Declare a dividend (draft). Creates payouts for all active
     * shareholders based on share count. No journal writes (placeholder
     * per G.2/H lock).
     */
    public function declare(int $instituteId, array $data): Dividend
    {
        return DB::transaction(function () use ($instituteId, $data) {
            $shareholders = Shareholder::where('institute_id', $instituteId)
                ->where('is_active', true)
                ->get();

            $totalShares = $shareholders->sum('shares');

            if ($totalShares === 0) {
                throw new \InvalidArgumentException('No active shareholders with shares.');
            }

            $totalDividend = (float) $data['total_dividend'];
            $perShare = round($totalDividend / $totalShares, 4);

            $dividend = Dividend::create([
                'institute_id' => $instituteId,
                'reference_no' => $data['reference_no'] ?? $this->generateReferenceNo($instituteId),
                'declared_date' => $data['declared_date'],
                'record_date' => $data['record_date'] ?? null,
                'payment_date' => $data['payment_date'] ?? null,
                'financial_year' => $data['financial_year'],
                'total_dividend' => $totalDividend,
                'per_share_amount' => $perShare,
                'total_shares' => $totalShares,
                'status' => 'draft',
                'board_resolution' => $data['board_resolution'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => auth()->id(),
            ]);

            $this->computePayouts($dividend, $shareholders, $perShare);

            return $dividend->fresh('payouts');
        });
    }

    /**
     * Compute payout rows for each shareholder (10% BD withholding default).
     */
    public function computePayouts(Dividend $dividend, $shareholders, float $perShare): void
    {
        $totalTax = 0;
        $totalNet = 0;

        foreach ($shareholders as $sh) {
            $gross = round($sh->shares * $perShare, 2);
            $taxRate = 10.0;
            $taxAmount = round($gross * ($taxRate / 100), 2);
            $net = round($gross - $taxAmount, 2);

            DividendPayout::create([
                'dividend_id' => $dividend->id,
                'institute_id' => $dividend->institute_id,
                'shareholder_id' => $sh->id,
                'shares' => $sh->shares,
                'gross_amount' => $gross,
                'tax_rate' => $taxRate,
                'tax_amount' => $taxAmount,
                'net_amount' => $net,
                'status' => 'pending',
            ]);

            $totalTax += $taxAmount;
            $totalNet += $net;
        }

        $dividend->update([
            'total_tax' => $totalTax,
            'total_net' => $totalNet,
        ]);
    }

    /**
     * Transition draft → declared.
     */
    public function markDeclared(Dividend $dividend): Dividend
    {
        if (! $dividend->isDraft()) {
            throw new \InvalidArgumentException('Only draft dividends can be declared.');
        }

        $dividend->update([
            'status' => 'declared',
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);

        return $dividend->fresh();
    }

    /**
     * Record payout as paid.
     */
    public function recordPayout(DividendPayout $payout, array $data): DividendPayout
    {
        if ($payout->status === 'paid') {
            throw new \InvalidArgumentException('Payout already paid.');
        }

        $payout->update([
            'status' => 'paid',
            'paid_date' => $data['paid_date'] ?? now(),
            'payment_method' => $data['payment_method'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);

        $this->maybeMarkDividendPaid($payout->dividend);

        return $payout->fresh();
    }

    /**
     * Bulk payout — mark all pending as paid.
     */
    public function payAll(Dividend $dividend, array $data): int
    {
        $count = DividendPayout::where('dividend_id', $dividend->id)
            ->where('status', 'pending')
            ->update([
                'status' => 'paid',
                'paid_date' => $data['paid_date'] ?? now(),
                'payment_method' => $data['payment_method'] ?? null,
            ]);

        $dividend->update(['status' => 'paid']);

        return $count;
    }

    /**
     * Cancel dividend (revert payouts).
     */
    public function cancel(Dividend $dividend): Dividend
    {
        if (! $dividend->canCancel()) {
            throw new \InvalidArgumentException('Dividend cannot be cancelled.');
        }

        DividendPayout::where('dividend_id', $dividend->id)
            ->update(['status' => 'cancelled']);

        $dividend->update(['status' => 'cancelled']);

        return $dividend->fresh();
    }

    /**
     * Register report — per shareholder across dividends.
     */
    public function getRegister(int $instituteId, ?string $financialYear = null): array
    {
        $query = DividendPayout::where('institute_id', $instituteId)
            ->with(['shareholder', 'dividend'])
            ->whereHas('dividend', function ($q) use ($financialYear) {
                if ($financialYear) {
                    $q->where('financial_year', $financialYear);
                }
            });

        return $query->get()
            ->groupBy('shareholder_id')
            ->map(function ($payouts) {
                $first = $payouts->first();

                return [
                    'shareholder_id' => $first->shareholder_id,
                    'name' => $first->shareholder?->name,
                    'total_shares' => $first->shares,
                    'gross_total' => $payouts->sum('gross_amount'),
                    'tax_total' => $payouts->sum('tax_amount'),
                    'net_total' => $payouts->sum('net_amount'),
                    'dividend_count' => $payouts->unique('dividend_id')->count(),
                ];
            })->values()->toArray();
    }

    /**
     * Generate next reference number (per tenant, sequential).
     */
    protected function generateReferenceNo(int $instituteId): string
    {
        $count = Dividend::where('institute_id', $instituteId)->count() + 1;

        return 'DIV-'.date('Y').'-'.str_pad((string) $count, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Mark dividend paid if all payouts are paid.
     */
    protected function maybeMarkDividendPaid(Dividend $dividend): void
    {
        $pending = DividendPayout::where('dividend_id', $dividend->id)
            ->where('status', 'pending')
            ->count();

        if ($pending === 0 && $dividend->status !== 'paid') {
            $dividend->update(['status' => 'paid']);
        }
    }

    /**
     * Dividend summary for dashboard.
     */
    public function getSummary(int $instituteId, ?string $financialYear = null): array
    {
        $query = Dividend::where('institute_id', $instituteId);
        if ($financialYear) {
            $query->where('financial_year', $financialYear);
        }

        return [
            'total_dividends' => $query->count(),
            'total_declared' => (float) (clone $query)->whereIn('status', ['declared', 'paid'])->sum('total_dividend'),
            'total_paid' => (float) (clone $query)->where('status', 'paid')->sum('total_net'),
            'total_tax' => (float) (clone $query)->whereIn('status', ['declared', 'paid'])->sum('total_tax'),
            'pending_count' => DividendPayout::where('institute_id', $instituteId)
                ->where('status', 'pending')->count(),
        ];
    }
}

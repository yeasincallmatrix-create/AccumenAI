<?php

namespace App\Services\Accounting;

use App\Models\Institute;
use App\Models\ShareCapitalTransaction;
use App\Models\ShareCertificate;
use App\Models\Shareholder;
use Illuminate\Support\Facades\DB;

class ShareCapitalService
{
    public function issueShares(
        int $instituteId,
        int $shareholderId,
        int $shares,
        float $faceValue,
        float $premiumPerShare = 0,
        ?string $notes = null
    ): ShareCapitalTransaction {
        return DB::transaction(function () use (
            $instituteId, $shareholderId, $shares,
            $faceValue, $premiumPerShare, $notes
        ) {
            $shareholder = Shareholder::where('institute_id', $instituteId)
                ->findOrFail($shareholderId);

            $totalAmount = $shares * ($faceValue + $premiumPerShare);

            $shareholder->increment('shares', $shares);

            $certificate = ShareCertificate::create([
                'institute_id' => $instituteId,
                'shareholder_id' => $shareholderId,
                'certificate_no' => $this->generateCertificateNumber($instituteId),
                'shares' => $shares,
                'face_value' => $faceValue,
                'total_value' => $shares * $faceValue,
                'issue_date' => now(),
                'status' => 'active',
            ]);

            $transaction = ShareCapitalTransaction::create([
                'institute_id' => $instituteId,
                'type' => 'issuance',
                'shareholder_id' => $shareholderId,
                'shares' => $shares,
                'face_value' => $faceValue,
                'premium_per_share' => $premiumPerShare,
                'total_amount' => $totalAmount,
                'transaction_date' => now(),
                'certificate_no' => $certificate->certificate_no,
                'notes' => $notes,
                'created_by' => auth()->id(),
            ]);

            $this->recomputeSharePercents($instituteId);
            $this->recomputeInstituteCapital($instituteId);

            return $transaction;
        });
    }

    public function transferShares(
        int $instituteId,
        int $fromShareholderId,
        int $toShareholderId,
        int $shares,
        float $faceValue,
        ?string $notes = null
    ): ShareCapitalTransaction {
        return DB::transaction(function () use (
            $instituteId, $fromShareholderId, $toShareholderId,
            $shares, $faceValue, $notes
        ) {
            $from = Shareholder::where('institute_id', $instituteId)
                ->findOrFail($fromShareholderId);
            $to = Shareholder::where('institute_id', $instituteId)
                ->findOrFail($toShareholderId);

            if ($from->shares < $shares) {
                throw new \InvalidArgumentException(
                    "Shareholder {$from->name} only has {$from->shares} shares."
                );
            }

            $from->decrement('shares', $shares);
            $to->increment('shares', $shares);

            ShareCertificate::create([
                'institute_id' => $instituteId,
                'shareholder_id' => $toShareholderId,
                'certificate_no' => $this->generateCertificateNumber($instituteId),
                'shares' => $shares,
                'face_value' => $faceValue,
                'total_value' => $shares * $faceValue,
                'issue_date' => now(),
                'status' => 'active',
            ]);

            $this->recomputeSharePercents($instituteId);

            return ShareCapitalTransaction::create([
                'institute_id' => $instituteId,
                'type' => 'transfer',
                'from_shareholder_id' => $fromShareholderId,
                'to_shareholder_id' => $toShareholderId,
                'shares' => $shares,
                'face_value' => $faceValue,
                'total_amount' => $shares * $faceValue,
                'transaction_date' => now(),
                'notes' => $notes,
                'created_by' => auth()->id(),
            ]);
        });
    }

    public function buybackShares(
        int $instituteId,
        int $shareholderId,
        int $shares,
        float $faceValue,
        ?string $notes = null
    ): ShareCapitalTransaction {
        return DB::transaction(function () use (
            $instituteId, $shareholderId, $shares, $faceValue, $notes
        ) {
            $sh = Shareholder::where('institute_id', $instituteId)
                ->findOrFail($shareholderId);

            if ($sh->shares < $shares) {
                throw new \InvalidArgumentException('Cannot buyback more shares than owned.');
            }

            $sh->decrement('shares', $shares);

            ShareCertificate::where('institute_id', $instituteId)
                ->where('shareholder_id', $shareholderId)
                ->where('status', 'active')
                ->update(['status' => 'cancelled', 'cancel_date' => now()]);

            $transaction = ShareCapitalTransaction::create([
                'institute_id' => $instituteId,
                'type' => 'buyback',
                'shareholder_id' => $shareholderId,
                'shares' => $shares,
                'face_value' => $faceValue,
                'total_amount' => $shares * $faceValue,
                'transaction_date' => now(),
                'notes' => $notes,
                'created_by' => auth()->id(),
            ]);

            $this->recomputeSharePercents($instituteId);
            $this->recomputeInstituteCapital($instituteId);

            return $transaction;
        });
    }

    public function recomputeSharePercents(int $instituteId): void
    {
        $totalShares = (int) Shareholder::where('institute_id', $instituteId)->sum('shares');
        if ($totalShares === 0) {
            return;
        }

        Shareholder::where('institute_id', $instituteId)->each(function ($sh) use ($totalShares) {
            $sh->update([
                'share_percent' => round(($sh->shares / $totalShares) * 100, 4),
            ]);
        });
    }

    public function recomputeInstituteCapital(int $instituteId): void
    {
        $totalShares = (int) Shareholder::where('institute_id', $instituteId)->sum('shares');
        $faceValue = (float) Institute::where('id', $instituteId)->value('share_face_value') ?? 10;
        $paidUp = $totalShares * $faceValue;

        Institute::where('id', $instituteId)->update([
            'shares_outstanding' => $totalShares,
            'issued_capital' => $paidUp,
            'paid_up_capital' => $paidUp,
        ]);
    }

    protected function generateCertificateNumber(int $instituteId): string
    {
        $count = ShareCertificate::where('institute_id', $instituteId)->count() + 1;

        return 'SC-'.str_pad((string) $count, 5, '0', STR_PAD_LEFT);
    }

    public function getCapitalSummary(int $instituteId): array
    {
        $institute = Institute::find($instituteId);

        return [
            'authorized_capital' => (float) ($institute->authorized_capital ?? 0),
            'issued_capital' => (float) ($institute->issued_capital ?? 0),
            'paid_up_capital' => (float) ($institute->paid_up_capital ?? 0),
            'shares_outstanding' => (int) ($institute->shares_outstanding ?? 0),
            'share_face_value' => (float) ($institute->share_face_value ?? 10),
            'remaining_authorized' => (float) ($institute->authorized_capital ?? 0)
                - (float) ($institute->issued_capital ?? 0),
            'paid_up_ratio' => ($institute->issued_capital ?? 0) > 0
                ? round((($institute->paid_up_capital ?? 0) / $institute->issued_capital) * 100, 2)
                : 0,
        ];
    }
}

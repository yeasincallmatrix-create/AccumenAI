<?php

namespace App\Services;

use App\Models\CashMemo;
use App\Models\ChartOfAccount;
use App\Models\Currency;
use App\Models\FiscalYear;
use App\Models\InstituteUser;
use App\Models\OfflineSyncQueue;
use App\Models\Student;
use App\Services\Accounting\AccountingAuditService;
use App\Services\Accounting\AccountingSetupService;
use App\Services\Accounting\ChartOfAccountService;
use App\Services\Accounting\JournalPostingService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Reviews offline-synced records and materializes approved ones into
 * production tables (currently cash_memos).
 *
 * A materialized cash memo is real money received, so when the institute has
 * accounting configured (COA + open current fiscal year) it also posts a
 * RECEIPT journal (debit cash/bank per payment method / credit income) so the
 * collection is reflected in the ledger, trial balance and cash reports.
 * Institutes without accounting setup keep the legacy behavior (memo only).
 */
class OfflineSyncService
{
    public const SUPPORTED_ENTITY_TYPES = ['cash_memo'];

    public function __construct(
        private readonly JournalPostingService $posting,
        private readonly AccountingSetupService $settings,
        private readonly AccountingAuditService $audit,
    ) {}

    /**
     * Server-side payload validation. Throws ValidationException on failure.
     */
    public function validatePayload(array $payload): array
    {
        $validator = validator($payload, [
            'student_id' => ['nullable', 'integer'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'description' => ['nullable', 'string', 'max:255'],
            'payment_method' => ['nullable', 'in:cash,bkash,nagad,bank,other'],
            'memo_number' => ['nullable', 'string', 'max:30'],
            'created_at' => ['nullable', 'date'],
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }

    /**
     * Approve + materialize a pending queue record into a CashMemo.
     */
    public function materialize(OfflineSyncQueue $queue, InstituteUser $reviewer): CashMemo
    {
        if ($queue->status !== 'pending_review') {
            throw new \LogicException('Only pending review records can be approved.');
        }

        $payload = $this->validatePayload((array) $queue->payload);

        if ($payload['student_id'] ?? null) {
            try {
                Student::query()->findOrFail($payload['student_id']);
            } catch (ModelNotFoundException) {
                throw ValidationException::withMessages([
                    'student_id' => 'Student does not exist (or does not belong to this institute).',
                ]);
            }
        }

        return DB::transaction(function () use ($queue, $reviewer, $payload) {
            $memo = CashMemo::create([
                'institute_id' => $queue->institute_id,
                'memo_number' => $this->resolveMemoNumber($queue->institute_id, $payload['memo_number'] ?? null),
                'student_id' => $payload['student_id'] ?? null,
                'amount' => $payload['amount'],
                'description' => $payload['description'] ?? null,
                'payment_method' => $payload['payment_method'] ?? 'cash',
                'created_by' => $queue->created_by,
                'offline_origin_id' => $queue->id,
                'created_at' => $payload['created_at'] ?? $queue->created_offline_at,
            ]);

            $this->postJournalForMemo($memo, $reviewer);

            $queue->forceFill([
                'status' => 'approved',
                'synced_at' => now(),
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
                'reject_reason' => null,
                'materialized_id' => $memo->id,
            ])->save();

            return $memo->fresh();
        });
    }

    /**
     * Reject a pending queue record.
     */
    public function reject(OfflineSyncQueue $queue, InstituteUser $reviewer, string $reason): void
    {
        if ($queue->status !== 'pending_review') {
            throw new \LogicException('Only pending review records can be rejected.');
        }

        DB::transaction(function () use ($queue, $reviewer, $reason) {
            $queue->forceFill([
                'status' => 'rejected',
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
                'reject_reason' => $reason,
            ])->save();
        });
    }

    /**
     * Post the RECEIPT journal for a materialized cash memo when the institute
     * has accounting configured. Returns null (and leaves the memo journal-less,
     * matching the legacy behavior) when accounting is not set up or the memo
     * cannot be mapped to cash/bank and income accounts. Never throws: an
     * unjournaled offline collection must still be materialized.
     */
    private function postJournalForMemo(CashMemo $memo, InstituteUser $reviewer): ?int
    {
        $instituteId = (int) $memo->institute_id;

        if (! $this->hasJournalableAccounting($instituteId)) {
            return null;
        }

        $cashAccountId = $this->memoCashAccountId($memo);
        $incomeAccountId = $this->memoIncomeAccountId($instituteId);

        if ($cashAccountId === null || $incomeAccountId === null) {
            return null;
        }

        $journal = $this->posting->create([
            'institute_id' => $instituteId,
            'branch_id' => null,
            'journal_date' => now()->toDateString(),
            'currency_id' => $this->resolveCurrencyId($instituteId),
            'type' => 'receipt',
            'ref_type' => 'cash_memo',
            'ref_id' => $memo->id,
            'description' => 'Cash memo '.$memo->memo_number,
            'entries' => [
                [
                    'coa_id' => $cashAccountId,
                    'party_id' => null,
                    'debit' => (float) $memo->amount,
                    'credit' => 0,
                    'memo' => 'Cash memo '.$memo->memo_number,
                ],
                [
                    'coa_id' => $incomeAccountId,
                    'party_id' => null,
                    'debit' => 0,
                    'credit' => (float) $memo->amount,
                    'memo' => 'Cash memo '.$memo->memo_number,
                ],
            ],
        ], $reviewer->id);

        $memo->forceFill(['journal_id' => $journal->id])->save();

        $this->audit->log($instituteId, [
            'branch_id' => null,
            'actor_type' => 'user',
            'actor_id' => $reviewer->id,
            'action' => 'create',
            'entity_type' => 'cash_memo',
            'entity_id' => $memo->id,
            'after_payload' => [
                'memo_number' => $memo->memo_number,
                'amount' => (float) $memo->amount,
                'journal' => $journal->journal_no,
            ],
        ]);

        return (int) $journal->id;
    }

    private function hasJournalableAccounting(int $instituteId): bool
    {
        $today = now()->toDateString();

        $hasYear = FiscalYear::query()
            ->where('institute_id', $instituteId)
            ->whereNull('branch_id')
            ->where('status', 'open')
            ->where('is_current', true)
            ->whereDate('start_date', '<=', $today)
            ->whereDate('end_date', '>=', $today)
            ->exists();

        $hasAccounts = ChartOfAccount::query()
            ->where('institute_id', $instituteId)
            ->whereNull('branch_id')
            ->exists();

        return $hasYear && $hasAccounts;
    }

    private function memoCashAccountId(CashMemo $memo): ?int
    {
        $instituteId = (int) $memo->institute_id;
        $isBank = in_array($memo->payment_method, ['bank', 'bkash', 'nagad'], true);
        $code = $isBank ? '1002' : '1001';

        $coaService = app(ChartOfAccountService::class);
        $account = $coaService->accountByCode($instituteId, $code, null);

        if ($account === null) {
            $account = ChartOfAccount::query()
                ->where('institute_id', $instituteId)
                ->whereNull('branch_id')
                ->where($isBank ? 'is_bank' : 'is_cash', true)
                ->where('is_active', true)
                ->orderBy('code')
                ->first();
        }

        return $account !== null ? (int) $account->id : null;
    }

    private function memoIncomeAccountId(int $instituteId): ?int
    {
        $coaService = app(ChartOfAccountService::class);

        foreach (['4004', '4001'] as $code) {
            $account = $coaService->accountByCode($instituteId, $code, null);

            if ($account !== null) {
                return (int) $account->id;
            }
        }

        $account = ChartOfAccount::query()
            ->where('institute_id', $instituteId)
            ->whereNull('branch_id')
            ->where('type', 'income')
            ->where('is_active', true)
            ->orderBy('code')
            ->first();

        return $account !== null ? (int) $account->id : null;
    }

    private function resolveCurrencyId(int $instituteId): int
    {
        $code = $this->settings->getSetting($instituteId, 'base_currency');

        if ($code !== null) {
            $currency = Currency::query()->where('code', $code)->first();

            if ($currency !== null) {
                return (int) $currency->id;
            }
        }

        return (int) (Currency::query()->orderBy('code')->value('id'));
    }

    /**
     * Use the client-supplied memo number when it is still free, otherwise
     * generate a unique one for the institute.
     */
    private function resolveMemoNumber(int $instituteId, ?string $preferred): string
    {
        $taken = fn (string $number) => CashMemo::query()
            ->where('institute_id', $instituteId)
            ->where('memo_number', $number)
            ->exists();

        if ($preferred !== null && ! $taken($preferred)) {
            return $preferred;
        }

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $candidate = 'CM-'.now()->format('ymd').'-'.strtoupper(Str::random(5));
            if (! $taken($candidate)) {
                return $candidate;
            }
        }

        throw new \RuntimeException('Could not allocate a unique memo number.');
    }
}

<?php

namespace App\Services\Accounting;

use App\Models\Currency;
use App\Models\ExchangeRate;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Exchange rate management (STEP 19).
 *
 * Rates are institute-scoped; branch_id NULL = institute-wide rate. A branch
 * rate overrides the institute-wide rate for that branch. The accounting rate
 * lives in `rate` (buy/sell are optional metadata). Every write is audited.
 *
 * Effective-date lookup is deterministic: exact date first (branch before
 * institute-wide), then the latest earlier rate (historical). A posted
 * transaction keeps the rate it was posted with — rates are never rewritten.
 */
class ExchangeRateService
{
    public function __construct(private readonly AccountingAuditService $audit) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function list(int $instituteId, ?int $branchId, array $filters = []): LengthAwarePaginator
    {
        return ExchangeRate::query()
            ->with(['fromCurrency', 'toCurrency'])
            ->where('institute_id', $instituteId)
            ->when($branchId !== null, fn ($query) => $query->where(fn ($scope) => $scope
                ->where('branch_id', $branchId)
                ->orWhereNull('branch_id')))
            ->when(filled($filters['from_currency_id'] ?? null), fn ($query) => $query->where('from_currency_id', (int) $filters['from_currency_id']))
            ->when(filled($filters['to_currency_id'] ?? null), fn ($query) => $query->where('to_currency_id', (int) $filters['to_currency_id']))
            ->orderByDesc('rate_date')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();
    }

    /**
     * Rate history for a currency pair (newest first).
     */
    public function history(int $instituteId, ?int $branchId, int $fromCurrencyId, int $toCurrencyId, int $limit = 100): Collection
    {
        return ExchangeRate::query()
            ->where('institute_id', $instituteId)
            ->when($branchId !== null, fn ($query) => $query->where(fn ($scope) => $scope
                ->where('branch_id', $branchId)
                ->orWhereNull('branch_id')))
            ->where('from_currency_id', $fromCurrencyId)
            ->where('to_currency_id', $toCurrencyId)
            ->orderByDesc('rate_date')
            ->limit($limit)
            ->get();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(int $instituteId, ?int $branchId, array $data, ?int $actorId = null): ExchangeRate
    {
        $data = $this->validate($instituteId, $branchId, $data);

        $rate = ExchangeRate::create(array_merge($data, [
            'institute_id' => $instituteId,
            'branch_id' => $branchId,
            'created_by' => $actorId,
        ]));

        $this->audit->log($instituteId, [
            'branch_id' => $branchId,
            'actor_type' => 'user',
            'actor_id' => $actorId,
            'action' => 'create',
            'entity_type' => 'exchange_rate',
            'entity_id' => $rate->id,
            'after_payload' => [
                'from' => $rate->from_currency_id,
                'to' => $rate->to_currency_id,
                'rate' => (string) $rate->rate,
                'rate_date' => $rate->rate_date?->toDateString(),
                'source' => $rate->source,
            ],
        ]);

        return $rate;
    }

    /**
     * Update a rate row. Posted transactions are unaffected — they keep the
     * rate stored on their own journal.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(ExchangeRate $rate, array $data, ?int $actorId = null): ExchangeRate
    {
        $before = [
            'rate' => (string) $rate->rate,
            'rate_date' => $rate->rate_date?->toDateString(),
            'source' => $rate->source,
        ];

        $data = $this->validate($rate->institute_id, $rate->branch_id, $data, exceptId: $rate->id);

        $rate->forceFill(array_merge($data, [
            'updated_by' => $actorId,
        ]))->save();

        $this->audit->log($rate->institute_id, [
            'branch_id' => $rate->branch_id,
            'actor_type' => 'user',
            'actor_id' => $actorId,
            'action' => 'update',
            'entity_type' => 'exchange_rate',
            'entity_id' => $rate->id,
            'before_payload' => $before,
            'after_payload' => [
                'rate' => (string) $rate->rate,
                'rate_date' => $rate->rate_date?->toDateString(),
                'source' => $rate->source,
            ],
        ]);

        return $rate;
    }

    public function delete(ExchangeRate $rate, ?int $actorId = null): void
    {
        $rate->delete();

        $this->audit->log($rate->institute_id, [
            'branch_id' => $rate->branch_id,
            'actor_type' => 'user',
            'actor_id' => $actorId,
            'action' => 'delete',
            'entity_type' => 'exchange_rate',
            'entity_id' => $rate->id,
            'before_payload' => ['rate' => (string) $rate->rate, 'rate_date' => $rate->rate_date?->toDateString()],
        ]);
    }

    /**
     * Deterministic effective-rate lookup for a date:
     *   1. exact branch rate on the date
     *   2. exact institute-wide rate on the date
     *   3. latest branch rate on/before the date
     *   4. latest institute-wide rate on/before the date
     *
     * @return array{rate: string, source: string}|null
     */
    public function findEffective(int $instituteId, ?int $branchId, int $fromCurrencyId, int $toCurrencyId, string $date): ?array
    {
        if ($branchId !== null) {
            $exact = $this->pairQuery($instituteId, $fromCurrencyId, $toCurrencyId)
                ->where('branch_id', $branchId)
                ->whereDate('rate_date', $date)
                ->orderByDesc('id')
                ->first();

            if ($exact !== null) {
                return ['rate' => (string) $exact->rate, 'source' => 'branch_exact'];
            }
        }

        $exactShared = $this->pairQuery($instituteId, $fromCurrencyId, $toCurrencyId)
            ->whereNull('branch_id')
            ->whereDate('rate_date', $date)
            ->orderByDesc('id')
            ->first();

        if ($exactShared !== null) {
            return ['rate' => (string) $exactShared->rate, 'source' => 'institute_exact'];
        }

        if ($branchId !== null) {
            $historical = $this->pairQuery($instituteId, $fromCurrencyId, $toCurrencyId)
                ->where('branch_id', $branchId)
                ->whereDate('rate_date', '<=', $date)
                ->orderByDesc('rate_date')
                ->orderByDesc('id')
                ->first();

            if ($historical !== null) {
                return ['rate' => (string) $historical->rate, 'source' => 'branch_historical'];
            }
        }

        $historicalShared = $this->pairQuery($instituteId, $fromCurrencyId, $toCurrencyId)
            ->whereNull('branch_id')
            ->whereDate('rate_date', '<=', $date)
            ->orderByDesc('rate_date')
            ->orderByDesc('id')
            ->first();

        if ($historicalShared !== null) {
            return ['rate' => (string) $historicalShared->rate, 'source' => 'institute_historical'];
        }

        return null;
    }

    private function pairQuery(int $instituteId, int $fromCurrencyId, int $toCurrencyId): \Illuminate\Database\Eloquent\Builder
    {
        return ExchangeRate::query()
            ->where('institute_id', $instituteId)
            ->where('from_currency_id', $fromCurrencyId)
            ->where('to_currency_id', $toCurrencyId);
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @return array<string, mixed>
     */
    private function validate(int $instituteId, ?int $branchId, array $data, ?int $exceptId = null): array
    {
        $validator = validator($data, [
            'from_currency_id' => ['required', 'integer', 'exists:currencies,id'],
            'to_currency_id' => ['required', 'integer', 'exists:currencies,id', 'different:from_currency_id'],
            'rate' => ['required', 'numeric', 'gt:0'],
            'rate_date' => ['required', 'date'],
            'source' => ['nullable', 'string', 'max:40'],
            'buy_rate' => ['nullable', 'numeric', 'gt:0'],
            'sell_rate' => ['nullable', 'numeric', 'gt:0'],
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $data = $validator->validated();

        foreach (['from_currency_id', 'to_currency_id'] as $key) {
            $currency = Currency::query()->find((int) $data[$key]);
            if ($currency === null || ! $currency->is_active) {
                throw ValidationException::withMessages([
                    $key => 'The selected currency is not active.',
                ]);
            }
        }

        $duplicate = ExchangeRate::query()
            ->where('institute_id', $instituteId)
            ->where('branch_id', $branchId)
            ->where('from_currency_id', (int) $data['from_currency_id'])
            ->where('to_currency_id', (int) $data['to_currency_id'])
            ->whereDate('rate_date', $data['rate_date']);

        if ($exceptId !== null) {
            $duplicate->where('id', '!=', $exceptId);
        }

        if ($duplicate->exists()) {
            throw ValidationException::withMessages([
                'rate_date' => 'A rate for this currency pair already exists on this date in this scope.',
            ]);
        }

        $data['rate_date'] = \Illuminate\Support\Carbon::parse($data['rate_date'])->toDateString();

        return $data;
    }
}

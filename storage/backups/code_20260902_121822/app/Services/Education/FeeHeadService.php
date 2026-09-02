<?php

namespace App\Services\Education;

use App\Models\ChartOfAccount;
use App\Models\FeeHead;
use App\Services\Accounting\ChartOfAccountService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Education fee heads (Step 37) — CRUD on the per-institute fee head catalog,
 * resolving the default income CoA per head type from the installed template.
 */
class FeeHeadService
{
    /** Fee-head type → template income account code. */
    private const TYPE_TO_COA = [
        FeeHead::TYPE_ADMISSION => '4002',
        FeeHead::TYPE_COURSE_TUITION => '4001',
        FeeHead::TYPE_REGISTRATION => '4004',
        FeeHead::TYPE_EXAM => '4004',
        FeeHead::TYPE_CERTIFICATE => '4004',
        FeeHead::TYPE_OTHER => '4004',
    ];

    public function __construct(private readonly ChartOfAccountService $coaService) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(int $instituteId, ?int $branchId, array $data, ?int $actorId = null): FeeHead
    {
        $data = $this->validate($instituteId, $branchId, $data);

        $data['income_coa_id'] = $data['income_coa_id']
            ?? $this->defaultIncomeAccount($instituteId, $branchId, $data['type']);

        return FeeHead::create([
            'institute_id' => $instituteId,
            'branch_id' => $branchId,
            'name' => $data['name'],
            'code' => $data['code'] ?? null,
            'type' => $data['type'],
            'default_amount' => $data['default_amount'] ?? 0,
            'income_coa_id' => $data['income_coa_id'],
            'description' => $data['description'] ?? null,
            'is_active' => $data['is_active'] ?? true,
            'is_recurring' => $data['is_recurring'] ?? false,
            'billing_frequency' => $data['billing_frequency'] ?? FeeHead::FREQ_ONE_TIME,
            'sort_order' => $data['sort_order'] ?? 0,
            'created_by' => $actorId,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(FeeHead $feeHead, array $data, ?int $actorId = null): FeeHead
    {
        $data = $this->validate($feeHead->institute_id, $feeHead->branch_id, $data, $feeHead->id);

        $data['income_coa_id'] = $data['income_coa_id']
            ?? $this->defaultIncomeAccount($feeHead->institute_id, $feeHead->branch_id, $data['type']);

        $feeHead->forceFill([
            'name' => $data['name'],
            'code' => $data['code'] ?? null,
            'type' => $data['type'],
            'default_amount' => $data['default_amount'] ?? 0,
            'income_coa_id' => $data['income_coa_id'],
            'description' => $data['description'] ?? null,
            'is_active' => $data['is_active'] ?? true,
            'is_recurring' => $data['is_recurring'] ?? false,
            'billing_frequency' => $data['billing_frequency'] ?? FeeHead::FREQ_ONE_TIME,
            'sort_order' => $data['sort_order'] ?? 0,
            'updated_by' => $actorId,
        ])->save();

        return $feeHead->fresh();
    }

    public function toggle(FeeHead $feeHead): FeeHead
    {
        $feeHead->forceFill(['is_active' => ! $feeHead->is_active])->save();

        return $feeHead->fresh();
    }

    /**
     * Deleting a fee head is safe: invoice line items keep their own amounts
     * and income accounts (fee_head_id nulls out on delete), and fee
     * structure items referencing the head are cascade-restricted.
     */
    public function destroy(FeeHead $feeHead): void
    {
        DB::transaction(function () use ($feeHead) {
            FeeHead::query()
                ->where('id', $feeHead->id)
                ->get()
                ->each(function (FeeHead $head) {
                    $head->structureItems()->delete();
                    $head->delete();
                });
        });
    }

    /**
     * Default income account for a head type (template code, falling back to
     * any active income account, then null).
     */
    public function defaultIncomeAccount(int $instituteId, ?int $branchId, string $type): ?int
    {
        $code = self::TYPE_TO_COA[$type] ?? '4004';

        $account = $this->coaService->accountByCode($instituteId, $code, $branchId)
            ?? $this->coaService->accountByCode($instituteId, $code, null);

        if ($account !== null) {
            return (int) $account->id;
        }

        $fallback = ChartOfAccount::query()
            ->where('institute_id', $instituteId)
            ->where('branch_id', $branchId)
            ->where('type', 'income')
            ->where('is_active', true)
            ->orderBy('code')
            ->first();

        return $fallback !== null ? (int) $fallback->id : null;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function validate(int $instituteId, ?int $branchId, array $data, ?int $ignoreId = null): array
    {
        $validator = validator($data, [
            'name' => ['required', 'string', 'max:150'],
            'code' => ['nullable', 'string', 'max:40'],
            'type' => ['required', 'in:admission,course_tuition,registration,exam,certificate,other'],
            'default_amount' => ['nullable', 'numeric', 'min:0'],
            'income_coa_id' => ['nullable', 'integer'],
            'description' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
            'is_recurring' => ['nullable', 'boolean'],
            'billing_frequency' => ['nullable', Rule::in(FeeHead::BILLING_FREQUENCIES)],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $data = $validator->validated();

        $query = FeeHead::query()
            ->where('institute_id', $instituteId)
            ->where('branch_id', $branchId)
            ->where('name', $data['name']);

        if ($ignoreId !== null) {
            $query->where('id', '!=', $ignoreId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'name' => 'A fee head with this name already exists for this scope.',
            ]);
        }

        if (filled($data['income_coa_id'] ?? null)) {
            $account = ChartOfAccount::query()
                ->where('institute_id', $instituteId)
                ->where('branch_id', $branchId)
                ->where('type', 'income')
                ->find((int) $data['income_coa_id']);

            if ($account === null) {
                throw ValidationException::withMessages([
                    'income_coa_id' => 'The selected account is not an income account of this scope.',
                ]);
            }
        }

        return $data;
    }
}

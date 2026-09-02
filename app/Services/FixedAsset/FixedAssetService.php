<?php

namespace App\Services\FixedAsset;

use App\Models\AssetAuditLog;
use App\Models\AssetCategory;
use App\Models\AssetDepreciationEntry;
use App\Models\AssetDepreciationRun;
use App\Models\AssetDisposal;
use App\Models\AssetImpairment;
use App\Models\AssetMethodChange;
use App\Models\AssetRevaluation;
use App\Models\AssetTransfer;
use App\Models\FixedAsset;
use App\Services\FixedAsset\Depreciation\DepreciationMethodResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Fixed asset lifecycle engine (STEP 17).
 *
 * Owns the asset state machine (draft -> acquired -> capitalized -> active ->
 * ... -> disposed) and every mutating workflow. Accounting side-effects are
 * delegated to FixedAssetAccountingService; every mutation is transactional and
 * audited through AssetAuditLog. Historical posted depreciation is immutable.
 */
class FixedAssetService
{
    public function __construct(
        private readonly FixedAssetAccountingService $accounting,
        private readonly FixedAssetCapabilityService $capabilities,
        private readonly DepreciationEngine $engine,
    ) {}

    public function create(int $instituteId, ?int $branchId, array $data, ?int $actorId = null): FixedAsset
    {
        $this->capabilities->assert($instituteId, 'assets.enabled', $branchId);

        $data = $this->validateAsset($instituteId, $branchId, $data);

        $asset = FixedAsset::create(array_merge($data, [
            'institute_id' => $instituteId,
            'branch_id' => $branchId,
            'asset_code' => $data['asset_code'] ?? $this->allocateAssetCode($instituteId),
            'status' => 'draft',
            'created_by' => $actorId,
        ]));

        $this->log($instituteId, $asset->id, 'created', null, $asset->only(['name', 'asset_code', 'status']), $actorId);

        return $asset;
    }

    public function update(FixedAsset $asset, array $data, ?int $actorId = null): FixedAsset
    {
        $instituteId = $asset->institute_id;
        $data = $this->validateAsset($instituteId, $asset->branch_id, $data, exceptAssetId: $asset->id);

        $old = $asset->only(['name', 'serial_number', 'department', 'responsible_person']);

        $asset->forceFill(array_merge($data, ['updated_by' => $actorId]))->save();

        $this->log($instituteId, $asset->id, 'updated', $old, $asset->only(['name', 'serial_number', 'department', 'responsible_person']), $actorId);

        return $asset;
    }

    /**
     * Capitalize an acquired asset and post the capitalization journal.
     */
    public function capitalize(FixedAsset $asset, ?int $partyId, ?int $actorId = null, array $options = []): FixedAsset
    {
        if (in_array($asset->status, ['capitalized', 'active', 'disposed', 'sold', 'scrapped', 'retired'], true)) {
            throw ValidationException::withMessages(['asset' => 'This asset is already capitalized or no longer capitalizable.']);
        }

        $this->capabilities->assert($asset->institute_id, 'assets.enabled', $asset->branch_id);

        return DB::transaction(function () use ($asset, $partyId, $actorId, $options) {
            $journal = $this->accounting->capitalizationJournal($asset, $partyId, $actorId, $options['journal_date'] ?? null, $options);

            $asset->forceFill([
                'status' => 'active',
                'capitalization_date' => $asset->capitalization_date ?? now()->toDateString(),
                'depreciation_start_date' => $asset->depreciation_start_date ?? $asset->capitalization_date ?? now()->toDateString(),
                'updated_by' => $actorId,
            ])->save();

            $this->log($asset->institute_id, $asset->id, 'capitalized', null, ['status' => 'active', 'journal_id' => $journal->id], $actorId);

            return $asset->fresh();
        });
    }

    /**
     * Batch depreciation run for a period. One run per (institute, branch,
     * period_start). Duplicate runs are rejected by the unique constraint.
     *
     * @param  array<int, float>|null  $unitsByAsset  asset_id => units produced this period
     */
    public function runDepreciation(
        int $instituteId,
        ?int $branchId,
        string $periodStart,
        string $periodEnd,
        ?int $actorId = null,
        ?array $unitsByAsset = null,
    ): AssetDepreciationRun {
        $this->capabilities->assert($instituteId, 'assets.depreciation', $branchId);

        return DB::transaction(function () use ($instituteId, $branchId, $periodStart, $periodEnd, $actorId, $unitsByAsset) {
            $existingRun = AssetDepreciationRun::query()
                ->where('institute_id', $instituteId)
                ->where('branch_id', $branchId)
                ->where('period_start', $periodStart)
                ->exists();

            if ($existingRun) {
                throw ValidationException::withMessages([
                    'period' => 'Depreciation has already been posted for this period.',
                ]);
            }

            $assets = FixedAsset::query()
                ->where('institute_id', $instituteId)
                ->where(fn ($q) => $q->where('branch_id', $branchId)->orWhereNull('branch_id'))
                ->where('is_depreciable', true)
                ->whereIn('status', ['active', 'capitalized', 'under_maintenance'])
                ->whereNotNull('useful_life_months')
                ->get();

            $lines = [];
            $journal = null;

            foreach ($assets as $asset) {
                $start = $asset->depreciation_start_date?->toDateString() ?? $asset->capitalization_date?->toDateString();
                if ($start !== null && $start > $periodEnd) {
                    continue;
                }

                $existing = AssetDepreciationEntry::query()
                    ->where('institute_id', $instituteId)
                    ->where('asset_id', $asset->id)
                    ->count();

                $periodIndex = $existing + 1;
                $accumulated = (float) $asset->accumulated_depreciation;
                $units = $unitsByAsset[$asset->id] ?? null;

                $amount = round($this->engine->periodAmount($asset, $accumulated, $periodIndex, $units !== null ? (float) $units : null), 4);

                if ($amount <= 0) {
                    continue;
                }

                $newAccumulated = round($accumulated + $amount, 4);
                $closingNbv = round($asset->cost() - $newAccumulated - (float) $asset->impairment_amount, 4);

                AssetDepreciationEntry::create([
                    'institute_id' => $instituteId,
                    'asset_id' => $asset->id,
                    'period_start' => $periodStart,
                    'period_end' => $periodEnd,
                    'opening_nbv' => round($asset->cost() - $accumulated - (float) $asset->impairment_amount, 4),
                    'depreciation_amount' => $amount,
                    'accumulated_depreciation' => $newAccumulated,
                    'closing_nbv' => $closingNbv,
                    'method' => $asset->depreciation_method,
                    'rate' => $asset->depreciation_rate,
                    'units' => $units,
                    'actor_id' => $actorId,
                ]);

                $asset->forceFill([
                    'accumulated_depreciation' => $newAccumulated,
                    'updated_by' => $actorId,
                ])->save();

                $lines[] = ['asset' => $asset, 'amount' => $amount];
            }

            if ($lines === []) {
                throw ValidationException::withMessages(['period' => 'No depreciable assets with a positive amount for this period.']);
            }

            $journal = $this->accounting->depreciationJournal($instituteId, $branchId, $lines, $periodStart, $periodEnd, $actorId);

            $run = AssetDepreciationRun::create([
                'institute_id' => $instituteId,
                'branch_id' => $branchId,
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'status' => 'posted',
                'journal_id' => $journal->id,
                'actor_id' => $actorId,
            ]);

            foreach ($assets->whereIn('id', array_map(fn ($l) => $l['asset']->id, $lines)) as $asset) {
                if ($asset->accumulated_depreciation >= $asset->depreciableBase() - 0.00005) {
                    $asset->forceFill(['status' => 'fully_depreciated', 'updated_by' => $actorId])->save();
                }
            }

            return $run;
        });
    }

    public function transfer(FixedAsset $asset, array $data, ?int $actorId = null): AssetTransfer
    {
        $this->capabilities->assert($asset->institute_id, 'assets.transfer', $asset->branch_id);

        if ($asset->status === 'disposed') {
            throw ValidationException::withMessages(['asset' => 'A disposed asset cannot be transferred.']);
        }

        return DB::transaction(function () use ($asset, $data, $actorId) {
            $transfer = AssetTransfer::create([
                'institute_id' => $asset->institute_id,
                'asset_id' => $asset->id,
                'from_branch_id' => $asset->branch_id,
                'to_branch_id' => $data['to_branch_id'] ?? $asset->branch_id,
                'from_location_id' => $asset->location_id,
                'to_location_id' => $data['to_location_id'] ?? null,
                'from_department' => $asset->department,
                'to_department' => $data['to_department'] ?? null,
                'from_custodian' => $asset->responsible_person,
                'to_custodian' => $data['to_custodian'] ?? null,
                'transfer_date' => $data['transfer_date'] ?? now()->toDateString(),
                'reason' => $data['reason'] ?? null,
                'actor_id' => $actorId,
            ]);

            $asset->forceFill([
                'branch_id' => $transfer->to_branch_id,
                'location_id' => $transfer->to_location_id,
                'department' => $transfer->to_department,
                'responsible_person' => $transfer->to_custodian,
                'updated_by' => $actorId,
            ])->save();

            $this->log($asset->institute_id, $asset->id, 'transferred', null, [
                'to_branch_id' => $transfer->to_branch_id,
                'to_location_id' => $transfer->to_location_id,
            ], $actorId);

            return $transfer;
        });
    }

    public function dispose(FixedAsset $asset, string $disposalType, float $saleProceeds, ?string $reason, ?int $actorId = null, array $options = []): AssetDisposal
    {
        $this->capabilities->assert($asset->institute_id, 'assets.disposal', $asset->branch_id);

        if (in_array($asset->status, ['disposed', 'sold', 'scrapped', 'retired'], true)) {
            throw ValidationException::withMessages(['asset' => 'This asset is already disposed.']);
        }

        $nbv = $asset->netBookValue();
        $gainLoss = round($saleProceeds - $nbv, 4);

        return DB::transaction(function () use ($asset, $disposalType, $saleProceeds, $reason, $actorId, $options, $gainLoss) {
            $journal = $this->accounting->disposalJournal($asset, $saleProceeds, $gainLoss, $actorId, $options['journal_date'] ?? null, $options);

            $disposal = AssetDisposal::create([
                'institute_id' => $asset->institute_id,
                'asset_id' => $asset->id,
                'disposal_type' => $disposalType,
                'disposal_date' => $options['journal_date'] ?? now()->toDateString(),
                'sale_proceeds' => $saleProceeds,
                'gain_loss' => $gainLoss,
                'reason' => $reason,
                'journal_id' => $journal->id,
                'actor_id' => $actorId,
            ]);

            $asset->forceFill([
                'status' => $disposalType === 'sale' ? 'sold' : ($disposalType === 'scrap' ? 'scrapped' : 'disposed'),
                'updated_by' => $actorId,
            ])->save();

            $this->log($asset->institute_id, $asset->id, 'disposed', null, ['status' => $asset->status, 'gain_loss' => $gainLoss], $actorId);

            return $disposal;
        });
    }

    public function impair(FixedAsset $asset, float $amount, ?string $reason, ?int $actorId = null, ?string $journalDate = null): AssetImpairment
    {
        $this->capabilities->assert($asset->institute_id, 'assets.impairment', $asset->branch_id);

        if ($amount <= 0 || $amount > $asset->netBookValue()) {
            throw ValidationException::withMessages(['amount' => 'Impairment amount must be positive and not exceed the net book value.']);
        }

        return DB::transaction(function () use ($asset, $amount, $reason, $actorId, $journalDate) {
            $journal = $this->accounting->impairmentJournal($asset, $amount, $actorId, $journalDate);

            $impairment = AssetImpairment::create([
                'institute_id' => $asset->institute_id,
                'asset_id' => $asset->id,
                'impairment_date' => $journalDate ?? now()->toDateString(),
                'impairment_amount' => $amount,
                'reason' => $reason,
                'journal_id' => $journal->id,
                'actor_id' => $actorId,
            ]);

            $asset->forceFill([
                'impairment_amount' => round((float) $asset->impairment_amount + $amount, 4),
                'status' => 'impaired',
                'updated_by' => $actorId,
            ])->save();

            $this->log($asset->institute_id, $asset->id, 'impaired', null, ['amount' => $amount], $actorId);

            return $impairment;
        });
    }

    public function revalue(FixedAsset $asset, float $newCarryingAmount, ?string $reason, ?int $actorId = null, ?string $journalDate = null): AssetRevaluation
    {
        $this->capabilities->assert($asset->institute_id, 'assets.revaluation', $asset->branch_id);

        $previous = $asset->netBookValue();
        $difference = round($newCarryingAmount - $previous, 4);

        return DB::transaction(function () use ($asset, $newCarryingAmount, $previous, $difference, $reason, $actorId, $journalDate) {
            $journal = $this->accounting->revaluationJournal($asset, $difference, $actorId, $journalDate);

            $revaluation = AssetRevaluation::create([
                'institute_id' => $asset->institute_id,
                'asset_id' => $asset->id,
                'revaluation_date' => $journalDate ?? now()->toDateString(),
                'previous_carrying_amount' => $previous,
                'new_carrying_amount' => $newCarryingAmount,
                'difference' => $difference,
                'reason' => $reason,
                'status' => 'approved',
                'approved_at' => now(),
                'journal_id' => $journal->id,
                'actor_id' => $actorId,
            ]);

            $this->log($asset->institute_id, $asset->id, 'revalued', ['nbv' => $previous], ['nbv' => $newCarryingAmount, 'difference' => $difference], $actorId);

            return $revaluation;
        });
    }

    /**
     * Request a depreciation-method / useful-life / residual change. Posted
     * history is never rewritten; the new assumptions apply from effective_date.
     */
    public function changeMethod(FixedAsset $asset, array $data, ?int $actorId = null): AssetMethodChange
    {
        return DB::transaction(function () use ($asset, $data, $actorId) {
            $change = AssetMethodChange::create([
                'institute_id' => $asset->institute_id,
                'asset_id' => $asset->id,
                'old_method' => $asset->depreciation_method,
                'new_method' => $data['new_method'] ?? $asset->depreciation_method,
                'old_useful_life_months' => $asset->useful_life_months,
                'new_useful_life_months' => $data['new_useful_life_months'] ?? $asset->useful_life_months,
                'old_residual_value' => $asset->residual_value,
                'new_residual_value' => $data['new_residual_value'] ?? $asset->residual_value,
                'reason' => $data['reason'] ?? null,
                'status' => 'requested',
                'effective_date' => $data['effective_date'] ?? null,
                'actor_id' => $actorId,
            ]);

            $this->log($asset->institute_id, $asset->id, 'method_change_requested', null, $change->only(['new_method', 'new_useful_life_months', 'new_residual_value']), $actorId);

            return $change;
        });
    }

    /**
     * Approve a method change and apply the new assumptions to the asset.
     */
    public function approveMethodChange(AssetMethodChange $change, ?int $actorId = null): FixedAsset
    {
        return DB::transaction(function () use ($change, $actorId) {
            $asset = FixedAsset::query()->where('institute_id', $change->institute_id)->findOrFail($change->asset_id);

            if (! in_array($change->new_method, DepreciationMethodResolver::supported(), true)) {
                throw ValidationException::withMessages(['new_method' => 'Unsupported depreciation method.']);
            }

            $change->forceFill([
                'status' => 'approved',
                'approved_by' => $actorId,
                'approved_at' => now(),
                'effective_date' => $change->effective_date ?? now()->toDateString(),
            ])->save();

            $asset->forceFill([
                'depreciation_method' => $change->new_method,
                'useful_life_months' => $change->new_useful_life_months,
                'residual_value' => $change->new_residual_value,
                'updated_by' => $actorId,
            ])->save();

            $this->log($asset->institute_id, $asset->id, 'method_change_approved', null, ['new_method' => $change->new_method], $actorId);

            return $asset->fresh();
        });
    }

    private function validateAsset(int $instituteId, ?int $branchId, array $data, ?int $exceptAssetId = null): array
    {
        $validator = validator($data, [
            'category_id' => ['nullable', 'integer'],
            'location_id' => ['nullable', 'integer'],
            'vendor_party_id' => ['nullable', 'integer'],
            'asset_code' => ['nullable', 'string', 'max:60'],
            'name' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string'],
            'serial_number' => ['nullable', 'string', 'max:120'],
            'manufacturer' => ['nullable', 'string', 'max:120'],
            'model' => ['nullable', 'string', 'max:120'],
            'purchase_date' => ['nullable', 'date'],
            'capitalization_date' => ['nullable', 'date'],
            'purchase_document_no' => ['nullable', 'string', 'max:80'],
            'invoice_reference' => ['nullable', 'string', 'max:80'],
            'acquisition_cost' => ['nullable', 'numeric', 'min:0'],
            'additional_capitalized_cost' => ['nullable', 'numeric', 'min:0'],
            'residual_value' => ['nullable', 'numeric', 'min:0'],
            'useful_life_months' => ['nullable', 'integer', 'min:1'],
            'depreciation_method' => ['nullable', 'string', 'max:40'],
            'depreciation_frequency' => ['nullable', 'string', 'max:20'],
            'depreciation_convention' => ['nullable', 'string', 'max:20'],
            'depreciation_rate' => ['nullable', 'numeric', 'min:0'],
            'depreciation_start_date' => ['nullable', 'date'],
            'department' => ['nullable', 'string', 'max:80'],
            'responsible_person' => ['nullable', 'string', 'max:120'],
            'unit_of_measure' => ['nullable', 'string', 'max:40'],
            'total_units' => ['nullable', 'numeric', 'min:0'],
            'warranty_provider' => ['nullable', 'string', 'max:120'],
            'warranty_start' => ['nullable', 'date'],
            'warranty_end' => ['nullable', 'date'],
            'warranty_reference' => ['nullable', 'string', 'max:80'],
            'warranty_notes' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $data = $validator->validated();

        if (isset($data['depreciation_method']) && ! in_array($data['depreciation_method'], DepreciationMethodResolver::supported(), true)) {
            throw ValidationException::withMessages(['depreciation_method' => 'Unsupported depreciation method.']);
        }

        if (isset($data['category_id'])) {
            $owned = AssetCategory::query()
                ->where('institute_id', $instituteId)
                ->where(fn ($q) => $q->where('branch_id', $branchId)->orWhereNull('branch_id'))
                ->whereKey($data['category_id'])
                ->exists();

            if (! $owned) {
                throw ValidationException::withMessages(['category_id' => 'The category does not belong to this institute.']);
            }
        }

        return $data;
    }

    private function allocateAssetCode(int $instituteId): string
    {
        $taken = fn (string $code) => FixedAsset::query()
            ->where('institute_id', $instituteId)
            ->where('asset_code', $code)
            ->exists();

        for ($attempt = 0; $attempt < 10; $attempt++) {
            $candidate = 'FA-'.now()->format('Ymd').'-'.strtoupper(Str::random(5));
            if (! $taken($candidate)) {
                return $candidate;
            }
        }

        throw new \RuntimeException('Could not allocate a unique asset code.');
    }

    private function log(int $instituteId, ?int $assetId, string $event, ?array $old, ?array $new, ?int $actorId): void
    {
        AssetAuditLog::create([
            'institute_id' => $instituteId,
            'asset_id' => $assetId,
            'event' => $event,
            'actor_id' => $actorId,
            'old_value' => $old,
            'new_value' => $new,
            'created_at' => now(),
        ]);
    }
}

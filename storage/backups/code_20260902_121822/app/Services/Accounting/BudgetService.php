<?php

namespace App\Services\Accounting;

use App\Models\Budget;
use App\Models\BudgetLine;
use App\Models\BudgetVersion;
use App\Models\ChartOfAccount;
use App\Models\Currency;
use Illuminate\Support\Facades\DB;

class BudgetService
{
    public function __construct(
        private readonly AccountingAuditService $audit,
    ) {}

    public function list(int $instituteId, ?int $branchId, array $filters = [])
    {
        $query = Budget::query()
            ->where('institute_id', $instituteId)
            ->with(['fiscalYear', 'currency', 'latestVersion']);

        if ($branchId !== null) {
            $query->where(fn ($q) => $q->where('branch_id', $branchId)->orWhereNull('branch_id'));
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (isset($filters['type'])) {
            $query->where('type', $filters['type']);
        }
        if (isset($filters['fiscal_year_id'])) {
            $query->where('fiscal_year_id', $filters['fiscal_year_id']);
        }

        return $query->orderByDesc('created_at')->paginate(25);
    }

    public function create(int $instituteId, ?int $branchId, array $data, ?int $actorId = null): Budget
    {
        return DB::transaction(function () use ($instituteId, $branchId, $data, $actorId) {
            $budget = Budget::create([
                'institute_id' => $instituteId,
                'branch_id' => $branchId,
                'fiscal_year_id' => $data['fiscal_year_id'],
                'currency_id' => $data['currency_id'],
                'name' => $data['name'],
                'type' => $data['type'],
                'status' => 'draft',
                'version' => 1,
                'total_amount' => 0,
                'notes' => $data['notes'] ?? null,
                'created_by' => $actorId,
            ]);

            $version = BudgetVersion::create([
                'budget_id' => $budget->id,
                'institute_id' => $instituteId,
                'branch_id' => $branchId,
                'version' => 1,
                'status' => 'draft',
                'total_amount' => 0,
                'created_by' => $actorId,
            ]);

            if (!empty($data['lines'])) {
                $this->syncLines($version, $instituteId, $branchId, $data['lines']);
                $total = $version->lines()->sum('amount');
                $version->update(['total_amount' => $total]);
                $budget->update(['total_amount' => $total]);
            }

            $this->audit->log($instituteId, [
                'actor_id' => $actorId,
                'action' => 'create',
                'entity_type' => Budget::class,
                'entity_id' => $budget->id,
                'after_payload' => $budget->toArray(),
                'branch_id' => $branchId,
            ]);

            return $budget->fresh();
        });
    }

    public function update(Budget $budget, array $data, ?int $actorId = null): Budget
    {
        if (!$budget->isEditable()) {
            throw new \DomainException('Cannot edit a budget that is not in draft or rejected status.');
        }

        return DB::transaction(function () use ($budget, $data, $actorId) {
            $before = $budget->toArray();

            $budget->update([
                'name' => $data['name'] ?? $budget->name,
                'notes' => $data['notes'] ?? $budget->notes,
                'updated_by' => $actorId,
            ]);

            if (!empty($data['lines'])) {
                $version = BudgetVersion::where('budget_id', $budget->id)
                    ->where('version', $budget->version)
                    ->first();

                if ($version) {
                    $this->syncLines($version, $budget->institute_id, $budget->branch_id, $data['lines']);
                    $total = $version->lines()->sum('amount');
                    $version->update(['total_amount' => $total]);
                    $budget->update(['total_amount' => $total]);
                }
            }

            $this->audit->log($budget->institute_id, [
                'actor_id' => $actorId,
                'action' => 'update',
                'entity_type' => Budget::class,
                'entity_id' => $budget->id,
                'before_payload' => $before,
                'after_payload' => $budget->fresh()->toArray(),
                'branch_id' => $budget->branch_id,
            ]);

            return $budget->fresh();
        });
    }

    public function submit(Budget $budget, ?int $actorId = null): Budget
    {
        if (!$budget->isDraft() && !$budget->isRejected()) {
            throw new \DomainException('Only draft or rejected budgets can be submitted.');
        }

        return DB::transaction(function () use ($budget, $actorId) {
            $budget->update([
                'status' => 'submitted',
                'submitted_by' => $actorId,
                'submitted_at' => now(),
            ]);

            $version = BudgetVersion::where('budget_id', $budget->id)
                ->where('version', $budget->version)->first();
            if ($version) {
                $version->update([
                    'status' => 'submitted',
                    'submitted_by' => $actorId,
                    'submitted_at' => now(),
                ]);
            }

            $this->audit->log($budget->institute_id, [
                'actor_id' => $actorId,
                'action' => 'update',
                'entity_type' => Budget::class,
                'entity_id' => $budget->id,
                'after_payload' => ['status' => 'submitted'],
                'branch_id' => $budget->branch_id,
            ]);

            return $budget->fresh();
        });
    }

    public function approve(Budget $budget, ?int $actorId = null): Budget
    {
        if (!$budget->isSubmitted()) {
            throw new \DomainException('Only submitted budgets can be approved.');
        }

        return DB::transaction(function () use ($budget, $actorId) {
            $budget->update([
                'status' => 'approved',
                'approved_by' => $actorId,
                'approved_at' => now(),
            ]);

            $version = BudgetVersion::where('budget_id', $budget->id)
                ->where('version', $budget->version)->first();
            if ($version) {
                $version->update([
                    'status' => 'approved',
                    'approved_by' => $actorId,
                    'approved_at' => now(),
                ]);
            }

            $this->audit->log($budget->institute_id, [
                'actor_id' => $actorId,
                'action' => 'update',
                'entity_type' => Budget::class,
                'entity_id' => $budget->id,
                'after_payload' => ['status' => 'approved'],
                'branch_id' => $budget->branch_id,
            ]);

            return $budget->fresh();
        });
    }

    public function reject(Budget $budget, ?string $reason, ?int $actorId = null): Budget
    {
        if (!$budget->isSubmitted()) {
            throw new \DomainException('Only submitted budgets can be rejected.');
        }

        return DB::transaction(function () use ($budget, $reason, $actorId) {
            $budget->update([
                'status' => 'rejected',
                'notes' => $reason ?? $budget->notes,
            ]);

            $version = BudgetVersion::where('budget_id', $budget->id)
                ->where('version', $budget->version)->first();
            if ($version) {
                $version->update([
                    'status' => 'rejected',
                    'reason' => $reason,
                ]);
            }

            $this->audit->log($budget->institute_id, [
                'actor_id' => $actorId,
                'action' => 'update',
                'entity_type' => Budget::class,
                'entity_id' => $budget->id,
                'after_payload' => ['status' => 'rejected', 'reason' => $reason],
                'branch_id' => $budget->branch_id,
            ]);

            return $budget->fresh();
        });
    }

    public function lock(Budget $budget, ?int $actorId = null): Budget
    {
        if (!$budget->isApproved()) {
            throw new \DomainException('Only approved budgets can be locked.');
        }

        return DB::transaction(function () use ($budget, $actorId) {
            $budget->update([
                'status' => 'locked',
                'locked_by' => $actorId,
                'locked_at' => now(),
            ]);

            $version = BudgetVersion::where('budget_id', $budget->id)
                ->where('version', $budget->version)->first();
            if ($version) {
                $version->update(['status' => 'locked']);
            }

            $this->audit->log($budget->institute_id, [
                'actor_id' => $actorId,
                'action' => 'lock',
                'entity_type' => Budget::class,
                'entity_id' => $budget->id,
                'after_payload' => ['status' => 'locked'],
                'branch_id' => $budget->branch_id,
            ]);

            return $budget->fresh();
        });
    }

    public function revise(Budget $budget, array $data, ?int $actorId = null): Budget
    {
        if (!$budget->isApproved() && !$budget->isLocked()) {
            throw new \DomainException('Only approved or locked budgets can be revised.');
        }

        return DB::transaction(function () use ($budget, $data, $actorId) {
            $newVersion = $budget->version + 1;

            $budget->update([
                'status' => 'draft',
                'version' => $newVersion,
                'total_amount' => 0,
                'submitted_by' => null,
                'submitted_at' => null,
                'approved_by' => null,
                'approved_at' => null,
                'locked_by' => null,
                'locked_at' => null,
                'notes' => $data['notes'] ?? $budget->notes,
            ]);

            $version = BudgetVersion::create([
                'budget_id' => $budget->id,
                'institute_id' => $budget->institute_id,
                'branch_id' => $budget->branch_id,
                'version' => $newVersion,
                'status' => 'draft',
                'total_amount' => 0,
                'reason' => $data['reason'] ?? null,
                'created_by' => $actorId,
            ]);

            if (!empty($data['lines'])) {
                $this->syncLines($version, $budget->institute_id, $budget->branch_id, $data['lines']);
                $total = $version->lines()->sum('amount');
                $version->update(['total_amount' => $total]);
                $budget->update(['total_amount' => $total]);
            }

            $this->audit->log($budget->institute_id, [
                'actor_id' => $actorId,
                'action' => 'create',
                'entity_type' => BudgetVersion::class,
                'entity_id' => $version->id,
                'after_payload' => $version->toArray(),
                'branch_id' => $budget->branch_id,
            ]);

            return $budget->fresh();
        });
    }

    public function getBudget(int $instituteId, int $budgetId): Budget
    {
        return Budget::with(['fiscalYear', 'currency', 'versions.lines.account', 'versions.lines.period'])
            ->where('institute_id', $instituteId)
            ->findOrFail($budgetId);
    }

    public function accounts(int $instituteId, ?int $branchId)
    {
        $query = ChartOfAccount::where('institute_id', $instituteId)
            ->where('is_active', true)
            ->orderBy('code');

        if ($branchId !== null) {
            $query->where(fn ($q) => $q->where('branch_id', $branchId)->orWhereNull('branch_id'));
        }

        return $query->get();
    }

    public function defaultCurrency(int $instituteId): ?Currency
    {
        $code = app(AccountingSetupService::class)->getSetting($instituteId, 'base_currency', 'BDT');
        return Currency::where('code', $code)->first();
    }

    private function syncLines(BudgetVersion $version, int $instituteId, ?int $branchId, array $lines): void
    {
        BudgetLine::where('budget_version_id', $version->id)->delete();

        foreach ($lines as $line) {
            $month = $line['month'] ?? 0;
            $periodId = null;

            if ($month > 0) {
                $periodQuery = \App\Models\AccountingPeriod::where('fiscal_year_id', $version->budget->fiscal_year_id)
                    ->where('name', sprintf('%02d', $month));

                if ($branchId !== null) {
                    $periodQuery->where(fn ($q) => $q->where('branch_id', $branchId)->orWhereNull('branch_id'));
                }
                $period = $periodQuery->first();
                $periodId = $period?->id;
            }

            BudgetLine::create([
                'budget_version_id' => $version->id,
                'institute_id' => $instituteId,
                'branch_id' => $branchId,
                'coa_id' => $line['coa_id'],
                'accounting_period_id' => $periodId,
                'month' => $month,
                'amount' => $line['amount'] ?? 0,
                'notes' => $line['notes'] ?? null,
            ]);
        }
    }
}

<?php

namespace App\Livewire;

use App\Models\ChartOfAccount;
use App\Models\InstituteUser;
use App\Support\TenantContext;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class BankReconciliationList extends DataTable
{
    protected const VIEW = 'livewire.bank-reconciliation.list';

    public ?int $instituteId = null;
    public ?int $branchId = null;

    public function mount(): void
    {
        $request = request();
        $this->search = $request->query('q', '');
        $this->perPage = 20;

        $this->resolveTenant();
    }

    private function resolveTenant(): void
    {
        $user = request()->user();
        if ($user === null) {
            return;
        }

        if ($user instanceof InstituteUser) {
            $this->instituteId = (int) $user->institute_id;
            $this->branchId = $user->branch_id !== null ? (int) $user->branch_id : null;
        } else {
            $tenantId = TenantContext::id();
            if ($tenantId !== null) {
                $this->instituteId = (int) $tenantId;
            }
        }
    }

    protected function baseQuery(): Builder
    {
        return ChartOfAccount::query()
            ->where('is_bank', true)
            ->where('is_active', true);
    }

    protected function searchableColumns(): array
    {
        return ['code', 'name'];
    }

    protected function sortableColumns(): array
    {
        return ['code', 'name'];
    }

    protected function defaultSort(): ?string
    {
        return 'code';
    }

    protected function defaultSortDirection(): string
    {
        return 'asc';
    }

    public function getRows(): LengthAwarePaginator
    {
        $query = $this->baseQuery();

        if ($this->instituteId !== null) {
            $query->where('institute_id', $this->instituteId)
                ->when($this->branchId !== null, fn ($q) => $q->where(fn ($scope) => $scope
                    ->where('branch_id', $this->branchId)
                    ->orWhereNull('branch_id')));
        }

        // Search
        if (filled($this->search) && $this->searchableColumns()) {
            $query->where(function (Builder $q) {
                foreach ($this->searchableColumns() as $column) {
                    $q->orWhere($column, 'like', "%{$this->search}%");
                }
            });
        }

        // Sort
        $sortField = $this->sortField ?? 'code';
        $sortDirection = $this->sortDirection ?? 'asc';
        if (in_array($sortField, $this->sortableColumns(), true)) {
            $query->orderBy($sortField, $sortDirection);
        } else {
            $query->orderBy('code', 'asc');
        }

        return $query->paginate($this->perPage)->withQueryString();
    }

    public function render()
    {
        return view(self::VIEW, [
            'bankAccounts' => $this->getRows(),
        ]);
    }
}

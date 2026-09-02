<?php

namespace App\Livewire;

use App\Models\AccountingAuditTrail;
use App\Models\InstituteUser;
use App\Support\TenantContext;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class AuditTrailList extends DataTable
{
    protected const VIEW = 'livewire.audit-trail.list';

    public ?int $instituteId = null;
    public ?int $branchId = null;

    public function mount(): void
    {
        $request = request();
        $this->search = $request->query('q', '');
        $this->filters = [
            'action' => $request->query('action', ''),
            'entity_type' => $request->query('entity_type', ''),
            'actor_id' => $request->query('actor_id', ''),
            'from' => $request->query('from', ''),
            'to' => $request->query('to', ''),
        ];
        $this->perPage = (int) $request->query('per_page', 50);

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
        return AccountingAuditTrail::query();
    }

    protected function searchableColumns(): array
    {
        return ['actor_type', 'action', 'entity_type'];
    }

    protected function filterableColumns(): array
    {
        return [
            'action' => ['type' => 'exact'],
            'entity_type' => ['type' => 'exact'],
            'actor_id' => ['type' => 'exact'],
            'from' => ['type' => 'date_after', 'column' => 'created_at'],
            'to' => ['type' => 'date_before', 'column' => 'created_at'],
        ];
    }

    protected function sortableColumns(): array
    {
        return ['id', 'created_at', 'action', 'entity_type'];
    }

    protected function defaultSort(): ?string
    {
        return 'id';
    }

    protected function defaultSortDirection(): string
    {
        return 'desc';
    }

    public function getRows(): LengthAwarePaginator
    {
        if ($this->instituteId === null) {
            return new LengthAwarePaginator(collect(), 0, $this->perPage);
        }

        $query = AccountingAuditTrail::query()
            ->where('institute_id', $this->instituteId)
            ->when($this->branchId !== null, fn ($q) => $q->where(fn ($scope) => $scope
                ->where('branch_id', $this->branchId)
                ->orWhereNull('branch_id')));

        // Search
        if (filled($this->search)) {
            $like = '%'.$this->search.'%';
            $query->where(function ($q) use ($like) {
                $q->where('actor_type', 'like', $like)
                    ->orWhere('action', 'like', $like)
                    ->orWhere('entity_type', 'like', $like);
            });
        }

        // Filters
        foreach ($this->filters as $key => $value) {
            if (filled($value) && in_array($key, array_keys($this->filterableColumns()), true)) {
                $this->applyFilter($query, $key, $value, $this->filterableColumns()[$key]);
            }
        }

        // Sort
        $sortMap = [
            'id' => 'id',
            'created_at' => 'created_at',
            'action' => 'action',
            'entity_type' => 'entity_type',
        ];
        $sortField = $this->sortField ?? 'id';
        $sortDirection = $this->sortDirection ?? 'desc';
        if (isset($sortMap[$sortField])) {
            $query->orderBy($sortMap[$sortField], $sortDirection);
        } else {
            $query->orderBy('id', 'desc');
        }

        return $query->paginate($this->perPage)->withQueryString();
    }

    public function render()
    {
        return view(self::VIEW, [
            'entries' => $this->getRows(),
        ]);
    }
}

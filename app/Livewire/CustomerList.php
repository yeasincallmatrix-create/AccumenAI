<?php

namespace App\Livewire;

use App\Models\Party;
use Illuminate\Database\Eloquent\Builder;

class CustomerList extends DataTable
{
    protected const VIEW = 'livewire.customers.list';

    public array $visibleColumns = [];

    public function mount(): void
    {
        $user = auth()->user();
        $this->visibleColumns = $user->preference('columns_customers', [
            'name', 'phone', 'email', 'status', 'action',
        ]);
        $this->visibleColumns = array_values(array_intersect([
            'name', 'phone', 'email', 'status', 'action',
        ], $this->visibleColumns));

        $request = request();
        $this->filters = [
            'status' => $request->query('status', ''),
        ];
        $this->search = $request->query('q', '');

        $this->perPage = 20;
    }

    protected function baseQuery(): Builder
    {
        $user = auth()->user();
        $institute = $user?->institute;

        $query = Party::query()
            ->where('institute_id', $institute->id)
            ->whereIn('type', ['customer', 'both']);

        // Branch scoping: branch-scoped users see their branch + shared records
        $branchId = method_exists($user, 'branch_id') ? $user->branch_id : null;
        if ($branchId !== null) {
            $query->where(fn ($q) => $q->where('branch_id', $branchId)->orWhereNull('branch_id'));
        }

        return $query;
    }

    protected function searchableColumns(): array
    {
        return ['name', 'phone', 'email'];
    }

    protected function sortableColumns(): array
    {
        return ['id', 'name', 'phone', 'email'];
    }

    protected function applyFilter(Builder $query, string $key, mixed $value, array $config): void
    {
        match ($key) {
            'status' => $query->where('is_active', $value === 'active'),
            default => null,
        };
    }

    public function getRows(): \Illuminate\Pagination\LengthAwarePaginator
    {
        $query = $this->baseQuery();

        // Search
        if (filled($this->search)) {
            $query->where(function (Builder $q) {
                $q->where('name', 'like', "%{$this->search}%")
                    ->orWhere('phone', 'like', "%{$this->search}%")
                    ->orWhere('email', 'like', "%{$this->search}%");
            });
        }

        // Filters
        if (filled($this->filters['status'] ?? '')) {
            $query->where('is_active', $this->filters['status'] === 'active');
        }

        $query->latest('id');

        return $query->paginate($this->perPage)->withQueryString();
    }

    public function saveColumns(): void
    {
        $user = auth()->user();
        if ($user && method_exists($user, 'preference')) {
            $user->setPreference('columns_customers', $this->visibleColumns);
        }
    }

    public function toggleColumn(string $column): void
    {
        $index = array_search($column, $this->visibleColumns, true);
        if ($index !== false) {
            unset($this->visibleColumns[$index]);
            $this->visibleColumns = array_values($this->visibleColumns);
        } else {
            $this->visibleColumns[] = $column;
        }
        $this->saveColumns();
    }

    public function render()
    {
        $user = auth()->user();
        $institute = $user?->institute;

        return view(self::VIEW, [
            'customers' => $this->getRows(),
            'user' => $user,
            'institute' => $institute,
        ]);
    }
}

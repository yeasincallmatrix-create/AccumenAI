<?php

namespace App\Livewire;

use App\Models\Party;
use Illuminate\Database\Eloquent\Builder;

class PartyList extends DataTable
{
    protected const VIEW = 'livewire.parties.list';

    public array $visibleColumns = [];

    public function mount(): void
    {
        $user = auth()->user();
        $this->visibleColumns = $user->preference('columns_parties', [
            'serial', 'name', 'phone', 'email', 'type', 'status', 'action',
        ]);
        $this->visibleColumns = array_values(array_intersect([
            'serial', 'name', 'phone', 'email', 'type', 'status', 'action',
        ], $this->visibleColumns));

        $request = request();
        $this->filters = [
            'type' => $request->query('type', ''),
            'status' => $request->query('status', ''),
        ];
        $this->search = $request->query('q', '');

        $this->perPage = 20;
    }

    protected function baseQuery(): Builder
    {
        return Party::query()->with(['branch', 'customerGroup']);
    }

    protected function searchableColumns(): array
    {
        return ['name', 'phone', 'email'];
    }

    protected function sortableColumns(): array
    {
        return ['id', 'name', 'phone', 'email', 'type'];
    }

    protected function applyFilter(Builder $query, string $key, mixed $value, array $config): void
    {
        match ($key) {
            'type' => $query->where('type', $value),
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
        if (filled($this->filters['type'] ?? '')) {
            $query->where('type', $this->filters['type']);
        }
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
            $user->setPreference('columns_parties', $this->visibleColumns);
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
            'parties' => $this->getRows(),
            'user' => $user,
            'institute' => $institute,
            'types' => ['customer', 'supplier', 'both'],
        ]);
    }
}

<?php

namespace App\Livewire;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;
use Livewire\WithPagination;

abstract class DataTable extends Component
{
    use WithPagination;

    public string $search = '';
    public int $perPage = 20;
    public ?string $sortField = null;
    public ?string $sortDirection = 'asc';
    public array $filters = [];

    public string $paginationTheme = 'bootstrap';

    abstract protected function baseQuery(): Builder;

    protected function searchableColumns(): array
    {
        return [];
    }

    protected function filterableColumns(): array
    {
        return [];
    }

    protected function sortableColumns(): array
    {
        return [];
    }

    protected function eagerLoad(): array
    {
        return [];
    }

    protected function defaultSort(): ?string
    {
        return null;
    }

    protected function defaultSortDirection(): string
    {
        return 'desc';
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedFilters(): void
    {
        $this->resetPage();
    }

    public function updatedPerPage(): void
    {
        $this->resetPage();
    }

    public function sortBy(string $field): void
    {
        if (! in_array($field, $this->sortableColumns(), true)) {
            return;
        }

        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = $this->defaultSortDirection();
        }

        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->search = '';
        $this->filters = [];
        $this->sortField = $this->defaultSort();
        $this->sortDirection = $this->defaultSortDirection();
        $this->resetPage();
    }

    public function getRows(): LengthAwarePaginator
    {
        $query = $this->baseQuery();

        // Search
        if (filled($this->search) && $this->searchableColumns()) {
            $query->where(function (Builder $q) {
                foreach ($this->searchableColumns() as $column) {
                    if (str_contains($column, '.')) {
                        // Relation search: "relation.column"
                        [$relation, $col] = explode('.', $column, 2);
                        $q->orWhereHas($relation, fn ($r) => $r->where($col, 'like', "%{$this->search}%"));
                    } else {
                        $q->orWhere($column, 'like', "%{$this->search}%");
                    }
                }
            });
        }

        // Filters
        foreach ($this->filters as $key => $value) {
            if (filled($value) && in_array($key, array_keys($this->filterableColumns()), true)) {
                $filterConfig = $this->filterableColumns()[$key];
                $this->applyFilter($query, $key, $value, $filterConfig);
            }
        }

        // Sort
        if ($this->sortField && in_array($this->sortField, $this->sortableColumns(), true)) {
            $query->orderBy($this->sortField, $this->sortDirection);
        } else {
            $query->latest('id');
        }

        return $query->paginate($this->perPage)->withQueryString();
    }

    protected function applyFilter(Builder $query, string $key, mixed $value, array $config): void
    {
        $type = $config['type'] ?? 'exact';
        $column = $config['column'] ?? $key;

        match ($type) {
            'exact' => $query->where($column, $value),
            'date_before' => $query->where($column, '<=', $value),
            'date_after' => $query->where($column, '>=', $value),
            'relation' => $query->whereHas($config['relation'], fn ($q) => $q->where($config['relation_column'] ?? $column, $value)),
            default => $query->where($column, 'like', "%{$value}%"),
        };
    }

    public function render()
    {
        return view(static::VIEW, [
            'rows' => $this->getRows(),
        ]);
    }
}

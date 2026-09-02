<?php

namespace App\Livewire;

use App\Models\Invoice;
use App\Models\Party;
use Illuminate\Database\Eloquent\Builder;

class InvoiceList extends DataTable
{
    protected const VIEW = 'livewire.invoices.list';

    public array $visibleColumns = [];

    public function mount(): void
    {
        $user = auth()->user();
        $this->visibleColumns = $user->preference('columns_invoices', [
            'serial', 'invoice', 'customer', 'payable', 'paid', 'due', 'status', 'action',
        ]);
        $this->visibleColumns = array_values(array_intersect([
            'serial', 'invoice', 'customer', 'payable', 'paid', 'due', 'status', 'action',
        ], $this->visibleColumns));

        $request = request();
        $this->filters = [
            'status' => $request->query('status', ''),
            'party_id' => $request->query('party_id', ''),
            'from' => $request->query('from', ''),
            'to' => $request->query('to', ''),
        ];
        $this->search = $request->query('q', '');

        $this->perPage = 20;
    }

    protected function baseQuery(): Builder
    {
        return Invoice::query()->with(['party', 'student', 'currency']);
    }

    protected function searchableColumns(): array
    {
        return ['invoice_number'];
    }

    protected function sortableColumns(): array
    {
        return ['id', 'payable_amount', 'paid_amount', 'due_amount', 'created_at'];
    }

    protected function applyFilter(Builder $query, string $key, mixed $value, array $config): void
    {
        match ($key) {
            'status' => $query->where('status', $value),
            'party_id' => $query->where('party_id', (int) $value),
            'from' => $query->whereDate('created_at', '>=', $value),
            'to' => $query->whereDate('created_at', '<=', $value),
            default => null,
        };
    }

    public function getRows(): \Illuminate\Pagination\LengthAwarePaginator
    {
        $query = $this->baseQuery();

        if (filled($this->search)) {
            $query->where('invoice_number', 'like', "%{$this->search}%");
        }

        if (filled($this->filters['status'] ?? '')) {
            $query->where('status', $this->filters['status']);
        }
        if (filled($this->filters['party_id'] ?? '')) {
            $query->where('party_id', (int) $this->filters['party_id']);
        }
        if (filled($this->filters['from'] ?? '')) {
            $query->whereDate('created_at', '>=', $this->filters['from']);
        }
        if (filled($this->filters['to'] ?? '')) {
            $query->whereDate('created_at', '<=', $this->filters['to']);
        }

        $query->latest('id');

        return $query->paginate($this->perPage)->withQueryString();
    }

    public function saveColumns(): void
    {
        $user = auth()->user();
        if ($user && method_exists($user, 'preference')) {
            $user->setPreference('columns_invoices', $this->visibleColumns);
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

    public function isColumnVisible(string $column): bool
    {
        return in_array($column, $this->visibleColumns, true);
    }

    public function render()
    {
        $user = auth()->user();
        $institute = $user?->institute;

        $customers = Party::query()
            ->whereIn('type', ['customer', 'both'])
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        return view(self::VIEW, [
            'invoices' => $this->getRows(),
            'user' => $user,
            'institute' => $institute,
            'statuses' => ['unpaid', 'partial', 'paid', 'cancelled'],
            'customers' => $customers,
        ]);
    }
}

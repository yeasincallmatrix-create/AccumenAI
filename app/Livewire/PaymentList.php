<?php

namespace App\Livewire;

use App\Models\Payment;
use Illuminate\Database\Eloquent\Builder;

class PaymentList extends DataTable
{
    protected const VIEW = 'livewire.payments.list';

    public array $visibleColumns = [];

    public function mount(): void
    {
        $user = auth()->user();
        $this->visibleColumns = $user->preference('columns_payments', [
            'serial', 'invoice', 'customer', 'method', 'amount', 'paid_at', 'received_by', 'action',
        ]);
        $this->visibleColumns = array_values(array_intersect([
            'serial', 'invoice', 'customer', 'method', 'amount', 'paid_at', 'received_by', 'action',
        ], $this->visibleColumns));

        $request = request();
        $this->filters = [
            'from' => $request->query('from', ''),
            'to' => $request->query('to', ''),
        ];
        $this->search = $request->query('q', '');

        $this->perPage = 20;
    }

    protected function baseQuery(): Builder
    {
        return Payment::query()->with(['invoice', 'party', 'paymentMethod', 'receivedBy']);
    }

    protected function searchableColumns(): array
    {
        return ['invoice.invoice_number'];
    }

    protected function sortableColumns(): array
    {
        return ['id', 'amount', 'paid_at'];
    }

    protected function applyFilter(Builder $query, string $key, mixed $value, array $config): void
    {
        match ($key) {
            'from' => $query->whereDate('paid_at', '>=', $value),
            'to' => $query->whereDate('paid_at', '<=', $value),
            default => null,
        };
    }

    public function getRows(): \Illuminate\Pagination\LengthAwarePaginator
    {
        $query = $this->baseQuery();

        if (filled($this->search)) {
            $query->whereHas('invoice', fn (Builder $builder) => $builder->where('invoice_number', 'like', "%{$this->search}%"));
        }

        if (filled($this->filters['from'] ?? '')) {
            $query->whereDate('paid_at', '>=', $this->filters['from']);
        }
        if (filled($this->filters['to'] ?? '')) {
            $query->whereDate('paid_at', '<=', $this->filters['to']);
        }

        $query->latest('id');

        return $query->paginate($this->perPage)->withQueryString();
    }

    public function saveColumns(): void
    {
        $user = auth()->user();
        if ($user && method_exists($user, 'preference')) {
            $user->setPreference('columns_payments', $this->visibleColumns);
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

        return view(self::VIEW, [
            'payments' => $this->getRows(),
            'user' => $user,
            'institute' => $institute,
        ]);
    }
}

<?php

namespace App\Livewire;

use App\Models\Invoice;
use App\Models\Party;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Unified invoice list — Finance + Sales in one table with a source filter.
 *
 * Mirrors InvoiceList (search/status/party/date filters, per-user column
 * preferences, bootstrap pagination) but adds:
 *   - source filter: all | finance | sales, keyed on sales_order_id
 *   - source badge + total + date columns
 *   - links to the unified invoices.show route
 *
 * Existing finance.invoices.* and sales.invoices.* pages are untouched;
 * this component is only rendered by institute.invoices.index.
 */
class UnifiedInvoiceList extends DataTable
{
    protected const VIEW = 'livewire.unified-invoices.list';

    public const SOURCE_ALL = 'all';

    public const SOURCE_FINANCE = 'finance';

    public const SOURCE_SALES = 'sales';

    public array $visibleColumns = [];

    /** all | finance | sales */
    public string $source = self::SOURCE_ALL;

    public function mount(): void
    {
        $user = auth()->user();
        $default = [
            'serial', 'invoice', 'customer', 'source', 'total',
            'payable', 'paid', 'due', 'status', 'date', 'action',
        ];
        $this->visibleColumns = $user->preference('columns_unified_invoices', $default);
        $this->visibleColumns = array_values(array_intersect($default, $this->visibleColumns));

        $request = request();
        $this->filters = [
            'status' => $request->query('status', ''),
            'party_id' => $request->query('party_id', ''),
            'from' => $request->query('from', ''),
            'to' => $request->query('to', ''),
        ];
        $this->search = $request->query('q', '');
        $source = (string) $request->query('source', self::SOURCE_ALL);
        $this->source = in_array($source, [self::SOURCE_ALL, self::SOURCE_FINANCE, self::SOURCE_SALES], true)
            ? $source
            : self::SOURCE_ALL;

        $this->perPage = 20;
    }

    public function updatedSource(): void
    {
        $this->resetPage();
    }

    protected function baseQuery(): Builder
    {
        return Invoice::query()->with(['party', 'student', 'currency', 'salesOrder']);
    }

    protected function searchableColumns(): array
    {
        return ['invoice_number'];
    }

    protected function sortableColumns(): array
    {
        return ['id', 'total_amount', 'payable_amount', 'paid_amount', 'due_amount', 'created_at'];
    }

    /**
     * Apply the source filter: finance = no sales_order_id, sales = has one.
     */
    protected function applySourceFilter(Builder $query): void
    {
        match ($this->source) {
            self::SOURCE_FINANCE => $query->whereNull('sales_order_id'),
            self::SOURCE_SALES => $query->whereNotNull('sales_order_id'),
            default => null,
        };
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

    public function getRows(): LengthAwarePaginator
    {
        $query = $this->baseQuery();

        $this->applySourceFilter($query);

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

    public function resetFilters(): void
    {
        parent::resetFilters();
        $this->source = self::SOURCE_ALL;
    }

    public function saveColumns(): void
    {
        $user = auth()->user();
        if ($user && method_exists($user, 'preference')) {
            $user->setPreference('columns_unified_invoices', $this->visibleColumns);
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
            'sources' => [
                self::SOURCE_ALL => 'All sources',
                self::SOURCE_FINANCE => 'Finance',
                self::SOURCE_SALES => 'Sales',
            ],
        ]);
    }
}

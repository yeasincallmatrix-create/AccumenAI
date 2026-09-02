<?php

namespace App\Livewire;

use App\Models\InstituteUser;
use App\Models\Party;
use App\Support\TenantContext;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class PayableList extends DataTable
{
    protected const VIEW = 'livewire.payables.list';

    public string $asOfDate = '';
    public string $viewMode = 'index';
    public ?int $instituteId = null;
    public ?int $branchId = null;

    public function mount(): void
    {
        $request = request();
        $this->search = $request->query('q', '');
        $this->asOfDate = $request->query('as_of_date', now()->toDateString());
        $this->viewMode = $request->query('view', 'index');
        $this->perPage = 25;

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

    public function updatedAsOfDate(): void
    {
        $this->resetPage();
    }

    protected function baseQuery(): Builder
    {
        return Party::query();
    }

    protected function searchableColumns(): array
    {
        return [];
    }

    protected function sortableColumns(): array
    {
        return ['name', 'payable', 'receivable'];
    }

    protected function defaultSort(): ?string
    {
        return 'payable';
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

        $instituteId = $this->instituteId;
        $branchId = $this->branchId;
        $asOfDate = filled($this->asOfDate) ? $this->asOfDate : now()->toDateString();
        $asOfLiteral = "'".str_replace("'", "''", $asOfDate)."'";

        $query = Party::query()
            ->where('parties.institute_id', $instituteId)
            ->whereIn('parties.type', ['supplier', 'both'])
            ->where('parties.is_active', true)
            ->when($branchId !== null, fn ($q) => $q->where(fn ($scope) => $scope
                ->where('parties.branch_id', $branchId)
                ->orWhereNull('parties.branch_id')));

        // Search
        if (filled($this->search)) {
            $like = '%'.$this->search.'%';
            $query->where(function ($q) use ($like) {
                $q->where('parties.name', 'like', $like)
                    ->orWhere('parties.phone', 'like', $like)
                    ->orWhere('parties.email', 'like', $like);
            });
        }

        // Join with journal entries for DB-level aggregates
        $query->leftJoin('journal_entries as je', function ($join) use ($instituteId, $asOfDate) {
            $join->on('je.party_id', '=', 'parties.id')
                ->where('je.institute_id', $instituteId)
                ->whereDate('je.journal_date', '<=', $asOfDate)
                ->whereNull('je.deleted_at');
        })
        ->leftJoin('journals as j', function ($join) {
            $join->on('j.id', '=', 'je.journal_id')
                ->where('j.status', 'posted')
                ->whereNull('j.reversal_of')
                ->whereNull('j.deleted_at');
        })
        ->leftJoin('chart_of_accounts as coa', 'coa.id', '=', 'je.coa_id');

        $query->select(
            'parties.id',
            'parties.name',
            'parties.phone',
            'parties.email',
            'parties.type',
        )
        ->selectRaw('COALESCE(SUM(CASE WHEN coa.is_payable = 1 THEN je.credit - je.debit ELSE 0 END), 0) AS payable')
        ->selectRaw('COALESCE(SUM(CASE WHEN coa.is_receivable = 1 THEN je.debit - je.credit ELSE 0 END), 0) AS receivable')
        // Aging buckets (DB-level) — payable aging
        ->selectRaw("COALESCE(SUM(CASE WHEN coa.is_payable = 1 AND DATEDIFF({$asOfLiteral}, je.journal_date) <= 30 THEN je.credit - je.debit ELSE 0 END), 0) AS aging_current")
        ->selectRaw("COALESCE(SUM(CASE WHEN coa.is_payable = 1 AND DATEDIFF({$asOfLiteral}, je.journal_date) BETWEEN 31 AND 60 THEN je.credit - je.debit ELSE 0 END), 0) AS aging_31_60")
        ->selectRaw("COALESCE(SUM(CASE WHEN coa.is_payable = 1 AND DATEDIFF({$asOfLiteral}, je.journal_date) BETWEEN 61 AND 90 THEN je.credit - je.debit ELSE 0 END), 0) AS aging_61_90")
        ->selectRaw("COALESCE(SUM(CASE WHEN coa.is_payable = 1 AND DATEDIFF({$asOfLiteral}, je.journal_date) > 90 THEN je.credit - je.debit ELSE 0 END), 0) AS aging_91_plus")
        ->groupBy('parties.id', 'parties.name', 'parties.phone', 'parties.email', 'parties.type');

        // Sort
        $sortMap = [
            'name' => 'parties.name',
            'payable' => 'payable',
            'receivable' => 'receivable',
        ];
        $sortField = $this->sortField ?? 'payable';
        $sortDirection = $this->sortDirection ?? 'desc';
        if (isset($sortMap[$sortField])) {
            $query->orderBy($sortMap[$sortField], $sortDirection);
        } else {
            $query->orderBy('payable', 'desc');
        }

        $paginator = $query->paginate($this->perPage)->withQueryString();

        // Map rows to add computed fields
        $items = collect($paginator->items())->map(function ($row) {
            $row->payable = round((float) $row->payable, 4);
            $row->receivable = round((float) $row->receivable, 4);
            $row->net = round($row->payable - $row->receivable, 4);
            $row->aging = [
                'current' => round((float) $row->aging_current, 4),
                '31_60' => round((float) $row->aging_31_60, 4),
                '61_90' => round((float) $row->aging_61_90, 4),
                '91_plus' => round((float) $row->aging_91_plus, 4),
            ];

            return $row;
        });

        return new LengthAwarePaginator(
            $items,
            $paginator->total(),
            $paginator->perPage(),
            $paginator->currentPage(),
            ['path' => request()->url(), 'query' => request()->query()]
        );
    }

    public function render()
    {
        $rows = $this->getRows();

        return view(self::VIEW, [
            'suppliers' => $rows,
            'customers' => $rows,
            'asOfDate' => $this->asOfDate,
            'viewMode' => $this->viewMode,
        ]);
    }
}

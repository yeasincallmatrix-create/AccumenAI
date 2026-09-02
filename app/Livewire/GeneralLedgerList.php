<?php

namespace App\Livewire;

use App\Models\ChartOfAccount;
use App\Models\FiscalYear;
use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\JournalEntry;
use App\Support\TenantContext;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class GeneralLedgerList extends DataTable
{
    protected const VIEW = 'livewire.reports.general-ledger.list';

    public array $accountFilterOptions = [];
    public ?int $instituteId = null;
    public ?int $branchId = null;

    public function mount(): void
    {
        $request = request();
        $this->search = $request->query('q', '');
        $this->filters = [
            'account_id' => $request->query('account_id', ''),
            'from' => $request->query('from', ''),
            'to' => $request->query('to', ''),
            'fiscal_year_id' => $request->query('fiscal_year_id', ''),
        ];
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

        if ($this->instituteId === null) {
            return;
        }

        $this->accountFilterOptions = ChartOfAccount::query()
            ->where('institute_id', $this->instituteId)
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['id', 'code', 'name'])
            ->toArray();
    }

    protected function baseQuery(): Builder
    {
        return JournalEntry::query();
    }

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
        return ['journal_date', 'journal_no', 'code', 'debit', 'credit'];
    }

    protected function defaultSort(): ?string
    {
        return 'journal_date';
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

        $asOfDate = $this->filters['to'] ?? null;
        $fromDate = $this->filters['from'] ?? null;

        $query = DB::table('journal_entries as je')
            ->join('journals as j', 'j.id', '=', 'je.journal_id')
            ->join('chart_of_accounts as coa', 'coa.id', '=', 'je.coa_id')
            ->leftJoin('institute_users as iu', 'iu.id', '=', 'j.created_by')
            ->where('je.institute_id', $this->instituteId)
            ->where('j.status', 'posted')
            ->whereNull('j.reversal_of')
            ->whereNull('je.deleted_at')
            ->whereNull('j.deleted_at')
            ->when($this->branchId !== null, fn ($q) => $q->where('je.branch_id', $this->branchId));

        // Fiscal year date range filter
        $fyId = $this->filters['fiscal_year_id'] ?? '';
        if (filled($fyId)) {
            $fy = FiscalYear::query()
                ->where('institute_id', $this->instituteId)
                ->where('id', (int) $fyId)
                ->first();
            if ($fy !== null) {
                $fromDate = $fy->start_date->toDateString();
                $asOfDate = $fy->end_date->toDateString();
            }
        }

        // Date filters
        if (filled($fromDate)) {
            $query->whereDate('je.journal_date', '>=', $fromDate);
        }
        if (filled($asOfDate)) {
            $query->whereDate('je.journal_date', '<=', $asOfDate);
        }

        // Account filter
        $accountId = $this->filters['account_id'] ?? '';
        if (filled($accountId)) {
            $query->where('je.coa_id', (int) $accountId);
        }

        // Search
        if (filled($this->search)) {
            $like = '%'.$this->search.'%';
            $query->where(function ($q) use ($like) {
                $q->where('j.journal_no', 'like', $like)
                    ->orWhere('coa.name', 'like', $like)
                    ->orWhere('coa.code', 'like', $like)
                    ->orWhere('je.memo', 'like', $like)
                    ->orWhere('j.description', 'like', $like);
            });
        }

        // Select
        $query->select(
            'je.id',
            'j.id as journal_id',
            'j.journal_no',
            'je.journal_date',
            'coa.id as coa_id',
            'coa.code as code',
            'coa.name as account_name',
            'je.debit',
            'je.credit',
            'je.memo',
            'j.description as journal_description',
            'iu.name as created_by_name',
        );

        // Sort
        $sortMap = [
            'journal_date' => 'je.journal_date',
            'journal_no' => 'j.journal_no',
            'code' => 'coa.code',
            'debit' => 'je.debit',
            'credit' => 'je.credit',
        ];
        $sortField = $this->sortField ?? 'journal_date';
        $sortDirection = $this->sortDirection ?? 'desc';
        if (isset($sortMap[$sortField])) {
            $query->orderBy($sortMap[$sortField], $sortDirection);
        } else {
            $query->orderBy('je.journal_date', 'desc');
        }
        $query->orderBy('je.id', 'asc');

        $paginator = $query->paginate($this->perPage)->withQueryString();

        // Running balance: compute opening balance as-of the first item's journal_date,
        // then sum all entries up to (but not including) the first item.
        $items = $paginator->items();
        if ($items !== []) {
            $firstDate = $items[0]->journal_date;
            $firstJournalId = $items[0]->journal_id;
            $firstEntryId = $items[0]->id;

            // Opening balance: all posted entries strictly before the first row on this page
            $openingQuery = DB::table('journal_entries as je')
                ->join('journals as j', 'j.id', '=', 'je.journal_id')
                ->where('je.institute_id', $this->instituteId)
                ->where('j.status', 'posted')
                ->whereNull('j.reversal_of')
                ->whereNull('je.deleted_at')
                ->whereNull('j.deleted_at')
                ->where(fn ($q) => $q
                    ->whereDate('je.journal_date', '<', $firstDate)
                    ->orWhere(function ($q2) use ($firstDate, $firstJournalId) {
                        $q2->whereDate('je.journal_date', '=', $firstDate)
                            ->where('j.id', '<', $firstJournalId);
                    })
                    ->orWhere(function ($q3) use ($firstDate, $firstJournalId, $firstEntryId) {
                        $q3->whereDate('je.journal_date', '=', $firstDate)
                            ->where('j.id', '=', $firstJournalId)
                            ->where('je.id', '<', $firstEntryId);
                    })
                );

            if ($this->branchId !== null) {
                $openingQuery->where('je.branch_id', $this->branchId);
            }

            // If a specific account is filtered, only compute opening for that account
            if (filled($accountId)) {
                $openingQuery->where('je.coa_id', (int) $accountId);
            }

            // Opening balances from opening_balances table
            $obQuery = DB::table('opening_balances as ob')
                ->where('ob.institute_id', $this->instituteId)
                ->whereNull('ob.deleted_at')
                ->when($this->branchId !== null, fn ($q) => $q->where('ob.branch_id', $this->branchId))
                ->when(filled($accountId), fn ($q) => $q->where('ob.coa_id', (int) $accountId));

            $ob = $obQuery
                ->selectRaw('COALESCE(SUM(ob.debit), 0) - COALESCE(SUM(ob.credit), 0) AS balance')
                ->value('balance') ?? 0;

            $priorBalance = (float) $openingQuery
                ->selectRaw('COALESCE(SUM(je.debit), 0) - COALESCE(SUM(je.credit), 0) AS balance')
                ->value('balance') ?? 0;

            $running = (float) $ob + $priorBalance;

            $items = array_map(function ($row) use (&$running) {
                $running += (float) $row->debit - (float) $row->credit;
                $row->running_balance = round($running, 4);

                return $row;
            }, $items);
        }

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
        return view(self::VIEW, [
            'ledger' => $this->getRows(),
            'accountFilterOptions' => $this->accountFilterOptions,
        ]);
    }
}

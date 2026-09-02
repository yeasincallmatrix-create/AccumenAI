<?php

namespace App\Livewire;

use App\Models\Branch;
use App\Models\Journal;
use Illuminate\Database\Eloquent\Builder;

class JournalList extends DataTable
{
    protected const VIEW = 'livewire.journals.list';

    public array $branches = [];
    public array $types = ['sale', 'purchase', 'receipt', 'payment', 'journal', 'contra', 'opening', 'adjustment'];
    public array $statuses = ['draft', 'posted', 'reversed', 'void'];

    public function mount(): void
    {
        $request = request();
        $this->search = $request->query('q', '');
        $this->filters = [
            'type' => $request->query('type', ''),
            'status' => $request->query('status', ''),
            'branch_id' => $request->query('branch_id', ''),
            'from' => $request->query('from', ''),
            'to' => $request->query('to', ''),
        ];
        $this->perPage = 20;

        $user = $request->user();
        if ($user !== null) {
            $institute = $user instanceof \App\Models\InstituteUser
                ? \App\Models\Institute::query()->find($user->institute_id)
                : null;
            if ($institute !== null) {
                $this->branches = Branch::query()
                    ->where('institute_id', $institute->id)
                    ->orderBy('name')
                    ->get(['id', 'name'])
                    ->toArray();
            }
        }
    }

    protected function baseQuery(): Builder
    {
        return Journal::query()
            ->with(['creator', 'period', 'branch'])
            ->withSum('entries as total_debit', 'debit')
            ->withSum('entries as total_credit', 'credit');
    }

    protected function searchableColumns(): array
    {
        return ['journal_no', 'description'];
    }

    protected function sortableColumns(): array
    {
        return ['id', 'journal_no', 'journal_date', 'type', 'status'];
    }

    protected function defaultSort(): ?string
    {
        return 'id';
    }

    protected function defaultSortDirection(): string
    {
        return 'desc';
    }

    protected function applyFilter(Builder $query, string $key, mixed $value, array $config): void
    {
        match ($key) {
            'type' => $query->where('type', $value),
            'status' => $query->where('status', $value),
            'branch_id' => $query->where('branch_id', (int) $value),
            'from' => $query->whereDate('journal_date', '>=', $value),
            'to' => $query->whereDate('journal_date', '<=', $value),
            default => null,
        };
    }

    public function render()
    {
        return view(self::VIEW, [
            'journals' => $this->getRows(),
        ]);
    }
}

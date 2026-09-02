<div>
<div class="filter-card mb-3">
    <div class="filter-layout">
        <div class="filter-search-row align-items-end flex-wrap">
            <div class="filter-span">
                <label class="form-label mb-1">Search</label>
                <input type="search" class="form-control form-control-sm" wire:model.live.debounce.300ms="search" placeholder="Journal no, account code, account name, memo, description">
            </div>
            <div class="filter-span">
                <label class="form-label mb-1">Account</label>
                <select class="form-select form-select-sm" wire:model.live="filters.account_id">
                    <option value="">— All accounts —</option>
                    @foreach ($accountFilterOptions as $account)
                        <option value="{{ $account['id'] }}">{{ $account['code'] }} — {{ $account['name'] }}</option>
                    @endforeach
                </select>
            </div>
            <div class="filter-span">
                <label class="form-label mb-1">From</label>
                <input type="date" class="form-control form-control-sm" wire:model.live="filters.from">
            </div>
            <div class="filter-span">
                <label class="form-label mb-1">To</label>
                <input type="date" class="form-control form-control-sm" wire:model.live="filters.to">
            </div>
            <div class="filter-span">
                <button class="btn btn-outline-secondary btn-sm mt-1" wire:click="resetFilters"><i class="bi bi-arrow-counterclockwise"></i> Reset</button>
            </div>
        </div>
    </div>
</div>

<div class="admin-card">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th wire:click="sortBy('journal_date')" class="sortable">Date</th>
                    <th wire:click="sortBy('journal_no')" class="sortable">Journal</th>
                    <th>Account</th>
                    <th>Description</th>
                    <th>Created By</th>
                    <th class="text-end" wire:click="sortBy('debit')" class="sortable">Debit</th>
                    <th class="text-end" wire:click="sortBy('credit')" class="sortable">Credit</th>
                    <th class="text-end">Running balance</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($ledger as $row)
                    <tr>
                        <td>{{ $row->journal_date }}</td>
                        <td><a href="{{ route('finance.journals.show', ['journal' => $row->journal_id]) }}" class="text-decoration-none">{{ $row->journal_no }}</a></td>
                        <td>{{ $row->code }} — {{ $row->account_name }}</td>
                        <td>{{ $row->memo ?? $row->journal_description ?? '—' }}</td>
                        <td>{{ $row->created_by_name ?? '—' }}</td>
                        <td class="text-end">{{ $row->debit > 0 ? number_format((float) $row->debit, 2) : '—' }}</td>
                        <td class="text-end">{{ $row->credit > 0 ? number_format((float) $row->credit, 2) : '—' }}</td>
                        <td class="text-end fw-semibold">{{ number_format((float) $row->running_balance, 2) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">No ledger lines found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($ledger->hasPages())
        <div class="p-2 border-top">{{ $ledger->links('pagination::bootstrap-5') }}</div>
    @endif
</div>
</div>

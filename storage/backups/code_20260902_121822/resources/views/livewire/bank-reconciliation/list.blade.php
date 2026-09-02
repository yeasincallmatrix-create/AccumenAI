<div>
<div class="filter-card mb-3">
    <div class="filter-layout">
        <div class="filter-search-row align-items-end flex-wrap">
            <div class="filter-span">
                <label class="form-label mb-1">Search</label>
                <input type="search" class="form-control form-control-sm" wire:model.live.debounce.300ms="search" placeholder="Account code or name">
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
                    <th wire:click="sortBy('code')" class="sortable">Code</th>
                    <th wire:click="sortBy('name')" class="sortable">Account Name</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($bankAccounts as $account)
                    <tr>
                        <td class="text-muted">{{ $account->code }}</td>
                        <td>{{ $account->name }}</td>
                        <td class="text-end">
                            <a href="{{ route('accounting.bank-reconciliation.statements', ['accountId' => $account->id]) }}" class="btn btn-outline-primary btn-sm">
                                <i class="bi bi-list-ul"></i> Statements
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="text-center text-muted py-4">No bank accounts found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($bankAccounts->hasPages())
        <div class="p-2 border-top">{{ $bankAccounts->links('pagination::bootstrap-5') }}</div>
    @endif
</div>
</div>

<div>
<div class="standalone-heading">
    <h4>Chart of Accounts</h4>
    <p>Accounts define the ledger. Codes are unique per institute scope; posted accounts cannot be deleted (deactivate instead).</p>
    @if ($canManage)
        <a href="{{ route('finance.chart-of-accounts.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>New Account</a>
    @endif
</div>

<div class="filter-card mb-3">
    <div class="filter-layout">
        <div class="filter-search-row align-items-end flex-wrap">
            <div class="filter-span">
                <label class="form-label mb-1">Search</label>
                <input type="search" class="form-control form-control-sm" wire:model.live.debounce.300ms="search" placeholder="Name or code">
            </div>
            <div class="filter-span">
                <label class="form-label mb-1">Type</label>
                <select class="form-select form-select-sm" wire:model.live="filters.type">
                    <option value="">All types</option>
                    @foreach (['asset', 'liability', 'equity', 'income', 'expense'] as $type)
                        <option value="{{ $type }}">{{ ucfirst($type) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="filter-span">
                <label class="form-label mb-1">Status</label>
                <select class="form-select form-select-sm" wire:model.live="filters.status">
                    <option value="">All statuses</option>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
            </div>
            <div class="filter-span">
                <button class="btn btn-outline-secondary btn-sm mt-1" wire:click="resetFilters"><i class="bi bi-arrow-counterclockwise"></i> Reset</button>
            </div>
        </div>
    </div>
</div>

<div class="admin-card mb-3">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Account</th>
                    <th>Type</th>
                    <th>Flags</th>
                    <th>Status</th>
                    @if ($canManage)
                        <th class="text-end">Actions</th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @forelse ($accounts as $account)
                    <tr>
                        <td class="text-muted">{{ $account->code }}</td>
                        <td>
                            <span class="fw-semibold">{{ $account->name }}</span>
                            @if ($account->parent)
                                <div class="text-muted small">Parent: {{ $account->parent->name }}</div>
                            @endif
                        </td>
                        <td><span class="badge text-bg-light border">{{ ucfirst($account->type) }}</span></td>
                        <td>
                            @if ($account->is_cash)<span class="badge text-bg-info me-1">Cash</span>@endif
                            @if ($account->is_bank)<span class="badge text-bg-info me-1">Bank</span>@endif
                            @if ($account->is_receivable)<span class="badge text-bg-warning me-1">Receivable</span>@endif
                            @if ($account->is_payable)<span class="badge text-bg-warning me-1">Payable</span>@endif
                            @if ($account->is_system)<span class="badge text-bg-secondary">System</span>@endif
                        </td>
                        <td>
                            <span class="badge text-bg-{{ $account->is_active ? 'success' : 'secondary' }}">{{ $account->is_active ? 'Active' : 'Inactive' }}</span>
                        </td>
                        @if ($canManage)
                            <td class="text-end">
                                <a href="{{ route('finance.chart-of-accounts.edit', $account) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil-square"></i></a>
                                <button class="btn btn-sm btn-outline-{{ $account->is_active ? 'warning' : 'success' }}" wire:click="toggle({{ $account->id }})">
                                    <i class="bi bi-{{ $account->is_active ? 'pause-circle' : 'play-circle' }}"></i>
                                </button>
                                @if (! $account->is_system)
                                    <button class="btn btn-sm btn-outline-danger" wire:confirm="Delete this account?" wire:click="destroy({{ $account->id }})">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                @endif
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $canManage ? 7 : 6 }}" class="text-center text-muted py-4">No accounts found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($accounts->hasPages())
        <div class="p-2 border-top">{{ $accounts->links('pagination::bootstrap-5') }}</div>
    @endif
</div>
</div>

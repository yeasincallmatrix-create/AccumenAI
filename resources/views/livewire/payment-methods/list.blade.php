<div>
<div class="standalone-heading">
    <h4>Payment Methods</h4>
    <p>Cash, bank, mobile banking and card methods used when recording payments. Each method can link its default posting account.</p>
    @if ($canManage)
        <a href="{{ route('finance.payment-methods.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>New Payment Method</a>
    @endif
</div>

<div class="filter-card mb-3">
    <div class="filter-layout">
        <div class="filter-search-row align-items-end flex-wrap">
            <div class="filter-span">
                <label class="form-label mb-1">Search</label>
                <input type="search" class="form-control form-control-sm" wire:model.live.debounce.300ms="search" placeholder="Method name">
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
                    <th>Name</th>
                    <th>Default account</th>
                    <th>Scope</th>
                    <th>Status</th>
                    @if ($canManage)
                        <th class="text-end">Actions</th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @forelse ($methods as $method)
                    <tr>
                        <td>
                            <span class="fw-semibold">{{ $method->name }}</span>
                            @if ($method->is_system)
                                <span class="badge text-bg-secondary ms-1">System</span>
                            @endif
                        </td>
                        <td>
                            @if ($method->coa)
                                {{ $method->coa->code }} — {{ $method->coa->name }}
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td>
                            @if ($method->branch)
                                {{ $method->branch->name }}
                            @else
                                <span class="badge text-bg-light border">All branches</span>
                            @endif
                        </td>
                        <td>
                            <span class="badge text-bg-{{ $method->is_active ? 'success' : 'secondary' }}">{{ $method->is_active ? 'Active' : 'Inactive' }}</span>
                        </td>
                        @if ($canManage)
                            <td class="text-end">
                                <a href="{{ route('finance.payment-methods.edit', $method) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil-square"></i></a>
                                <button class="btn btn-sm btn-outline-{{ $method->is_active ? 'warning' : 'success' }}" wire:click="toggle({{ $method->id }})">
                                    <i class="bi bi-{{ $method->is_active ? 'pause-circle' : 'play-circle' }}"></i>
                                </button>
                                @if (! $method->is_system)
                                    <button class="btn btn-sm btn-outline-danger" wire:confirm="Delete this payment method?" wire:click="destroy({{ $method->id }})">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                @endif
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $canManage ? 5 : 4 }}" class="text-center text-muted py-4">No payment methods found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($methods->hasPages())
        <div class="p-2 border-top">{{ $methods->links('pagination::bootstrap-5') }}</div>
    @endif
</div>
</div>

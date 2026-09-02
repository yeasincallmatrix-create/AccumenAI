<div>
<div class="filter-card mb-3">
    <div class="filter-layout">
        <div class="filter-search-row align-items-end flex-wrap">
            <div class="filter-span">
                <label class="form-label mb-1">Search</label>
                <input type="search" class="form-control form-control-sm" wire:model.live.debounce.300ms="search" placeholder="Actor, action, entity type">
            </div>
            <div class="filter-span">
                <label class="form-label mb-1">Action</label>
                <select class="form-select form-select-sm" wire:model.live="filters.action">
                    <option value="">All actions</option>
                    @foreach (['create', 'update', 'delete', 'post', 'reverse', 'void'] as $action)
                        <option value="{{ $action }}">{{ ucfirst($action) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="filter-span">
                <label class="form-label mb-1">Entity type</label>
                <input type="search" class="form-control form-control-sm" wire:model.live.debounce.300ms="filters.entity_type" placeholder="e.g. journal">
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
                <label class="form-label mb-1">Per page</label>
                <select class="form-select form-select-sm" wire:model.live="perPage">
                    @foreach ([50, 100, 200] as $size)
                        <option value="{{ $size }}">{{ $size }}</option>
                    @endforeach
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
                    <th>#</th>
                    <th wire:click="sortBy('created_at')" class="sortable">Time</th>
                    <th>Actor</th>
                    <th wire:click="sortBy('action')" class="sortable">Action</th>
                    <th wire:click="sortBy('entity_type')" class="sortable">Entity</th>
                    <th>Details</th>
                    <th>Branch</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($entries as $entry)
                    <tr>
                        <td class="text-muted">{{ $entries->firstItem() + $loop->index }}</td>
                        <td>{{ $entry->created_at?->format('Y-m-d H:i') }}</td>
                        <td>
                            <span class="badge text-bg-light border">{{ $entry->actor_type }}</span>
                            @if ($entry->actor_id)
                                <span class="text-muted">#{{ $entry->actor_id }}</span>
                            @else
                                <span class="text-muted">system</span>
                            @endif
                        </td>
                        <td>
                            <span class="badge text-bg-{{ $entry->action === 'create' ? 'success' : ($entry->action === 'delete' ? 'danger' : 'info') }}">{{ $entry->action }}</span>
                        </td>
                        <td>
                            <span>{{ $entry->entity_type }}</span>
                            <span class="text-muted">#{{ $entry->entity_id }}</span>
                        </td>
                        <td>
                            @if ($entry->after_payload)
                                <span class="small text-muted">{{ json_encode($entry->after_payload, JSON_UNESCAPED_SLASHES) }}</span>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td>
                            @if ($entry->branch_id)
                                <span class="text-muted">#{{ $entry->branch_id }}</span>
                            @else
                                <span class="badge text-bg-light border">All branches</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center text-muted py-4">No audit events yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($entries->hasPages())
        <div class="p-2 border-top">{{ $entries->links('pagination::bootstrap-5') }}</div>
    @endif
</div>
</div>

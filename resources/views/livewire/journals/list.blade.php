<div>
<div class="standalone-heading">
    <h4>Journals</h4>
    <p>Double-entry posting documents. Drafts do not affect the ledger; posted journals can only be reversed, never hard-deleted.</p>
    <a href="{{ route('finance.journals.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>New Journal</a>
</div>

<div class="filter-card mb-3">
    <div class="filter-layout">
        <div class="filter-search-row align-items-end flex-wrap">
            <div class="filter-span">
                <label class="form-label mb-1">Search</label>
                <input type="search" class="form-control form-control-sm" wire:model.live.debounce.300ms="search" placeholder="Journal number or description">
            </div>
            <div class="filter-span">
                <label class="form-label mb-1">Type</label>
                <select class="form-select form-select-sm" wire:model.live="filters.type">
                    <option value="">All types</option>
                    @foreach ($types as $type)
                        <option value="{{ $type }}">{{ ucfirst($type) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="filter-span">
                <label class="form-label mb-1">Status</label>
                <select class="form-select form-select-sm" wire:model.live="filters.status">
                    <option value="">All statuses</option>
                    @foreach ($statuses as $status)
                        <option value="{{ $status }}">{{ ucfirst($status) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="filter-span">
                <label class="form-label mb-1">Branch</label>
                <select class="form-select form-select-sm" wire:model.live="filters.branch_id">
                    <option value="">All branches</option>
                    @foreach ($branches as $branch)
                        <option value="{{ $branch['id'] }}">{{ $branch['name'] }}</option>
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

<div class="admin-card mb-3">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Journal No</th>
                    <th>Date</th>
                    <th>Type</th>
                    <th>Description</th>
                    <th>Branch</th>
                    <th class="text-end">Debit</th>
                    <th class="text-end">Credit</th>
                    <th>Status</th>
                    <th>Created by</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($journals as $journal)
                    <tr>
                        <td class="text-muted">{{ $journals->firstItem() + $loop->index }}</td>
                        <td><a href="{{ route('finance.journals.show', $journal) }}" class="text-decoration-none">{{ $journal->journal_no }}</a></td>
                        <td>{{ $journal->journal_date?->format('Y-m-d') }}</td>
                        <td><span class="badge text-bg-light border">{{ ucfirst($journal->type) }}</span></td>
                        <td>{{ $journal->description }}</td>
                        <td>
                            @if ($journal->branch)
                                {{ $journal->branch->name }}
                            @else
                                <span class="badge text-bg-light border">All branches</span>
                            @endif
                        </td>
                        <td class="text-end">{{ number_format((float) ($journal->total_debit ?? 0), 2) }}</td>
                        <td class="text-end">{{ number_format((float) ($journal->total_credit ?? 0), 2) }}</td>
                        <td>
                            <span class="badge text-bg-{{ $journal->status === 'posted' ? 'success' : ($journal->status === 'draft' ? 'warning' : 'secondary') }}">{{ $journal->status }}</span>
                        </td>
                        <td>{{ $journal->creator?->name ?? '—' }}</td>
                        <td class="text-end">
                            <a href="{{ route('finance.journals.show', $journal) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i></a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="11" class="text-center text-muted py-4">No journals found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($journals->hasPages())
        <div class="p-2 border-top">{{ $journals->links('pagination::bootstrap-5') }}</div>
    @endif
</div>
</div>

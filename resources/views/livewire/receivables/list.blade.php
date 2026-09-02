<div>
<div class="filter-card mb-3">
    <div class="filter-layout">
        <div class="filter-search-row align-items-end flex-wrap">
            <div class="filter-span">
                <label class="form-label mb-1">Search</label>
                <input type="search" class="form-control form-control-sm" wire:model.live.debounce.300ms="search" placeholder="Customer name, phone, or email">
            </div>
            <div class="filter-span">
                <label class="form-label mb-1">As of</label>
                <input type="date" class="form-control form-control-sm" wire:model.live="asOfDate">
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
                    <th>#</th>
                    <th wire:click="sortBy('name')" class="sortable">Customer</th>
                    <th>Phone</th>
                    <th class="text-end">Current</th>
                    <th class="text-end">31–60</th>
                    <th class="text-end">61–90</th>
                    <th class="text-end">90+</th>
                    <th class="text-end" wire:click="sortBy('receivable')" class="sortable">Receivable</th>
                    <th class="text-end">Net</th>
                    <th class="text-end">Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($customers as $customer)
                    <tr>
                        <td class="text-muted">{{ $customers->firstItem() + $loop->index }}</td>
                        <td>{{ $customer->name }}</td>
                        <td>{{ $customer->phone ?? '—' }}</td>
                        <td class="text-end">{{ number_format((float) $customer->aging['current'], 2) }}</td>
                        <td class="text-end">{{ number_format((float) $customer->aging['31_60'], 2) }}</td>
                        <td class="text-end">{{ number_format((float) $customer->aging['61_90'], 2) }}</td>
                        <td class="text-end">{{ number_format((float) $customer->aging['91_plus'], 2) }}</td>
                        <td class="text-end fw-semibold">{{ number_format((float) $customer->receivable, 2) }}</td>
                        <td class="text-end">{{ number_format((float) $customer->net, 2) }}</td>
                        <td class="text-end">
                            <a href="{{ route('accounting.receivables.statement', $customer->id) }}" class="btn btn-outline-primary btn-sm">
                                <i class="bi bi-file-earmark-text"></i> Statement
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="10" class="text-center text-muted py-4">No outstanding receivables.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($customers->hasPages())
        <div class="p-2 border-top">{{ $customers->links('pagination::bootstrap-5') }}</div>
    @endif
</div>
</div>

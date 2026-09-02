<div>
<div class="standalone-heading">
    <h4>Invoices</h4>
    <p>Accounts-receivable documents. Creating an invoice posts a sale journal to the ledger; payments reduce the outstanding balance.</p>
    <a href="{{ route('finance.invoices.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>New Invoice</a>
</div>

<div class="filter-card mb-3">
    <div class="filter-layout">
        <div class="filter-search-row align-items-end flex-wrap">
            <div class="filter-span">
                <label class="form-label mb-1">Search</label>
                <input type="search" class="form-control form-control-sm" wire:model.live.debounce.300ms="search" placeholder="Invoice number">
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
                <label class="form-label mb-1">Customer</label>
                <select class="form-select form-select-sm" wire:model.live="filters.party_id">
                    <option value="">All customers</option>
                    @foreach ($customers as $customer)
                        <option value="{{ $customer->id }}">{{ $customer->name }}</option>
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
                <button class="btn btn-outline-secondary btn-sm mt-1" wire:click="resetFilters">Reset</button>
            </div>
        </div>
    </div>
</div>

<div class="admin-card mb-3">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    @if (in_array('serial', $visibleColumns, true))<th class="text-muted">#</th>@endif
                    @if (in_array('invoice', $visibleColumns, true))<th>Invoice</th>@endif
                    @if (in_array('customer', $visibleColumns, true))<th>Customer</th>@endif
                    @if (in_array('payable', $visibleColumns, true))<th class="text-end">Payable</th>@endif
                    @if (in_array('paid', $visibleColumns, true))<th class="text-end">Paid</th>@endif
                    @if (in_array('due', $visibleColumns, true))<th class="text-end">Due</th>@endif
                    @if (in_array('status', $visibleColumns, true))<th>Status</th>@endif
                    @if (in_array('action', $visibleColumns, true))<th class="text-end">Actions</th>@endif
                </tr>
            </thead>
            <tbody>
                @forelse ($invoices as $invoice)
                    <tr>
                        @if (in_array('serial', $visibleColumns, true))<td class="text-muted">{{ $invoices->firstItem() + $loop->index }}</td>@endif
                        @if (in_array('invoice', $visibleColumns, true))<td>
                            <a href="{{ route('finance.invoices.show', $invoice) }}" class="text-decoration-none">{{ $invoice->invoice_number }}</a>
                            <div class="text-muted small">{{ $invoice->created_at?->format('Y-m-d') }}</div>
                        </td>@endif
                        @if (in_array('customer', $visibleColumns, true))<td>{{ $invoice->party?->name ?? $invoice->student?->name ?? '—' }}</td>@endif
                        @if (in_array('payable', $visibleColumns, true))<td class="text-end">{{ number_format((float) $invoice->payable_amount, 2) }}</td>@endif
                        @if (in_array('paid', $visibleColumns, true))<td class="text-end">{{ number_format((float) $invoice->paid_amount, 2) }}</td>@endif
                        @if (in_array('due', $visibleColumns, true))<td class="text-end fw-semibold">{{ number_format((float) $invoice->due_amount, 2) }}</td>@endif
                        @if (in_array('status', $visibleColumns, true))<td>
                            <span class="badge text-bg-{{ $invoice->status === 'paid' ? 'success' : ($invoice->status === 'partial' ? 'warning' : ($invoice->status === 'cancelled' ? 'secondary' : 'danger')) }}">{{ $invoice->status }}</span>
                        </td>@endif
                        @if (in_array('action', $visibleColumns, true))<td class="text-end">
                            <a href="{{ route('finance.invoices.show', $invoice) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i></a>
                        </td>@endif
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">No invoices found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($invoices->hasPages())
        <div class="p-2 border-top d-flex flex-column align-items-center gap-2">
            {{ $invoices->links('pagination::bootstrap-5') }}
            <span class="text-muted small">{{ $invoices->total() }} invoices</span>
        </div>
    @endif
</div>
</div>

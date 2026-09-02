<div>
<div class="standalone-heading">
    <h4>Payments</h4>
    <p>AR receipts recorded against invoices. Every payment posts a receipt journal (debit cash / credit Accounts Receivable).</p>
</div>

<div class="filter-card mb-3">
    <div class="filter-layout">
        <div class="filter-search-row align-items-end flex-wrap">
            <div class="filter-span">
                <label class="form-label mb-1">Search</label>
                <input type="search" class="form-control form-control-sm" wire:model.live.debounce.300ms="search" placeholder="Invoice number">
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
                    @if (in_array('method', $visibleColumns, true))<th>Method</th>@endif
                    @if (in_array('amount', $visibleColumns, true))<th class="text-end">Amount</th>@endif
                    @if (in_array('paid_at', $visibleColumns, true))<th>Paid at</th>@endif
                    @if (in_array('received_by', $visibleColumns, true))<th>Received by</th>@endif
                    @if (in_array('action', $visibleColumns, true))<th class="text-end">Actions</th>@endif
                </tr>
            </thead>
            <tbody>
                @forelse ($payments as $payment)
                    <tr>
                        @if (in_array('serial', $visibleColumns, true))<td class="text-muted">{{ $payments->firstItem() + $loop->index }}</td>@endif
                        @if (in_array('invoice', $visibleColumns, true))<td>
                            <a href="{{ route('finance.invoices.show', $payment->invoice) }}" class="text-decoration-none">{{ $payment->invoice?->invoice_number ?? '—' }}</a>
                        </td>@endif
                        @if (in_array('customer', $visibleColumns, true))<td>{{ $payment->party?->name ?? '—' }}</td>@endif
                        @if (in_array('method', $visibleColumns, true))<td>
                            <span class="badge text-bg-light border">{{ $payment->payment_method }}</span>
                            @if ($payment->paymentMethod)
                                <span class="small text-muted">{{ $payment->paymentMethod->name }}</span>
                            @endif
                        </td>@endif
                        @if (in_array('amount', $visibleColumns, true))<td class="text-end fw-semibold">{{ number_format((float) $payment->amount, 2) }}</td>@endif
                        @if (in_array('paid_at', $visibleColumns, true))<td>{{ $payment->paid_at?->format('Y-m-d H:i') }}</td>@endif
                        @if (in_array('received_by', $visibleColumns, true))<td>{{ $payment->receivedBy?->name ?? '—' }}</td>@endif
                        @if (in_array('action', $visibleColumns, true))<td class="text-end">
                            @if ($payment->journal?->status === 'posted')
                                <form method="POST" action="{{ route('finance.payments.reverse', $payment) }}" class="d-inline" data-ajax-submit="1" data-confirm="Reverse this payment?">
                                    @csrf
                                    <button class="btn btn-sm btn-outline-danger" type="submit" title="Reverse"><i class="bi bi-arrow-counterclockwise"></i></button>
                                </form>
                            @endif
                        </td>@endif
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">No payments found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($payments->hasPages())
        <div class="p-2 border-top d-flex flex-column align-items-center gap-2">
            {{ $payments->links('pagination::bootstrap-5') }}
            <span class="text-muted small">{{ $payments->total() }} payments</span>
        </div>
    @endif
</div>
</div>

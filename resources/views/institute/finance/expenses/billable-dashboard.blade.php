@extends('layouts.standalone')

@section('title', 'Billable Expenses Dashboard — AccumenAI')
@section('page_title', 'Finance')

@section('content')

<div class="standalone-heading">
    <h4>Billable Expenses</h4>
    <p>Unbilled expenses grouped by customer. Select expenses and generate invoices.</p>
    <div class="d-flex gap-2">
        <a href="{{ route('finance.expenses.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>All Expenses</a>
    </div>
</div>

@if ($byCustomer->isEmpty())
    <div class="admin-card text-center py-5">
        <i class="bi bi-check-circle text-success" style="font-size:3rem;"></i>
        <h5 class="mt-3">All caught up!</h5>
        <p class="text-muted">No unbilled expenses to invoice.</p>
    </div>
@else
    <form id="invoiceForm">
        @csrf
        @foreach ($byCustomer as $customerId => $group)
            <div class="admin-card mb-3">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <h6 class="mb-0">{{ $group['customer']?->name ?? 'Unknown Customer' }}</h6>
                        <small class="text-muted">{{ $group['count'] }} unbilled expense(s) — Total: {{ number_format($group['total'], 2) }}</small>
                    </div>
                    <div class="d-flex gap-2 align-items-center">
                        <input type="hidden" name="customer_id" value="{{ $customerId }}">
                        <button type="button" class="btn btn-sm btn-outline-primary generate-invoice-btn"
                            data-customer-id="{{ $customerId }}"
                            data-customer-name="{{ $group['customer']?->name ?? 'Unknown' }}"
                            data-count="{{ $group['count'] }}"
                            data-total="{{ number_format($group['total'], 2) }}">
                            <i class="bi bi-receipt me-1"></i>Generate Invoice
                        </button>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                            <tr>
                                <th width="40"><input type="checkbox" class="form-check-input select-all" data-customer="{{ $customerId }}"></th>
                                <th>Expense #</th>
                                <th>Date</th>
                                <th>Vendor</th>
                                <th>Category</th>
                                <th class="text-end">Cost</th>
                                <th class="text-end">Billable</th>
                                <th class="text-end">Markup</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($group['expenses'] as $expense)
                                <tr>
                                    <td><input type="checkbox" class="form-check-input expense-checkbox" name="expense_ids[]" value="{{ $expense->id }}" data-customer="{{ $customerId }}"></td>
                                    <td><a href="{{ route('finance.expenses.show', $expense) }}">{{ $expense->expense_number }}</a></td>
                                    <td>{{ $expense->expense_date?->format('d M Y') }}</td>
                                    <td>{{ $expense->vendor_name ?? '—' }}</td>
                                    <td>{{ $expense->categoryLabel() }}</td>
                                    <td class="text-end">{{ number_format($expense->amount, 2) }}</td>
                                    <td class="text-end">{{ number_format($expense->billable_amount ?? $expense->computeBillableAmount(), 2) }}</td>
                                    <td class="text-end">{{ $expense->markup_percentage }}%</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endforeach
    </form>
@endif

<!-- Generate Invoice Modal -->
<div class="modal fade" id="invoiceModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Generate Invoice</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="{{ route('finance.expenses.generate-invoice') }}">
                @csrf
                <div class="modal-body">
                    <input type="hidden" name="customer_id" id="modal_customer_id">
                    <div id="selected_expenses_container"></div>

                    <div class="mb-3">
                        <label class="form-label">Customer</label>
                        <input type="text" class="form-control" id="modal_customer_name" readonly>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Invoice Date <span class="text-danger">*</span></label>
                            <input type="date" name="invoice_date" class="form-control" value="{{ now()->toDateString() }}" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Due Date <span class="text-danger">*</span></label>
                            <input type="date" name="due_date" class="form-control" value="{{ now()->addDays(30)->toDateString() }}" required>
                        </div>
                    </div>
                    <div class="mt-3">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" class="form-control" rows="2">Expense reimbursement invoice</textarea>
                    </div>
                    <div class="mt-3 p-3 bg-light rounded">
                        <div>Expenses: <strong id="modal_count">0</strong></div>
                        <div>Total: <strong id="modal_total">0.00</strong></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-receipt me-1"></i>Generate Invoice</button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
document.querySelectorAll('.select-all').forEach(function(el) {
    el.addEventListener('change', function() {
        const customer = this.dataset.customer;
        document.querySelectorAll('.expense-checkbox[data-customer="' + customer + '"]').forEach(function(cb) {
            cb.checked = el.checked;
        });
    });
});

document.querySelectorAll('.generate-invoice-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
        const customerId = this.dataset.customerId;
        const customerName = this.dataset.customerName;

        const selected = document.querySelectorAll('.expense-checkbox[data-customer="' + customerId + '"]:checked');
        if (selected.length === 0) {
            alert('Please select at least one expense.');
            return;
        }

        const ids = Array.from(selected).map(function(cb) { return cb.value; });

        document.getElementById('modal_customer_id').value = customerId;
        document.getElementById('modal_customer_name').value = customerName;
        document.getElementById('modal_count').textContent = ids.length;

        const container = document.getElementById('selected_expenses_container');
        container.innerHTML = '';
        ids.forEach(function(id) {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'expense_ids[]';
            input.value = id;
            container.appendChild(input);
        });

        // Fetch preview
        fetch('{{ route("finance.expenses.preview-invoice") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'Accept': 'application/json'
            },
            body: JSON.stringify({ expense_ids: ids })
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            document.getElementById('modal_total').textContent = data.total_billable ? data.total_billable.toFixed(2) : '0.00';
        });

        new bootstrap.Modal(document.getElementById('invoiceModal')).show();
    });
});
</script>
@endpush

@extends('layouts.standalone')

@section('title', 'Expense ' . $expense->expense_number . ' — AccumenAI')
@section('page_title', 'Finance')

@section('content')

<div class="standalone-heading">
    <h4>Expense {{ $expense->expense_number }}</h4>
    <div class="d-flex gap-2">
        @if (!$expense->isBilled())
            <a href="{{ route('finance.expenses.edit', $expense) }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-pencil me-1"></i>Edit</a>
        @endif
    </div>
</div>

<div class="row g-4">
    <div class="col-md-8">
        <div class="admin-card">
            <h6>Expense Details</h6>
            <table class="table table-sm mb-0">
                <tr><th width="160">Expense #</th><td>{{ $expense->expense_number }}</td></tr>
                <tr><th>Date</th><td>{{ $expense->expense_date?->format('d M Y') }}</td></tr>
                <tr><th>Vendor</th><td>{{ $expense->vendor_name ?? '—' }}</td></tr>
                <tr><th>Category</th><td>{{ $expense->categoryLabel() }}</td></tr>
                <tr><th>Description</th><td>{{ $expense->description ?? '—' }}</td></tr>
                <tr><th>Reference #</th><td>{{ $expense->reference_number ?? '—' }}</td></tr>
                <tr><th>Notes</th><td>{{ $expense->notes ?? '—' }}</td></tr>
            </table>
        </div>

        <div class="admin-card mt-3">
            <h6>Financial</h6>
            <table class="table table-sm mb-0">
                <tr><th width="160">Amount</th><td>{{ number_format($expense->amount, 2) }} {{ $expense->currency }}</td></tr>
                <tr><th>Tax Amount</th><td>{{ number_format($expense->tax_amount, 2) }}</td></tr>
                <tr><th>Payment Account</th><td>{{ $expense->paymentAccount?->code }} — {{ $expense->paymentAccount?->name }}</td></tr>
                <tr><th>Expense Account</th><td>{{ $expense->expenseAccount?->code }} — {{ $expense->expenseAccount?->name }}</td></tr>
                @if ($expense->journalEntry)
                    <tr><th>Journal Entry</th><td><a href="{{ route('finance.journals.show', $expense->journalEntry->journal_id ?? $expense->journal_entry_id) }}">{{ $expense->journalEntry->journal->journal_no ?? '#' }}</a></td></tr>
                @endif
            </table>
        </div>
    </div>

    <div class="col-md-4">
        <div class="admin-card">
            <h6>Billing</h6>
            <table class="table table-sm mb-0">
                <tr><th>Status</th><td><span class="badge bg-{{ $expense->billingStatusColor() }}">{{ ucfirst($expense->billing_status) }}</span></td></tr>
                <tr><th>Billable</th><td>{{ $expense->is_billable ? 'Yes' : 'No' }}</td></tr>
                @if ($expense->is_billable)
                    <tr><th>Customer</th><td>{{ $expense->customer?->name ?? '—' }}</td></tr>
                    <tr><th>Markup %</th><td>{{ $expense->markup_percentage }}%</td></tr>
                    <tr><th>Billable Amount</th><td>{{ number_format($expense->billable_amount ?? $expense->computeBillableAmount(), 2) }}</td></tr>
                @endif
                @if ($expense->billed_invoice_id)
                    <tr><th>Invoice</th><td><a href="{{ route('finance.invoices.show', $expense->billed_invoice_id) }}">{{ $expense->billedInvoice?->invoice_number }}</a></td></tr>
                    <tr><th>Billed At</th><td>{{ $expense->billed_at?->format('d M Y H:i') }}</td></tr>
                @endif
            </table>
        </div>
    </div>
</div>

@endsection

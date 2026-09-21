@extends('layouts.standalone')

@section('title', 'Expenses — AccumenAI')
@section('page_title', 'Finance')

@section('content')

<div class="standalone-heading">
    <h4>Expenses</h4>
    <p>Track and manage business expenses with billable options.</p>
    <div class="d-flex gap-2">
        <a href="{{ route('finance.expenses.billable-dashboard') }}" class="btn btn-outline-warning btn-sm"><i class="bi bi-cash-coin me-1"></i>Billable Dashboard</a>
        <a href="{{ route('finance.expenses.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>New Expense</a>
    </div>
</div>

<div class="admin-card mb-3">
    <form method="GET" class="row g-2 align-items-end">
        <div class="col-md-3">
            <input type="text" class="form-control form-control-sm" name="q" value="{{ request('q') }}" placeholder="Search expense # or vendor...">
        </div>
        <div class="col-md-2">
            <select class="form-select form-select-sm" name="category">
                <option value="">All categories</option>
                @foreach ($categories as $key => $label)
                    <option value="{{ $key }}" @selected(request('category') === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2">
            <select class="form-select form-select-sm" name="billing_status">
                <option value="">All statuses</option>
                @foreach (['unbillable', 'unbilled', 'billed', 'paid'] as $s)
                    <option value="{{ $s }}" @selected(request('billing_status') === $s)>{{ ucfirst($s) }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2">
            <button class="btn btn-outline-primary btn-sm" type="submit"><i class="bi bi-search me-1"></i>Filter</button>
            @if (request('q') || request('category') || request('billing_status') || request('only_billable'))
                <a href="{{ route('finance.expenses.index') }}" class="btn btn-outline-secondary btn-sm">Clear</a>
            @endif
        </div>
    </form>
</div>

<div class="admin-card">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>Expense #</th>
                    <th>Date</th>
                    <th>Vendor</th>
                    <th>Category</th>
                    <th class="text-end">Amount</th>
                    <th>Status</th>
                    <th>Billable</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($expenses as $expense)
                    <tr>
                        <td><a href="{{ route('finance.expenses.show', $expense) }}">{{ $expense->expense_number }}</a></td>
                        <td>{{ $expense->expense_date?->format('d M Y') }}</td>
                        <td>{{ $expense->vendor_name ?? '—' }}</td>
                        <td><span class="badge bg-light text-dark">{{ $expense->categoryLabel() }}</span></td>
                        <td class="text-end">{{ number_format($expense->amount, 2) }}</td>
                        <td><span class="badge bg-{{ $expense->billingStatusColor() }}">{{ ucfirst($expense->billing_status) }}</span></td>
                        <td>
                            @if ($expense->is_billable)
                                <i class="bi bi-check-circle-fill text-success"></i>
                                @if ($expense->billed_invoice_id)
                                    <small class="text-muted">→ INV</small>
                                @endif
                            @else
                                <i class="bi bi-circle text-muted"></i>
                            @endif
                        </td>
                        <td class="text-end">
                            <a href="{{ route('finance.expenses.edit', $expense) }}" class="btn btn-outline-secondary btn-sm" @if($expense->isBilled()) disabled @endif><i class="bi bi-pencil"></i></a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-center text-muted py-4">No expenses found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="p-3">
        {{ $expenses->links() }}
    </div>
</div>

@endsection

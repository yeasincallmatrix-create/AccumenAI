@extends('layouts.standalone')

@section('title', 'Finance — AccumenAI')
@section('page_title', 'Finance & Accounting')

@section('content')

<div class="standalone-heading">
    <h4>Finance Dashboard</h4>
    <p>Double-entry accounting for {{ $institute->name }}. Chart of accounts, journals, invoices, payments and financial reports — everything is derived from posted journal entries.
        @if ($branch)
            <span class="badge text-bg-primary ms-1">Branch: {{ $branch->name }}</span>
        @else
            <span class="badge text-bg-light border ms-1">All branches</span>
        @endif
    </p>
    <div class="d-flex gap-2 flex-wrap">
        <a href="{{ route('finance.journals.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-journal-plus me-1"></i>New Journal</a>
        <a href="{{ route('finance.invoices.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-receipt me-1"></i>New Invoice</a>
        <a href="{{ route('finance.chart-of-accounts.index') }}" class="btn btn-outline-primary btn-sm"><i class="bi bi-list-columns-reverse me-1"></i>Chart of Accounts</a>
        <a href="{{ route('finance.parties.index') }}" class="btn btn-outline-primary btn-sm"><i class="bi bi-people me-1"></i>Parties</a>
        <a href="{{ route('finance.reports.trial-balance') }}" class="btn btn-outline-primary btn-sm"><i class="bi bi-table me-1"></i>Trial Balance</a>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-6 col-md-2">
        <div class="admin-card h-100">
            <div class="small text-muted">Cash</div>
            <div class="fs-5 fw-semibold">{{ number_format($cashTotal, 2) }}</div>
            <div class="small text-muted">{{ $baseCurrency }}</div>
        </div>
    </div>
    <div class="col-6 col-md-2">
        <div class="admin-card h-100">
            <div class="small text-muted">Bank</div>
            <div class="fs-5 fw-semibold">{{ number_format($bankTotal, 2) }}</div>
            <div class="small text-muted">{{ $baseCurrency }}</div>
        </div>
    </div>
    <div class="col-6 col-md-2">
        <div class="admin-card h-100">
            <div class="small text-muted">Receivables</div>
            <div class="fs-5 fw-semibold">{{ number_format($arpTotals['receivable'], 2) }}</div>
            <div class="small text-muted">{{ $baseCurrency }}</div>
        </div>
    </div>
    <div class="col-6 col-md-2">
        <div class="admin-card h-100">
            <div class="small text-muted">Payables</div>
            <div class="fs-5 fw-semibold text-danger">{{ number_format($arpTotals['payable'], 2) }}</div>
            <div class="small text-muted">{{ $baseCurrency }}</div>
        </div>
    </div>
    <div class="col-6 col-md-2">
        <div class="admin-card h-100">
            <div class="small text-muted">Net income (YTD)</div>
            <div class="fs-5 fw-semibold {{ $netIncome < 0 ? 'text-danger' : '' }}">{{ number_format($netIncome, 2) }}</div>
            <div class="small text-muted">{{ $baseCurrency }}</div>
        </div>
    </div>
    <div class="col-6 col-md-2">
        <div class="admin-card h-100">
            <div class="small text-muted">Current period</div>
            <div class="fs-6 fw-semibold">{{ $currentFiscalYear?->name ?? '—' }}</div>
            <div class="small text-muted">{{ $currentPeriod?->name ?? 'No open period' }}</div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-5">
        <div class="admin-card mb-3">
            <h6 class="card-title">Cash & bank balances</h6>
            @forelse ($cashBank as $account)
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <span>{{ $account->code }} — {{ $account->name }}</span>
                    <span class="fw-semibold">{{ number_format($account->balance, 2) }}</span>
                </div>
            @empty
                <p class="text-muted mb-0">No cash/bank accounts yet.</p>
            @endforelse
        </div>
        <div class="admin-card">
            <h6 class="card-title">Income vs expense (YTD)</h6>
            <div class="d-flex justify-content-between align-items-center mb-1">
                <span>Income</span>
                <span class="fw-semibold text-success">{{ number_format($incomeStatement['total_income'], 2) }}</span>
            </div>
            <div class="d-flex justify-content-between align-items-center mb-1">
                <span>Expenses</span>
                <span class="fw-semibold text-danger">{{ number_format($incomeStatement['total_expense'], 2) }}</span>
            </div>
            <hr class="my-1">
            <div class="d-flex justify-content-between align-items-center">
                <span class="fw-semibold">Net</span>
                <span class="fw-semibold">{{ number_format($netIncome, 2) }}</span>
            </div>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="admin-card mb-3">
            <h6 class="card-title">Recent journals</h6>
            @forelse ($recentJournals as $journal)
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <span class="text-truncate">
                        <a href="{{ route('finance.journals.show', $journal) }}" class="text-decoration-none">{{ $journal->journal_no }}</a>
                        <span class="badge text-bg-light border ms-1">{{ $journal->type }}</span>
                        <span class="badge text-bg-{{ $journal->status === 'posted' ? 'success' : ($journal->status === 'draft' ? 'warning' : 'secondary') }} ms-1">{{ $journal->status }}</span>
                        <span class="small text-muted ms-1">{{ $journal->journal_date }}</span>
                    </span>
                    <span class="small text-muted ms-2">{{ $journal->description }}</span>
                </div>
            @empty
                <p class="text-muted mb-0">No journals yet.</p>
            @endforelse
        </div>
        <div class="admin-card">
            <h6 class="card-title">Recent transactions</h6>
            @forelse ($recentPayments as $payment)
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <span class="text-truncate">
                        <a href="{{ route('finance.invoices.show', $payment->invoice) }}" class="text-decoration-none">#{{ $payment->invoice?->invoice_number ?? $payment->invoice_id }}</a>
                        <span class="badge text-bg-light border ms-1">{{ $payment->payment_method }}</span>
                        <span class="small text-muted ms-1">{{ $payment->paid_at?->format('Y-m-d') }}</span>
                    </span>
                    <span class="fw-semibold">{{ number_format($payment->amount, 2) }} {{ $baseCurrency }}</span>
                </div>
            @empty
                <p class="text-muted mb-0">No payments recorded yet.</p>
            @endforelse
        </div>
    </div>
</div>

@endsection
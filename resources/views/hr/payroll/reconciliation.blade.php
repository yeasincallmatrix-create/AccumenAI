@extends('layouts.institute')
@section('title','Payroll Reconciliation — HR')
@section('content')
<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div class="page-header-text">
        <h4 class="page-header-title">Payroll ↔ Finance Reconciliation <span class="badge bg-success ms-2" style="font-size:.7rem"><i class="bi bi-circle-fill me-1" style="font-size:.45rem"></i>Active</span></h4>
        <p class="page-header-desc mb-0">Payroll total vs journal total, salary payable, paid, outstanding, finance status. Institute/branch isolated.</p>
    </div>
</div>
@include('hr._payroll_tabs')

<div class="filter-card mb-3">
    <form method="GET" action="{{ route('hr.payroll.reconciliation') }}" class="d-flex flex-wrap gap-2">
        <select name="branch_id" class="form-select form-select-sm" style="width:200px"><option value="">All branches</option>@foreach($branches as $b)<option value="{{ $b->id }}" @selected((string)request('branch_id') === (string)$b->id)>{{ $b->name }}</option>@endforeach</select>
        <select name="period_id" class="form-select form-select-sm" style="width:200px"><option value="">All periods</option>@foreach($periods as $p)<option value="{{ $p->id }}" @selected((string)request('period_id') === (string)$p->id)>{{ $p->name }}</option>@endforeach</select>
        <button class="btn btn-sm btn-primary">Filter</button>
    </form>
</div>
<div class="row g-3 mb-4">
    <div class="col-md-3"><div class="admin-card p-3 text-center"><div class="h5 mb-0">{{ number_format($data['payroll_total'],2) }}</div><div class="text-muted small">Payroll Total (Net)</div></div></div>
    <div class="col-md-3"><div class="admin-card p-3 text-center"><div class="h5 mb-0">{{ number_format($data['journal_total'],2) }}</div><div class="text-muted small">Journal Total (Debit)</div></div></div>
    <div class="col-md-3"><div class="admin-card p-3 text-center"><div class="h5 mb-0">{{ number_format($data['salary_payable'],2) }}</div><div class="text-muted small">Salary Payable</div></div></div>
    <div class="col-md-3"><div class="admin-card p-3 text-center"><div class="h5 mb-0 text-success">{{ number_format($data['paid_amount'],2) }}</div><div class="text-muted small">Paid Amount</div></div></div>
</div>
<div class="row g-3 mb-4">
    <div class="col-md-4"><div class="admin-card p-3 text-center"><div class="h5 mb-0 text-warning">{{ number_format($data['outstanding_salary'],2) }}</div><div class="text-muted small">Outstanding Salary</div></div></div>
    <div class="col-md-4"><div class="admin-card p-3 text-center"><div class="h5 mb-0">{{ $data['payable_ledger_balance'] !== null ? number_format($data['payable_ledger_balance'],2) : '—' }}</div><div class="text-muted small">Payable Ledger Balance (Finance)</div></div></div>
    <div class="col-md-4"><div class="admin-card p-3 text-center"><div class="h6 mb-0"><span class="badge {{ $data['finance_reconciliation_status'] === 'matched' ? 'text-bg-success' : 'text-bg-danger' }}">{{ $data['finance_reconciliation_status'] }}</span></div><div class="text-muted small">Finance Reconciliation Status</div></div></div>
</div>
<div class="admin-card p-3">
    <h6>Details</h6>
    <ul class="small text-muted mb-0">
        <li>Gross Total: {{ number_format($data['gross_total'],2) }}, Deductions: {{ number_format($data['deduction_total'],2) }}</li>
        <li>All payrolls are branch/institute isolated; journals linked via ref_type payroll.</li>
        <li>Historical journals preserved on reversal (reversal_of).</li>
        <li>Closed period rejection enforced via AccountingPeriod open check.</li>
    </ul>
</div>
@endsection

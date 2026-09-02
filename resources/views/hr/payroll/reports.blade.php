@extends('layouts.institute')
@section('title','Payroll Reports — HR')
@section('content')
<div class="standalone-heading">
    <h4>Payroll Reports</h4>
    <a href="{{ route('hr.payroll.periods.index') }}" class="btn btn-outline-secondary btn-sm">Periods</a>
</div>
<div class="row g-3 mb-4">
    <div class="col-md-3"><div class="admin-card p-3 text-center"><div class="h5 mb-0">{{ $stats['total_payrolls'] }}</div><div class="text-muted small">Total Payslips</div></div></div>
    <div class="col-md-3"><div class="admin-card p-3 text-center"><div class="h5 mb-0">{{ number_format($stats['total_gross'],2) }}</div><div class="text-muted small">Gross</div></div></div>
    <div class="col-md-3"><div class="admin-card p-3 text-center"><div class="h5 mb-0 text-danger">{{ number_format($stats['total_deductions'],2) }}</div><div class="text-muted small">Deductions</div></div></div>
    <div class="col-md-3"><div class="admin-card p-3 text-center"><div class="h5 mb-0 text-success">{{ number_format($stats['total_net'],2) }}</div><div class="text-muted small">Net / Expense</div></div></div>
</div>
<div class="row g-3 mb-4">
    <div class="col-md-6"><div class="admin-card p-3 text-center"><div class="h6 mb-0">{{ number_format($stats['unpaid'],2) }}</div><div class="text-muted small">Unpaid Salary</div></div></div>
    <div class="col-md-6"><div class="admin-card p-3"><h6>By Branch</h6>@foreach($stats['by_branch'] as $bid=>$total)<span class="badge bg-light text-dark border">Branch #{{ $bid }}: {{ number_format($total,2) }}</span> @endforeach</div></div>
</div>
<h6>Recent Payslips</h6>
<div class="admin-card">
    <table class="table table-sm mb-0">
        <thead><tr><th>Payslip</th><th>Employee</th><th>Period</th><th>Net</th><th>Status</th></tr></thead>
        <tbody>
            @foreach($recent as $p)
                <tr><td><code>{{ $p->payslip_no }}</code></td><td>{{ $p->employee->display_name }}</td><td>{{ $p->period->name }}</td><td>{{ number_format($p->net_salary,2) }}</td><td><span class="badge text-bg-secondary">{{ $p->status }}</span></td></tr>
            @endforeach
        </tbody>
    </table>
</div>
@endsection

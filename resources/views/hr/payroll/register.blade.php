@extends('layouts.institute')
@section('title','Payroll Register — HR')
@section('content')
<div class="standalone-heading">
    <h4>Payroll Register</h4>
    <a href="{{ route('hr.payroll.reports') }}" class="btn btn-outline-primary btn-sm">Reports</a>
</div>
<div class="admin-card p-3 mb-3">
    <form method="GET" class="row g-2">
        <div class="col-md-4"><select name="period_id" class="form-select form-select-sm"><option value="">All Periods</option>@foreach($periods as $p)<option value="{{ $p->id }}" @selected(request('period_id')==$p->id)>{{ $p->name }}</option>@endforeach</select></div>
        <div class="col-md-3"><select name="status" class="form-select form-select-sm"><option value="">All Status</option><option value="draft" @selected(request('status')=='draft')>Draft</option><option value="approved" @selected(request('status')=='approved')>Approved</option><option value="paid" @selected(request('status')=='paid')>Paid</option></select></div>
        <div class="col-md-2"><button type="submit" class="btn btn-primary btn-sm w-100">Filter</button></div>
    </form>
</div>
<div class="admin-card">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead><tr><th>Payslip</th><th>Employee</th><th>Period</th><th>Gross</th><th>Deductions</th><th>Net</th><th>Status</th></tr></thead>
            <tbody>
                @foreach($payrolls as $p)
                    <tr>
                        <td><code>{{ $p->payslip_no }}</code></td>
                        <td>{{ $p->employee->display_name }}</td>
                        <td>{{ $p->period->name }}</td>
                        <td>{{ number_format($p->gross_earnings,2) }}</td>
                        <td>{{ number_format($p->total_deductions,2) }}</td>
                        <td class="fw-bold">{{ number_format($p->net_salary,2) }}</td>
                        <td><span class="badge text-bg-secondary">{{ $p->status }}</span></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="p-2">{{ $payrolls->links() }}</div>
</div>
@endsection

@extends('layouts.institute')
@section('title','Payroll Period — HR')
@section('content')
<div class="standalone-heading">
    <h4>{{ $period->name }} <small class="text-muted">{{ $period->start_date->format('Y-m-d') }} → {{ $period->end_date->format('Y-m-d') }}</small></h4>
    <span class="badge text-bg-{{ $period->status==='paid'?'success':($period->status==='approved'?'primary':'secondary') }}">{{ ucfirst($period->status) }}</span>
    <a href="{{ route('hr.payroll.periods.index') }}" class="btn btn-outline-secondary btn-sm">Back</a>
</div>
@if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
<div class="admin-card p-3 mb-3">
    <div class="d-flex flex-wrap gap-2">
        @if($period->status==='draft' || $period->status==='processing')
            <form method="POST" action="{{ route('hr.payroll.periods.generate',$period) }}">@csrf<button type="submit" class="btn btn-primary btn-sm">Generate</button></form>
        @endif
        @if($canApprove && in_array($period->status,['processing','draft']))
            <form method="POST" action="{{ route('hr.payroll.periods.approve',$period) }}">@csrf<button type="submit" class="btn btn-success btn-sm">Approve & Post Journals</button></form>
        @endif
        @if($canPay && $period->status==='approved')
            <form method="POST" action="{{ route('hr.payroll.periods.pay',$period) }}">@csrf<button type="submit" class="btn btn-warning btn-sm">Mark Paid</button></form>
        @endif
        @if(!in_array($period->status,['paid','cancelled','void']))
            <form method="POST" action="{{ route('hr.payroll.periods.cancel',$period) }}">@csrf<button type="submit" class="btn btn-outline-danger btn-sm" onclick="return confirm('Cancel?')">Cancel</button></form>
        @endif
    </div>
    <div class="mt-2 small text-muted">Total: {{ $period->total_employees }} employees, Gross {{ number_format($period->total_gross,2) }}, Net {{ number_format($period->total_net,2) }}</div>
</div>
<div class="admin-card">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead><tr><th>Payslip</th><th>Employee</th><th>Gross</th><th>Deductions</th><th>Net</th><th>Status</th><th></th></tr></thead>
            <tbody>
                @foreach($payrolls as $pay)
                    <tr>
                        <td><code>{{ $pay->payslip_no }}</code></td>
                        <td>{{ $pay->employee->display_name }}</td>
                        <td>{{ number_format($pay->gross_earnings,2) }}</td>
                        <td>{{ number_format($pay->total_deductions,2) }}</td>
                        <td class="fw-bold">{{ number_format($pay->net_salary,2) }}</td>
                        <td><span class="badge text-bg-{{ $pay->status==='paid'?'success':($pay->status==='approved'?'primary':'secondary') }}">{{ $pay->status }}</span></td>
                        <td><a href="{{ route('hr.payroll.payslip',$pay) }}" class="btn btn-sm btn-outline-primary">Payslip</a></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="p-2">{{ $payrolls->links() }}</div>
</div>
@endsection

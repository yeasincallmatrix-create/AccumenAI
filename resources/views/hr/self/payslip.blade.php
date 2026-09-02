@extends('layouts.institute')
@section('title','Payslip — HR')
@section('content')
<div class="standalone-heading"><h4>Payslip {{ $hrPayroll->payslip_no }}</h4><a href="{{ route('hr.self.payslips') }}" class="btn btn-outline-secondary btn-sm">Back</a><button onclick="window.print()" class="btn btn-primary btn-sm">Print</button></div>
<div class="admin-card p-4">
    <div class="row"><div class="col-6"><strong>{{ $hrPayroll->period?->name }}</strong><br>{{ $hrPayroll->period?->start_date?->format('Y-m-d') }} → {{ $hrPayroll->period?->end_date?->format('Y-m-d') }}</div><div class="col-6 text-end"><strong>{{ $employee->display_name }}</strong><br><code>{{ $employee->employee_code }}</code></div></div>
    <hr>
    <div class="row"><div class="col-6"><h6>Earnings</h6>@foreach($hrPayroll->earnings_snapshot ?? [] as $e)<div class="d-flex justify-content-between small"><span>{{ $e['name'] }}</span><span>{{ number_format($e['amount'],2) }}</span></div>@endforeach<hr><div class="d-flex justify-content-between fw-bold"><span>Gross</span><span>{{ number_format($hrPayroll->gross_earnings,2) }}</span></div></div><div class="col-6"><h6>Deductions</h6>@foreach($hrPayroll->deductions_snapshot ?? [] as $d)<div class="d-flex justify-content-between small"><span>{{ $d['name'] }}</span><span>{{ number_format($d['amount'],2) }}</span></div>@endforeach<hr><div class="d-flex justify-content-between fw-bold"><span>Total</span><span>{{ number_format($hrPayroll->total_deductions,2) }}</span></div></div></div>
    <hr><div class="d-flex justify-content-between"><h5>Net Salary</h5><h5>{{ number_format($hrPayroll->net_salary,2) }}</h5></div>
    <div class="small text-muted mt-2">Status: {{ ucfirst($hrPayroll->status) }} — Generated {{ $hrPayroll->created_at->format('Y-m-d') }}</div>
</div>
@endsection

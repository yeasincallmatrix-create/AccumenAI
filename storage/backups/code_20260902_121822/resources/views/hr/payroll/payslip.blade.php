@extends('layouts.institute')
@section('title','Payslip '.$payroll->payslip_no.' — HR')
@section('content')
<div class="standalone-heading">
    <h4>Payslip <code>{{ $payroll->payslip_no }}</code></h4>
    <p class="text-muted small">{{ $institute->name }} — {{ $period->name }} ({{ $period->start_date->format('Y-m-d') }} → {{ $period->end_date->format('Y-m-d') }}) — {{ ucfirst($payroll->status) }}</p>
    <a href="{{ route('hr.payroll.periods.show',$period) }}" class="btn btn-outline-secondary btn-sm">Back</a>
    <button onclick="window.print()" class="btn btn-primary btn-sm">Print</button>
</div>
<div class="admin-card p-4" id="payslip-print">
    <div class="row mb-3">
        <div class="col-md-6">
            <h6>Employer</h6>
            <strong>{{ $institute->name }}</strong><br>
            <span class="text-muted small">{{ $institute->country ?? '' }}</span>
        </div>
        <div class="col-md-6 text-end">
            <h6>Employee</h6>
            <strong>{{ $employee->display_name }}</strong> <code>{{ $employee->employee_code }}</code><br>
            <span class="text-muted small">{{ $employee->department?->name ?? '—' }} — {{ $employee->designation?->name ?? '—' }}</span>
        </div>
    </div>
    <hr>
    <div class="row">
        <div class="col-md-6">
            <h6>Earnings</h6>
            <table class="table table-sm">
                @foreach($payroll->earnings_snapshot ?? [] as $e)
                    <tr><td>{{ $e['name'] }}</td><td class="text-end">{{ number_format($e['amount'],2) }}</td></tr>
                @endforeach
                <tr class="fw-bold border-top"><td>Gross Earnings</td><td class="text-end">{{ number_format($payroll->gross_earnings,2) }}</td></tr>
            </table>
        </div>
        <div class="col-md-6">
            <h6>Deductions</h6>
            <table class="table table-sm">
                @foreach($payroll->deductions_snapshot ?? [] as $d)
                    <tr><td>{{ $d['name'] }}</td><td class="text-end">{{ number_format($d['amount'],2) }}</td></tr>
                @endforeach
                <tr class="fw-bold border-top"><td>Total Deductions</td><td class="text-end">{{ number_format($payroll->total_deductions,2) }}</td></tr>
            </table>
        </div>
    </div>
    <hr>
    <div class="d-flex justify-content-between">
        <h5>Net Salary</h5>
        <h5>{{ number_format($payroll->net_salary,2) }} @if($payroll->currency) {{ $payroll->currency->code }} @endif</h5>
    </div>
    <div class="small text-muted mt-2">
        Working days: {{ $payroll->working_days }}, Present: {{ $payroll->present_days }}, Unpaid leave: {{ $payroll->unpaid_leave_days }}, Overtime: {{ $payroll->overtime_minutes }} mins ({{ number_format($payroll->overtime_amount,2) }})
    </div>
    <div class="small text-muted">Payslip {{ $payroll->payslip_no }} — Generated {{ $payroll->created_at->format('Y-m-d H:i') }} — Status: {{ ucfirst($payroll->status) }}</div>
</div>
@endsection

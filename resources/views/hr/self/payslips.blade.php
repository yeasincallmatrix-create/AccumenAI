@extends('layouts.institute')
@section('title','My Payslips — HR')
@section('content')
<div class="standalone-heading"><h4>My Payslips</h4><a href="{{ route('hr.self.dashboard') }}" class="btn btn-outline-secondary btn-sm">Dashboard</a></div>
<div class="admin-card">
    <table class="table table-sm mb-0">
        <thead><tr><th>Payslip</th><th>Period</th><th>Gross</th><th>Deductions</th><th>Net</th><th>Status</th><th></th></tr></thead>
        <tbody>
            @foreach($payslips as $p)<tr><td><code>{{ $p->payslip_no }}</code></td><td>{{ $p->period?->name ?? '—' }}</td><td>{{ number_format($p->gross_earnings,2) }}</td><td>{{ number_format($p->total_deductions,2) }}</td><td class="fw-bold">{{ number_format($p->net_salary,2) }}</td><td><span class="badge text-bg-secondary">{{ $p->status }}</span></td><td><a href="{{ route('hr.self.payslip.show',$p) }}" class="btn btn-sm btn-outline-primary">View</a></td></tr>@endforeach
        </tbody>
    </table>
</div>
@endsection

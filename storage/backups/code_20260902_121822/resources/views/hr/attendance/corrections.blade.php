@extends('layouts.institute')

@section('title', 'Corrections — HR')

@section('content')
<div class="standalone-heading"><h4>Attendance Corrections</h4><p>Employee/manager requests correction, HR approves/rejects. Original record preserved, correction history kept.</p></div>

<div class="admin-card">
    <div class="table-responsive">
        <table class="table small mb-0">
            <thead><tr><th>Date</th><th>Employee</th><th>Requested</th><th>Reason</th><th>Status</th><th>Action</th></tr></thead>
            <tbody>
                @forelse($corrections as $c)
                    <tr>
                        <td>{{ $c->correction_date->format('Y-m-d') }}</td>
                        <td>{{ $c->employee->display_name }} <small class="text-muted">{{ $c->employee->employee_code }}</small></td>
                        <td>{{ $c->requested_status }} @if($c->requested_check_in) {{ $c->requested_check_in }} @endif</td>
                        <td>{{ $c->reason }}</td>
                        <td><span class="badge {{ $c->status==='pending'?'text-bg-warning':($c->status==='approved'?'text-bg-success':'text-bg-danger') }}">{{ $c->status }}</span></td>
                        <td>
                            @if($c->status==='pending')
                                <form method="POST" action="{{ route('hr.attendance.corrections.decide', $c) }}" class="d-inline">@csrf<button name="decision" value="approved" class="btn btn-sm btn-success">Approve</button></form>
                                <form method="POST" action="{{ route('hr.attendance.corrections.decide', $c) }}" class="d-inline">@csrf<button name="decision" value="rejected" class="btn btn-sm btn-outline-danger">Reject</button></form>
                            @else
                                <span class="text-muted small">{{ $c->reviewed_at?->format('Y-m-d H:i') }}</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted py-3">No corrections.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($corrections->hasPages())<div class="p-2 border-top">{{ $corrections->links() }}</div>@endif
</div>
@endsection

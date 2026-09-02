@extends('layouts.institute')

@section('title', 'Leave Balances — HR')

@section('content')
<div class="standalone-heading"><h4>Leave Balances</h4><p>Allocated, used, pending, remaining per employee/type/year.</p></div>

<div class="filter-card mb-3">
    <form method="GET" action="{{ route('hr.leave.balances') }}" class="d-flex flex-wrap gap-2">
        <select name="employee_id" class="form-select form-select-sm" style="width:200px"><option value="">All employees</option>@foreach($employees as $e)<option value="{{ $e->id }}" @selected((string)(request('employee_id'))===(string)$e->id)>{{ $e->display_name }}</option>@endforeach</select>
        <input type="number" name="year" value="{{ request('year', date('Y')) }}" class="form-control form-control-sm" style="width:120px">
        <button type="submit" class="btn btn-sm btn-primary">Filter</button>
    </form>
</div>

<div class="admin-card">
    <div class="table-responsive">
        <table class="table small mb-0">
            <thead><tr><th>Employee</th><th>Type</th><th>Year</th><th>Allocated</th><th>Used</th><th>Pending</th><th>Remaining</th></tr></thead>
            <tbody>
                @forelse($balances as $b)
                    <tr>
                        <td>{{ $b->employee->display_name }} <small class="text-muted">{{ $b->employee->employee_code }}</small></td>
                        <td>{{ $b->leaveType->name }}</td>
                        <td>{{ $b->year }}</td>
                        <td>{{ $b->allocated }}</td>
                        <td>{{ $b->used }}</td>
                        <td>{{ $b->pending }}</td>
                        <td><strong>{{ number_format($b->remaining(),1) }}</strong></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted py-3">No balances yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($balances->hasPages())<div class="p-2 border-top">{{ $balances->links() }}</div>@endif
</div>
@endsection

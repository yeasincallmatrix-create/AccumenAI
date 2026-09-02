@extends('layouts.institute')
@section('title','Reviews — HR')
@section('content')
<div class="standalone-heading"><h4>Performance Reviews</h4><p>Employee, reviewer, period, scores, status.</p><a href="{{ route('hr.performance.reviews.create') }}" class="btn btn-primary btn-sm">New Review</a></div>
<div class="filter-card mb-3">
    <form method="GET" action="{{ route('hr.performance.reviews') }}" class="d-flex flex-wrap gap-2">
        <select name="period_id" class="form-select form-select-sm"><option value="">All periods</option>@foreach($periods as $p)<option value="{{ $p->id }}" @selected((string)request('period_id')===(string)$p->id)>{{ $p->name }}</option>@endforeach</select>
        <select name="status" class="form-select form-select-sm"><option value="">All status</option>@foreach($statuses as $s)<option value="{{ $s }}" @selected(request('status')===$s)>{{ $s }}</option>@endforeach</select>
        <button class="btn btn-sm btn-primary">Filter</button>
    </form>
</div>
<div class="admin-card">
    <div class="table-responsive">
        <table class="table small mb-0">
            <thead><tr><th>Employee</th><th>Period</th><th>Score</th><th>Status</th><th>Reviewer</th><th></th></tr></thead>
            <tbody>
                @forelse($reviews as $r)
                    <tr>
                        <td>{{ $r->employee->display_name }}<div class="text-muted small">{{ $r->employee->employee_code }}</div></td>
                        <td>{{ $r->period->name }}</td>
                        <td>{{ $r->overall_score ?? '—' }}</td>
                        <td><span class="badge {{ $r->status==='approved'?'text-bg-success':($r->status==='rejected'?'text-bg-danger':'text-bg-warning') }}">{{ $r->status }}</span></td>
                        <td>{{ $r->reviewer?->display_name ?? '—' }}</td>
                        <td><a href="{{ route('hr.performance.reviews.show', $r) }}" class="btn btn-sm btn-outline-primary">View</a></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted py-3">No reviews.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($reviews->hasPages())<div class="p-2 border-top">{{ $reviews->links() }}</div>@endif
</div>
@endsection

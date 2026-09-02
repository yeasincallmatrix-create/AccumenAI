@extends('layouts.institute')
@section('title','Review — HR')
@section('content')
<div class="standalone-heading">
    <h4>Review: {{ $review->employee->display_name }} — {{ $review->period->name }}</h4>
    <p>Reviewer: {{ $review->reviewer?->display_name ?? '—' }} · Status: <span class="badge {{ $review->status==='approved'?'text-bg-success':'text-bg-warning' }}">{{ $review->status }}</span> · Score: {{ $review->overall_score ?? '—' }}</p>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-6"><div class="admin-card p-3"><h6>KPIs</h6>
        <table class="table small mb-0">
            <thead><tr><th>Name</th><th>Target</th><th>Weight</th><th>Score</th></tr></thead>
            <tbody>
                @forelse($review->kpis as $k)<tr><td>{{ $k->name }}</td><td>{{ $k->target ?? '—' }}</td><td>{{ $k->weight }}</td><td>{{ $k->score ?? '—' }} / {{ $k->max_score }}</td></tr>@empty<tr><td colspan="4" class="text-muted text-center">No KPIs.</td></tr>@endforelse
            </tbody>
        </table>
        <div class="mt-2">Overall: <strong>{{ $review->overall_score ?? '—' }}</strong> | Self: {{ $review->self_score ?? '—' }} | Manager: {{ $review->manager_score ?? '—' }} | HR: {{ $review->hr_score ?? '—' }}</div>
    </div></div>
    <div class="col-md-6"><div class="admin-card p-3"><h6>Actions & Recommendations</h6>
        <div class="small">Promotion: {{ $review->promotion_recommendation ?? '—' }}<br>Training: {{ $review->training_recommendation ?? '—' }}<br>Improvement: {{ $review->improvement_plan ?? '—' }}<br>Recognition: {{ $review->recognition ?? '—' }}</div>
        <hr>
        @if($canReview)
        <form method="POST" action="{{ route('hr.performance.reviews.evaluate', $review) }}" class="mb-2">
            @csrf
            <input type="hidden" name="role" value="manager">
            <div class="input-group input-group-sm"><input type="number" step="0.5" name="manager_score" class="form-control" placeholder="Manager score"><button class="btn btn-outline-primary">Save Manager Eval</button></div>
        </form>
        <form method="POST" action="{{ route('hr.performance.reviews.evaluate', $review) }}" class="mb-2">
            @csrf
            <input type="hidden" name="role" value="hr">
            <textarea name="hr_comments" class="form-control form-control-sm" placeholder="HR comments"></textarea>
            <input type="text" name="training_recommendation" class="form-control form-control-sm mt-1" placeholder="Training recommendation">
            <button class="btn btn-sm btn-outline-primary mt-1">Save HR Review</button>
        </form>
        @endif
        @if($canApprove)
        <form method="POST" action="{{ route('hr.performance.reviews.approve', $review) }}" class="d-inline">@csrf<button name="decision" value="approved" class="btn btn-sm btn-success">Approve</button></form>
        <form method="POST" action="{{ route('hr.performance.reviews.approve', $review) }}" class="d-inline">@csrf<button name="decision" value="rejected" class="btn btn-sm btn-outline-danger">Reject</button></form>
        @endif
    </div></div>
</div>

<div class="admin-card p-3">
    <h6>History / Comments</h6>
    <div class="small">Self: {{ $review->self_comments ?? '—' }}<br>Manager: {{ $review->manager_comments ?? '—' }}<br>HR: {{ $review->hr_comments ?? '—' }}<br>General: {{ $review->comments ?? '—' }}</div>
    <div class="text-muted small mt-2">Created {{ $review->created_at->diffForHumans() }}</div>
</div>
@endsection

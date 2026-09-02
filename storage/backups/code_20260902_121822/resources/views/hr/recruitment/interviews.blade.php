@extends('layouts.institute')
@section('title','Interviews — HR')
@section('content')
<div class="standalone-heading"><h4>Interviews</h4></div>
<div class="admin-card">
    <table class="table table-sm mb-0">
        <thead><tr><th>Candidate</th><th>Scheduled</th><th>Type</th><th>Interviewer</th><th>Score</th><th>Status</th></tr></thead>
        <tbody>
            @foreach($interviews as $iv)<tr><td>{{ $iv->application->candidateLead->first_name ?? '—' }}</td><td>{{ $iv->scheduled_at->format('Y-m-d H:i') }}</td><td>{{ $iv->interview_type }}</td><td>{{ $iv->interviewer?->email ?? '—' }}</td><td>{{ $iv->score ?? '—' }}</td><td><span class="badge text-bg-secondary">{{ $iv->status }}</span></td></tr>@endforeach
        </tbody>
    </table>
    <div class="p-2">{{ $interviews->links() }}</div>
</div>
@endsection

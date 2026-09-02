@extends('layouts.institute')
@section('title','Offers — HR')
@section('content')
<div class="standalone-heading"><h4>Offers</h4></div>
<div class="admin-card">
    <table class="table table-sm mb-0">
        <thead><tr><th>Candidate</th><th>Salary</th><th>Joining</th><th>Status</th></tr></thead>
        <tbody>
            @foreach($offers as $off)<tr><td>{{ $off->application->candidateLead->first_name ?? '—' }}</td><td>{{ $off->offered_salary }}</td><td>{{ $off->joining_date?->format('Y-m-d') ?? '—' }}</td><td><span class="badge text-bg-secondary">{{ $off->status }}</span></td></tr>@endforeach
        </tbody>
    </table>
    <div class="p-2">{{ $offers->links() }}</div>
</div>
@endsection

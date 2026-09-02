@extends('layouts.institute')
@section('title','Trainings — HR')
@section('content')
<div class="standalone-heading"><h4>Training Programs</h4><p>Title, provider, trainer, dates, location, capacity, cost, status.</p></div>
<div class="admin-card p-3 mb-3">
    <h6>New Training</h6>
    <form method="POST" action="{{ route('hr.training.programs.store') }}" class="row g-2">
        @csrf
        <div class="col-md-3"><input type="text" name="title" class="form-control form-control-sm" placeholder="Title *" required></div>
        <div class="col-md-2"><input type="text" name="provider" class="form-control form-control-sm" placeholder="Provider"></div>
        <div class="col-md-2"><input type="date" name="start_date" class="form-control form-control-sm" required></div>
        <div class="col-md-2"><input type="date" name="end_date" class="form-control form-control-sm" required></div>
        <div class="col-md-1"><input type="number" name="capacity" class="form-control form-control-sm" placeholder="Cap"></div>
        <div class="col-md-2"><button type="submit" class="btn btn-sm btn-primary">Create</button></div>
    </form>
</div>
<div class="admin-card">
    <div class="table-responsive">
        <table class="table small mb-0">
            <thead><tr><th>Title</th><th>Dates</th><th>Provider</th><th>Capacity</th><th>Cost</th><th>Status</th><th></th></tr></thead>
            <tbody>
                @forelse($trainings as $t)
                    <tr>
                        <td><a href="{{ route('hr.training.programs.show', $t) }}">{{ $t->title }}</a><div class="text-muted small">{{ $t->location ?? ($t->is_online ? 'Online' : '') }}</div></td>
                        <td>{{ $t->start_date->format('Y-m-d') }} → {{ $t->end_date->format('Y-m-d') }}</td>
                        <td>{{ $t->provider ?? '—' }}</td>
                        <td>{{ $t->enrolled_count }}/{{ $t->capacity ?? '∞' }}</td>
                        <td>{{ number_format($t->cost,0) }}</td>
                        <td><span class="badge text-bg-light border">{{ $t->status }}</span></td>
                        <td><a href="{{ route('hr.training.programs.show', $t) }}" class="btn btn-sm btn-outline-primary">View</a></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted py-3">No trainings.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($trainings->hasPages())<div class="p-2 border-top">{{ $trainings->links() }}</div>@endif
</div>
@endsection

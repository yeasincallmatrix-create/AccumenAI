@extends('layouts.institute')
@section('title','Review Periods — HR')
@section('content')
<div class="standalone-heading"><h4>Review Periods</h4><p>Create start/end dates, active/closed status. Industry-neutral.</p></div>
<div class="admin-card p-3 mb-3">
    <h6>Create Period</h6>
    <form method="POST" action="{{ route('hr.performance.periods.store') }}" class="row g-2">
        @csrf
        <div class="col-md-3"><input type="text" name="name" class="form-control form-control-sm" placeholder="Name *" required></div>
        <div class="col-md-2"><input type="date" name="start_date" class="form-control form-control-sm" required></div>
        <div class="col-md-2"><input type="date" name="end_date" class="form-control form-control-sm" required></div>
        <div class="col-md-2"><select name="branch_id" class="form-select form-select-sm"><option value="">All branches</option>@foreach($branches as $b)<option value="{{ $b->id }}">{{ $b->name }}</option>@endforeach</select></div>
        <div class="col-md-1"><button type="submit" class="btn btn-sm btn-primary">Create</button></div>
    </form>
</div>
<div class="admin-card">
    <div class="table-responsive">
        <table class="table small mb-0">
            <thead><tr><th>Name</th><th>Dates</th><th>Branch</th><th>Status</th><th></th></tr></thead>
            <tbody>
                @forelse($periods as $p)
                    <tr>
                        <td>{{ $p->name }}@if($p->code) <code>{{ $p->code }}</code> @endif<div class="text-muted small">{{ $p->description ?? '' }}</div></td>
                        <td>{{ $p->start_date->format('Y-m-d') }} → {{ $p->end_date->format('Y-m-d') }}</td>
                        <td>{{ $p->branch?->name ?? 'All' }}</td>
                        <td><span class="badge {{ $p->status==='active' ? 'text-bg-success' : ($p->status==='closed' ? 'text-bg-secondary' : 'text-bg-warning') }}">{{ $p->status }}</span></td>
                        <td>
                            @if($p->status !== 'closed')
                                <form method="POST" action="{{ route('hr.performance.periods.close', $p) }}">@csrf<button class="btn btn-sm btn-outline-secondary">Close</button></form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-muted py-3">No periods.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($periods->hasPages())<div class="p-2 border-top">{{ $periods->links() }}</div>@endif
</div>
@endsection

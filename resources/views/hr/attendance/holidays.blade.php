@extends('layouts.institute')

@section('title', 'Holidays — HR')

@section('content')
<div class="standalone-heading"><h4>Holidays / Non-working Days</h4><p>Organization/branch holidays. Reuses separate HR infrastructure, not Education calendar.</p></div>

<div class="admin-card p-3 mb-3">
    <h6>Add Holiday</h6>
    <form method="POST" action="{{ route('hr.attendance.holidays.store') }}" class="row g-2">
        @csrf
        <div class="col-md-3"><input type="text" name="name" class="form-control form-control-sm" placeholder="Name *" required></div>
        <div class="col-md-2"><input type="date" name="holiday_date" class="form-control form-control-sm" required></div>
        <div class="col-md-3"><select name="branch_id" class="form-select form-select-sm"><option value="">All branches</option>@foreach($branches as $b)<option value="{{ $b->id }}">{{ $b->name }}</option>@endforeach</select></div>
        <div class="col-md-2"><button type="submit" class="btn btn-sm btn-primary">Add</button></div>
    </form>
</div>

<div class="admin-card">
    <div class="table-responsive">
        <table class="table small mb-0">
            <thead><tr><th>Date</th><th>Name</th><th>Branch</th><th></th></tr></thead>
            <tbody>
                @forelse($holidays as $h)
                    <tr>
                        <td>{{ $h->holiday_date->format('Y-m-d') }}</td>
                        <td>{{ $h->name }}</td>
                        <td>{{ $h->branch?->name ?? 'All' }}</td>
                        <td><form method="POST" action="{{ route('hr.attendance.holidays.destroy', $h) }}" onsubmit="return confirm('Delete?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">Delete</button></form></td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-center text-muted py-3">No holidays.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($holidays->hasPages())<div class="p-2 border-top">{{ $holidays->links() }}</div>@endif
</div>
@endsection

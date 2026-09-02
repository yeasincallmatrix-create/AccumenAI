@extends('layouts.institute')
@section('title','My Leave — HR')
@section('content')
<div class="standalone-heading">
    <h4>My Leave</h4>
    <a href="{{ route('hr.self.dashboard') }}" class="btn btn-outline-secondary btn-sm">Dashboard</a>
</div>
@if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
<div class="row g-3">
    <div class="col-md-4">
        <div class="admin-card p-3">
            <h6>Balances</h6>
            @foreach($balances as $b)<div class="small d-flex justify-content-between"><span>{{ $b->leaveType?->name ?? 'Leave' }}</span><strong>{{ $b->remaining() }} / {{ $b->allocated }}</strong></div>@endforeach
        </div>
        <div class="admin-card p-3 mt-3">
            <h6>Apply Leave</h6>
            <form method="POST" action="{{ route('hr.self.leave.store') }}" enctype="multipart/form-data">
                @csrf
                <div class="mb-2"><label class="form-label small">Type *</label><select name="leave_type_id" class="form-select form-select-sm" required><option value="">Select</option>@foreach($types as $t)<option value="{{ $t->id }}">{{ $t->name }}</option>@endforeach</select></div>
                <div class="mb-2"><label class="form-label small">Start</label><input type="date" name="start_date" class="form-control form-control-sm" required></div>
                <div class="mb-2"><label class="form-label small">End</label><input type="date" name="end_date" class="form-control form-control-sm" required></div>
                <div class="mb-2"><label class="form-label small">Reason</label><textarea name="reason" class="form-control form-control-sm" rows="2"></textarea></div>
                <button type="submit" class="btn btn-primary btn-sm w-100">Apply</button>
            </form>
        </div>
    </div>
    <div class="col-md-8">
        <div class="admin-card p-3">
            <h6>History</h6>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead><tr><th>Type</th><th>Period</th><th>Days</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                        @foreach($history as $h)
                            <tr>
                                <td>{{ $h->leaveType?->name }}</td>
                                <td>{{ $h->start_date->format('Y-m-d') }} → {{ $h->end_date->format('Y-m-d') }}</td>
                                <td>{{ $h->days_count }}</td>
                                <td><span class="badge text-bg-secondary">{{ $h->status }}</span></td>
                                <td>@if($h->status==='pending')<form method="POST" action="{{ route('hr.self.leave.cancel',$h) }}">@csrf<button type="submit" class="btn btn-sm btn-outline-danger">Cancel</button></form>@endif</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="p-2">{{ $history->links() }}</div>
        </div>
    </div>
</div>
@endsection

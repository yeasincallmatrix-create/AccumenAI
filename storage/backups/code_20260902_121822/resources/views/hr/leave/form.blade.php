@extends('layouts.institute')

@section('title', 'Apply Leave — HR')

@section('content')
<div class="standalone-heading"><h4>Apply Leave</h4><p>Select type, dates, reason, attachment.</p></div>

<div class="admin-card p-3">
    <form method="POST" action="{{ route('hr.leave.applications.store') }}" enctype="multipart/form-data">
        @csrf
        <div class="row g-2">
            <div class="col-md-4"><label class="form-label small">Employee *</label><select name="employee_id" class="form-select form-select-sm" required><option value="">— Select —</option>@foreach($employees as $e)<option value="{{ $e->id }}">{{ $e->display_name }} ({{ $e->employee_code }})</option>@endforeach</select></div>
            <div class="col-md-3"><label class="form-label small">Leave Type *</label><select name="leave_type_id" class="form-select form-select-sm" required><option value="">— Select —</option>@foreach($types as $t)<option value="{{ $t->id }}">{{ $t->name }} ({{ $t->yearly_allowance }}d)</option>@endforeach</select></div>
            <div class="col-md-2"><label class="form-label small">Start *</label><input type="date" name="start_date" class="form-control form-control-sm" required></div>
            <div class="col-md-2"><label class="form-label small">End *</label><input type="date" name="end_date" class="form-control form-control-sm" required></div>
            <div class="col-md-12"><label class="form-label small">Reason</label><textarea name="reason" class="form-control form-control-sm" rows="2"></textarea></div>
            <div class="col-md-4"><label class="form-label small">Attachment</label><input type="file" name="attachment" class="form-control form-control-sm"></div>
            <div class="col-md-12"><button type="submit" class="btn btn-sm btn-primary">Submit</button> <a href="{{ route('hr.leave.applications') }}" class="btn btn-sm btn-outline-secondary">Cancel</a></div>
        </div>
    </form>
</div>
@endsection

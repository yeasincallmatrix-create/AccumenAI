@extends('layouts.institute')
@section('title','Training — HR')
@section('content')
<div class="standalone-heading">
    <h4>{{ $training->title }}</h4><p>{{ $training->provider ?? '—' }} · {{ $training->trainer ?? '' }} · {{ $training->start_date->format('Y-m-d') }} → {{ $training->end_date->format('Y-m-d') }}</p>
</div>
<div class="row g-3">
    <div class="col-md-4"><div class="admin-card p-3"><h6>Details</h6><div class="small">Provider: {{ $training->provider ?? '—' }}<br>Trainer: {{ $training->trainer ?? '—' }}<br>Location: {{ $training->location ?? '—' }} {{ $training->is_online ? '(Online)' : '' }}<br>Capacity: {{ $training->enrolled_count }}/{{ $training->capacity ?? '∞' }}<br>Cost: {{ number_format($training->cost,0) }}<br>Status: {{ $training->status }}</div>
        @if($canManage)
            <form method="POST" action="{{ route('hr.training.programs.update', $training) }}" class="mt-2">@csrf @method('PUT')<select name="status" class="form-select form-select-sm d-inline" style="width:50%"><option value="planned" @selected($training->status==='planned')>planned</option><option value="ongoing" @selected($training->status==='ongoing')>ongoing</option><option value="completed" @selected($training->status==='completed')>completed</option></select><button class="btn btn-sm btn-outline-primary">Update</button></form>
        @endif
    </div></div>
    <div class="col-md-8"><div class="admin-card p-3"><h6>Enrollments</h6>
        @if($canEnroll)
            <form method="POST" action="{{ route('hr.training.programs.enroll', $training) }}" class="row g-1 mb-2">@csrf<div class="col-md-6"><select name="employee_id" class="form-select form-select-sm" required><option value="">— Employee —</option>@foreach($employees as $e)<option value="{{ $e->id }}">{{ $e->display_name }} ({{ $e->employee_code }})</option>@endforeach</select></div><div class="col-md-3"><button class="btn btn-sm btn-primary">Enroll</button></div></form>
        @endif
        <div class="table-responsive">
            <table class="table small mb-0">
                <thead><tr><th>Employee</th><th>Status</th><th>Result</th><th>Certificate</th><th></th></tr></thead>
                <tbody>
                    @forelse($training->enrollments as $en)
                        <tr>
                            <td>{{ $en->employee->display_name }}<div class="text-muted small">{{ $en->employee->employee_code }}</div></td>
                            <td>{{ $en->status }}</td>
                            <td>{{ $en->result }} {{ $en->score ? '('.$en->score.')' : '' }}</td>
                            <td>@if($en->certificate_path)<a href="{{ Storage::url($en->certificate_path) }}" target="_blank">View</a>@else — @endif</td>
                            <td>
                                @if($canManage)
                                    <form method="POST" action="{{ route('hr.training.enrollments.update', $en) }}" enctype="multipart/form-data" class="d-flex gap-1">@csrf
                                        <select name="status" class="form-select form-select-sm" style="width:120px"><option value="enrolled" @selected($en->status==='enrolled')>enrolled</option><option value="completed" @selected($en->status==='completed')>completed</option><option value="dropped" @selected($en->status==='dropped')>dropped</option></select>
                                        <input type="file" name="certificate" class="form-control form-control-sm" style="width:160px">
                                        <button class="btn btn-sm btn-outline-primary">Save</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted py-2">No enrollments.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div></div>
</div>
@endsection

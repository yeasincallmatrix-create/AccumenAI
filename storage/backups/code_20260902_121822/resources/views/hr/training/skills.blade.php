@extends('layouts.institute')
@section('title','Skills — HR')
@section('content')
<div class="standalone-heading"><h4>Employee Skills</h4><p>Skill name, proficiency, acquired date, verification.</p></div>
<div class="admin-card p-3 mb-3">
    <h6>Add Skill</h6>
    <form method="POST" action="{{ route('hr.training.skills.store') }}" class="row g-2">
        @csrf
        <div class="col-md-3"><select name="employee_id" class="form-select form-select-sm" required><option value="">— Employee —</option>@foreach($employees as $e)<option value="{{ $e->id }}">{{ $e->display_name }}</option>@endforeach</select></div>
        <div class="col-md-2"><input type="text" name="skill_name" class="form-control form-control-sm" placeholder="Skill *" required></div>
        <div class="col-md-2"><select name="proficiency_level" class="form-select form-select-sm" required><option value="beginner">beginner</option><option value="intermediate">intermediate</option><option value="advanced">advanced</option><option value="expert">expert</option></select></div>
        <div class="col-md-2"><input type="date" name="acquired_date" class="form-control form-control-sm"></div>
        <div class="col-md-1"><button type="submit" class="btn btn-sm btn-primary">Add</button></div>
    </form>
</div>
<div class="admin-card">
    <div class="table-responsive">
        <table class="table small mb-0">
            <thead><tr><th>Employee</th><th>Skill</th><th>Level</th><th>Acquired</th><th>Status</th><th></th></tr></thead>
            <tbody>
                @forelse($skills as $s)
                    <tr>
                        <td>{{ $s->employee->display_name }}</td>
                        <td>{{ $s->skill_name }}</td>
                        <td>{{ $s->proficiency_level }}</td>
                        <td>{{ $s->acquired_date?->format('Y-m-d') ?? '—' }}</td>
                        <td><span class="badge {{ $s->verification_status==='verified'?'text-bg-success':($s->verification_status==='rejected'?'text-bg-danger':'text-bg-warning') }}">{{ $s->verification_status }}</span></td>
                        <td>
                            @if($s->verification_status==='pending')
                                <form method="POST" action="{{ route('hr.training.skills.verify', $s) }}" class="d-inline">@csrf<button name="status" value="verified" class="btn btn-sm btn-success">Verify</button></form>
                                <form method="POST" action="{{ route('hr.training.skills.verify', $s) }}" class="d-inline">@csrf<button name="status" value="rejected" class="btn btn-sm btn-outline-danger">Reject</button></form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted py-3">No skills.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($skills->hasPages())<div class="p-2 border-top">{{ $skills->links() }}</div>@endif
</div>
@endsection

@extends('layouts.institute')

@section('title', 'Branch — AccumenAI')

@section('content')
<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div class="page-header-text">
        <h4 class="page-header-title">
            {{ $branch->name }}
            <span class="badge bg-{{ $branch->status === 'active' ? 'success' : 'secondary' }}">{{ ucfirst($branch->status) }}</span>
            <code>{{ $branch->code }}</code>
        </h4>
    </div>
    <div class="page-header-actions">
        <a class="btn btn-warning" href="{{ route('medical.branches.edit', $branch) }}">
            <i class="bi bi-pencil me-1"></i>Edit
        </a>
        <form action="{{ route('medical.branches.toggle-status', $branch) }}" method="POST" class="d-inline"
              onsubmit="return confirm('Change branch status? Existing clinical records keep their branch and stay intact.')">
            @csrf
            <button type="submit" class="btn btn-secondary">
                {{ $branch->status === 'active' ? 'Deactivate' : 'Activate' }}
            </button>
        </form>
        <a class="btn btn-secondary" href="{{ route('medical.branches.index') }}">
            <i class="bi bi-arrow-left me-1"></i>Back
        </a>
    </div>
</div>

<div class="card">
    <div class="card-header"><h6 class="mb-0">Assigned Doctors ({{ $assignments->count() }})</h6></div>
    <div class="card-body">
        @if($assignments->count() > 0)
            <ul class="mb-3">
                @foreach($assignments as $assignment)
                    <li>
                        <strong>{{ $assignment->doctor_name ?? 'Doctor #'.$assignment->doctor_id }}</strong>
                        <span class="text-muted">({{ $assignment->registration_number ?? '—' }})</span>
                        @if(! $assignment->is_active)
                            <span class="badge bg-secondary">inactive</span>
                        @endif
                        <form action="{{ route('medical.branches.doctors.remove', [$branch, $assignment->doctor_id]) }}"
                              method="POST" class="d-inline"
                              onsubmit="return confirm('Remove this assignment? The doctor returns to institute-wide scope if no other assignments remain.')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-link text-danger p-0">remove</button>
                        </form>
                    </li>
                @endforeach
            </ul>
        @else
            <p class="text-muted">No explicit assignments — all institute doctors work here under legacy scope.</p>
        @endif
        <form action="{{ route('medical.branches.doctors.assign', $branch) }}" method="POST" class="row g-2">
            @csrf
            <div class="col-md-8">
                <select name="doctor_id" class="form-select" required>
                    <option value="">Select doctor…</option>
                    @foreach($doctors as $doctor)
                        <option value="{{ $doctor->id }}">{{ $doctor->user->name ?? 'Doctor #'.$doctor->id }} ({{ $doctor->registration_number }})</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4">
                <button type="submit" class="btn btn-primary w-100">Assign</button>
            </div>
        </form>
    </div>
</div>

<div class="card mt-3">
    <div class="card-header"><h6 class="mb-0">Clinical Usage (branch-owned records)</h6></div>
    <div class="card-body">
        <ul class="mb-0">
            @foreach($usage as $label => $count)
                <li>{{ ucfirst($label) }}: <strong>{{ $count }}</strong></li>
            @endforeach
        </ul>
        <p class="text-muted small mt-2 mb-0">Deactivation never moves or deletes these records.</p>
    </div>
</div>
@endsection

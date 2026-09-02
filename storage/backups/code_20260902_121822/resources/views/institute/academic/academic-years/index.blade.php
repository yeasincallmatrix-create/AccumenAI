@extends('layouts.standalone')

@section('title', 'Academic Years — AccumenAI')
@section('page_title', 'Academic Years')

@section('content')

@include('institute.academic._step-nav', ['currentStep'=>2,'currentLabel'=>'Academic Years','prevRoute'=>'settings.academic.index','prevLabel'=>'1 · Structure','nextRoute'=>'settings.academic.placements.index','nextLabel'=>'3 · Placements'])

<div class="standalone-heading">
    <h4>2 · Academic Years — Academic Years</h4>
    <p>Step 2 of 7 — Each placement belongs to one academic year so 2026 and 2027 placements stay separate and historical. Complete before <a href="{{ route('settings.academic.placements.index') }}">3 · Placements</a>. Years are also visible inside Placements for backward compatibility.</p>
</div>

{{-- Reused manager from placements/index — extracted for dedicated navigation --}}
<div class="admin-card">
    <div class="table-toolbar">
        <div class="toolbar-info">
            <i class="bi bi-calendar3"></i>
            <span class="fw-semibold">Academic Years</span>
            <span class="badge text-bg-secondary badge-soft ms-2">{{ $academicYears->count() }} years</span>
        </div>
    </div>
    <p class="text-muted small mb-3 px-3">
        Each placement belongs to one academic year so historical placements stay separate. Mark one as <em>Current</em> for default selection.
    </p>

    <form method="POST" action="{{ route('settings.academic.academic-years.store') }}" class="row g-2 align-items-end px-3 pb-3">
        @csrf
        <div class="col-md-3">
            <label class="form-label small text-muted mb-1">Name</label>
            <input type="text" name="name" class="form-control form-control-sm" placeholder="e.g. Academic Year 2026" required maxlength="120">
        </div>
        <div class="col-md-2">
            <label class="form-label small text-muted mb-1">Code</label>
            <input type="text" name="code" class="form-control form-control-sm" placeholder="e.g. 2026" required maxlength="40">
        </div>
        <div class="col-md-2">
            <label class="form-label small text-muted mb-1">Start</label>
            <input type="date" name="start_date" class="form-control form-control-sm">
        </div>
        <div class="col-md-2">
            <label class="form-label small text-muted mb-1">End</label>
            <input type="date" name="end_date" class="form-control form-control-sm">
        </div>
        <div class="col-md-2">
            <div class="form-check form-switch mb-0 pb-1">
                <input class="form-check-input" type="checkbox" name="is_current" value="1" id="ay_add_current">
                <label class="form-check-label small text-muted" for="ay_add_current">Current year</label>
            </div>
        </div>
        <div class="col-md-1">
            <button class="btn btn-sm btn-primary w-100" type="submit"><i class="bi bi-plus-lg"></i></button>
        </div>
    </form>

    @error('academic_year')<div class="text-danger small px-3">{{ $message }}</div>@enderror

    @if ($academicYears->isNotEmpty())
        <div class="table-responsive border-top">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Code</th>
                        <th>Start</th>
                        <th>End</th>
                        <th class="text-center">Current</th>
                        <th class="text-center">Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($academicYears as $year)
                        <tr>
                            <td colspan="7" class="p-0">
                                <form method="POST" action="{{ route('settings.academic.academic-years.update', $year) }}" class="row g-2 align-items-center p-2">
                                    @csrf
                                    @method('PUT')
                                    <input type="hidden" name="name" value="{{ $year->name }}">
                                    <input type="hidden" name="code" value="{{ $year->code }}">
                                    <input type="hidden" name="start_date" value="{{ $year->start_date?->format('Y-m-d') ?? '' }}">
                                    <input type="hidden" name="end_date" value="{{ $year->end_date?->format('Y-m-d') ?? '' }}">
                                    <div class="col d-flex align-items-center gap-2 flex-wrap">
                                        <span class="fw-semibold">{{ $year->name }}</span>
                                        <span class="badge text-bg-light border">{{ $year->code }}</span>
                                        <small class="text-muted">{{ $year->start_date?->format('d M Y') ?? '—' }} → {{ $year->end_date?->format('d M Y') ?? '—' }}</small>
                                    </div>
                                    <div class="col-auto">
                                        <label class="form-check form-switch m-0">
                                            <input class="form-check-input" type="checkbox" name="is_current" value="1" @checked($year->is_current)>
                                            <span class="form-check-label small text-muted">Current</span>
                                        </label>
                                    </div>
                                    <div class="col-auto">
                                        <label class="form-check form-switch m-0">
                                            <input class="form-check-input" type="checkbox" name="status" value="1" @checked($year->status)>
                                            <span class="form-check-label small text-muted">Active</span>
                                        </label>
                                    </div>
                                    <div class="col-auto d-flex gap-1">
                                        <button class="btn btn-sm btn-outline-primary" type="submit"><i class="bi bi-check-lg"></i> Save</button>
                                        <button class="btn btn-sm btn-outline-danger" type="submit" form="ay-delete-{{ $year->id }}"><i class="bi bi-trash"></i></button>
                                    </div>
                                </form>
                                <form id="ay-delete-{{ $year->id }}" method="POST" action="{{ route('settings.academic.academic-years.destroy', $year) }}" data-ajax-delete="1" data-confirm="Remove academic year {{ $year->name }}?">
                                    @csrf
                                    @method('DELETE')
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>

<div class="mt-3">
    <a href="{{ route('settings.academic.placements.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i> Back to 3 · Placements</a>
</div>

@endsection

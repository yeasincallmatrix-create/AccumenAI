@extends('layouts.standalone')

@section('title', 'Grade Scales — AccumenAI')
@section('page_title', 'Grade Scales')

@section('content')

@include('institute.academic._step-nav', ['currentStep'=>6,'currentLabel'=>'Grade Overrides','prevRoute'=>'settings.academic.aggregations.index','prevLabel'=>'5 · Weight Schemes','nextRoute'=>'settings.academic.final-results.index','nextLabel'=>'7 · Result Cycles'])
@include('institute.academic._dependency-banner', ['context'=>'grading'])

<div class="standalone-heading">
    <h4>6 · Grade Overrides — Grade Scales</h4>
    <p>Step 6 of 7 — How an aggregate becomes a grade, grade point and PASS/FAIL, and how GPA is calculated. Requires <a href="{{ route('settings.academic.index') }}">1 · Structure</a> context; your overrides replace country defaults (managed by platform).</p>
    <a href="{{ route('settings.academic.grading.create') }}" class="btn btn-primary btn-sm">
        <i class="bi bi-plus-lg me-1"></i>New Institute Override
    </a>
</div>

@if (session('status'))
    <div class="alert alert-success py-2">
        <i class="bi bi-check-circle me-1"></i>{{ session('status') }}
    </div>
@endif

@if ($errors->count())
    <div class="alert alert-danger">
        <i class="bi bi-exclamation-triangle-fill me-1"></i><strong>Please fix the following:</strong>
        <ul class="mb-0 mt-1">
            @foreach ($errors->all() as $message)
                <li>{{ $message }}</li>
            @endforeach
        </ul>
    </div>
@endif

{{-- Effective scale per class --}}
<div class="admin-card mb-3">
    <div class="table-toolbar">
        <div class="toolbar-info"><i class="bi bi-signpost-2"></i> Effective Scale per Class / Grade</div>
    </div>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>Class / Grade</th>
                    <th>Grade Scale</th>
                    <th>Scope</th>
                    <th>GPA Mode</th>
                    <th>Bands</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($classLabels as $label)
                    <tr>
                        <td class="fw-semibold">{{ $label['class'] }}</td>
                        @if ($label['scale'] !== null)
                            <td>{{ $label['scale']->name }}</td>
                            <td>
                                <span class="badge text-bg-{{ $label['scale']->isInstituteOverride() ? 'primary' : 'light' }}">
                                    {{ $label['scale']->scopeLabel() }}
                                </span>
                                @if ($label['scale']->isInstituteOverride())
                                    <a href="{{ route('settings.academic.grading.edit', $label['scale']->id) }}" class="small ms-1">edit</a>
                                @endif
                            </td>
                            <td>{{ $label['scale']->gpa_mode === 'credit_weighted' ? 'Credit Weighted' : 'Equal Weight' }}</td>
                            <td>{{ $label['scale']->rows->count() }}</td>
                        @else
                            <td colspan="4"><span class="text-warning">No grade scale applies — no grade or GPA will be produced.</span></td>
                        @endif
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-muted py-4">No classes / grades configured for this institute yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- Institute overrides --}}
<div class="admin-card mb-3">
    <div class="table-toolbar">
        <div class="toolbar-info"><i class="bi bi-sliders"></i> Your Institute Overrides</div>
    </div>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Name</th>
                    <th>Scope</th>
                    <th>GPA Mode</th>
                    <th>Optional Subjects</th>
                    <th>Bands</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($overrides as $scale)
                    <tr>
                        <td class="text-muted">{{ $loop->iteration }}</td>
                        <td class="fw-semibold">{{ $scale->name }}</td>
                        <td>
                            @if ($scale->academicLevel)
                                {{ $scale->academicLevel->name }}
                            @else
                                <span class="text-muted">Whole institute</span>
                            @endif
                        </td>
                        <td>{{ $scale->gpa_mode === 'credit_weighted' ? 'Credit Weighted' : 'Equal Weight' }}</td>
                        <td>{{ ucfirst($scale->optional_subject_gpa) }}</td>
                        <td>{{ $scale->rows->count() }}</td>
                        <td>
                            <span class="badge {{ $scale->status ? 'text-bg-success' : 'text-bg-secondary' }}">{{ $scale->status ? 'Active' : 'Inactive' }}</span>
                        </td>
                        <td class="text-end">
                            <a href="{{ route('settings.academic.grading.edit', $scale->id) }}" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-pencil-square"></i>
                            </a>
                            <form method="POST" action="{{ route('settings.academic.grading.destroy', $scale->id) }}" class="d-inline" data-ajax-delete="1" data-confirm="Remove this institute override?">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger" type="submit"><i class="bi bi-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-center text-muted py-4">No institute overrides yet — you inherit the defaults above.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-3">
    <a href="{{ route('settings.academic.grading.preview') }}" class="btn btn-outline-primary btn-sm">
        <i class="bi bi-calculator me-1"></i>Preview Final Grades &amp; GPA
    </a>
</div>

@endsection

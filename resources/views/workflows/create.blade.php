@extends('layouts.institute')

@section('title', mawa_e('workflows.new_workflow') . ' — AccumenAI')

@section('content')
<div class="page-header">
    <div class="page-header-text">
        <h4 class="page-header-title">{{ mawa_e('workflows.new_workflow') }}</h4>
        <p class="page-header-desc">{{ mawa_e('workflows.start_workflow') }}</p>
    </div>
</div>

@if ($errors->any())
    <div class="alert alert-danger">
        <ul class="mb-0">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="admin-card">
    <div class="card-body">
        <form method="POST" action="{{ route('workflows.store') }}">
            @csrf
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">{{ mawa_e('workflows.workflow_type') }} <span class="text-danger">*</span></label>
                    <select name="workflow_type" class="form-select" required>
                        <option value="">{{ mawa_e('workflows.select_type') }}</option>
                        @foreach ($types as $slug => $def)
                            <option value="{{ $slug }}" @selected(old('workflow_type') === $slug)>{{ $def['label'] ?? $slug }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">{{ mawa_e('workflows.title_label') }} <span class="text-danger">*</span></label>
                    <input type="text" name="title" class="form-control" value="{{ old('title') }}" maxlength="255" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">{{ mawa_e('workflows.student_label') }}</label>
                    <select name="student_id" class="form-select">
                        <option value="">{{ mawa_e('workflows.no_student') }}</option>
                        @foreach ($students as $student)
                            <option value="{{ $student->id }}" @selected(old('student_id') == $student->id)>
                                {{ trim($student->first_name.' '.$student->last_name) }}
                                @if ($student->reg_no) ({{ $student->reg_no }}) @endif
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label">{{ mawa_e('workflows.notes') }}</label>
                    <textarea name="notes" class="form-control" rows="3" maxlength="2000">{{ old('notes') }}</textarea>
                </div>
            </div>
            <div class="mt-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary">{{ mawa_e('workflows.create_workflow') }}</button>
                <a href="{{ route('workflows.index') }}" class="btn btn-outline-secondary">{{ mawa_e('workflows.cancel') }}</a>
            </div>
        </form>
    </div>
</div>
@endsection

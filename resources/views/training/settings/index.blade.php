@extends('layouts.institute')

@section('title', 'Training Settings — AccumenAI')
@section('page_title', 'Training Settings')

@section('content')
<div class="page-header">
    <h4>Training Center Settings</h4>
    <p class="text-muted small">Toggle Training Center modules. Disabled modules are hidden from the Training menu.</p>
</div>

<div class="admin-card">
    <form action="{{ route('training.settings.update') }}" method="POST">
        @csrf
        @method('PUT')
        <div class="form-check form-switch mb-3">
            <input type="hidden" name="enable_courses" value="0">
            <input class="form-check-input" type="checkbox" name="enable_courses" value="1" id="enable_courses" {{ ($config['enable_courses'] ?? true) ? 'checked' : '' }}>
            <label class="form-check-label" for="enable_courses">Enable Courses</label>
        </div>
        <div class="form-check form-switch mb-3">
            <input type="hidden" name="enable_batches" value="0">
            <input class="form-check-input" type="checkbox" name="enable_batches" value="1" id="enable_batches" {{ ($config['enable_batches'] ?? true) ? 'checked' : '' }}>
            <label class="form-check-label" for="enable_batches">Enable Batches</label>
        </div>
        <div class="form-check form-switch mb-3">
            <input type="hidden" name="enable_enrollment" value="0">
            <input class="form-check-input" type="checkbox" name="enable_enrollment" value="1" id="enable_enrollment" {{ ($config['enable_enrollment'] ?? true) ? 'checked' : '' }}>
            <label class="form-check-label" for="enable_enrollment">Enable Enrollment</label>
        </div>
        <div class="form-check form-switch mb-3">
            <input type="hidden" name="enable_attendance" value="0">
            <input class="form-check-input" type="checkbox" name="enable_attendance" value="1" id="enable_attendance" {{ ($config['enable_attendance'] ?? true) ? 'checked' : '' }}>
            <label class="form-check-label" for="enable_attendance">Enable Attendance</label>
        </div>
        <div class="form-check form-switch mb-3">
            <input type="hidden" name="enable_exams" value="0">
            <input class="form-check-input" type="checkbox" name="enable_exams" value="1" id="enable_exams" {{ ($config['enable_exams'] ?? true) ? 'checked' : '' }}>
            <label class="form-check-label" for="enable_exams">Enable Exams</label>
        </div>
        <div class="form-check form-switch mb-3">
            <input type="hidden" name="enable_certificates" value="0">
            <input class="form-check-input" type="checkbox" name="enable_certificates" value="1" id="enable_certificates" {{ ($config['enable_certificates'] ?? true) ? 'checked' : '' }}>
            <label class="form-check-label" for="enable_certificates">Enable Certificates</label>
        </div>
        <button type="submit" class="btn btn-primary mt-3"><i class="bi bi-check-lg me-1"></i> Save Settings</button>
        <a href="{{ route('training.enrollments.index') }}" class="btn btn-outline-secondary mt-3">Back to Enrollments</a>
    </form>
</div>
@endsection

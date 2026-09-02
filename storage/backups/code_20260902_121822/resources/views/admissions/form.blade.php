@section('title', ($student->exists ? 'Edit' : 'New') . ' Admission Application — AccumenAI')

@push('styles')
<style>
    .student-form-card {
        background: #fff;
        border: 1px solid #e3e8ef;
        border-radius: 10px;
        padding: 24px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, .06);
    }

    .section-title {
        font-size: 14px;
        font-weight: 700;
        color: #0d6efd;
        text-transform: uppercase;
        letter-spacing: .4px;
        margin: 24px 0 12px;
        padding-bottom: 6px;
        border-bottom: 1px solid #eef1f6;
    }
    .section-title:first-child {
        margin-top: 0;
    }

    .grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 16px;
    }
    @media (min-width: 768px) {
        .grid {
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        }
    }
    @media (min-width: 992px) {
        .field-fill {
            grid-column: span 2;
        }
    }

    .field {
        display: flex;
        flex-direction: column;
        gap: 5px;
    }
    .field label {
        font-size: 13px;
        font-weight: 500;
        color: #495057;
    }
    .field label .req {
        color: #dc3545;
    }

    .field input,
    .field select,
    .field textarea {
        padding: 9px 12px;
        border: 1px solid #ced4da;
        border-radius: 6px;
        font-size: 14px;
        width: 100%;
        background: #fff;
        transition: border-color .15s, box-shadow .15s;
    }
    .field input:focus,
    .field select:focus,
    .field textarea:focus {
        outline: none;
        border-color: #86b7fe;
        box-shadow: 0 0 0 3px rgba(13, 110, 253, .15);
    }
    .field input[readonly] {
        background: #e9ecef;
        color: #6c757d;
        cursor: not-allowed;
    }

    .hint {
        font-size: 12px;
        color: #6c757d;
    }

    .form-footer {
        display: flex;
        justify-content: flex-end;
        gap: 10px;
        margin-top: 24px;
        padding-top: 16px;
        border-top: 1px solid #eef1f6;
    }
    .btn-save {
        background: #0d6efd;
        color: #fff;
        border: none;
        padding: 9px 20px;
        border-radius: 6px;
        font-size: 14px;
        font-weight: 500;
        cursor: pointer;
    }
    .btn-save:hover {
        background: #0b5ed7;
    }
</style>
@endpush

@section('content')
@php
    $genderNames = ['male' => 'Male', 'female' => 'Female', 'other' => 'Other'];
    $sourceNames = ['walk_in' => 'Walk-in', 'online' => 'Online form', 'referral' => 'Referral', 'advertisement' => 'Advertisement', 'social_media' => 'Social media', 'agency' => 'Agency', 'other' => 'Other'];
@endphp
<div class="page-header">
    <div class="page-header-text">
        <h4 class="page-header-title">{{ $student->exists ? 'Edit' : 'New' }} Admission Application</h4>
        <p class="page-header-desc">{{ $student->exists ? 'Update the applicant details.' : 'Capture a new prospect application. The full student profile can be completed later.' }}</p>
    </div>
    <div class="page-header-actions">
        <a class="btn btn-outline-secondary" href="{{ $student->exists ? route('admissions.show', $student) : route('admissions.index') }}">
            <i class="bi bi-arrow-left"></i> Back
        </a>
    </div>
</div>

<form method="POST" action="{{ $student->exists ? route('admissions.update', $student) : route('admissions.store') }}">
    @csrf
    @if ($student->exists)
        @method('PUT')
    @endif

    <div class="student-form-card">

        <h3 class="section-title">Application</h3>
        <div class="grid">
            <div class="field">
                <label for="application_date">Application Date <span class="req">*</span></label>
                <input id="application_date" type="date" name="application_date" value="{{ old('application_date', $student->application_date?->format('Y-m-d') ?? now()->format('Y-m-d')) }}" required>
                @error('application_date') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
            </div>
            <div class="field">
                <label for="admission_source">Source</label>
                <select id="admission_source" name="admission_source">
                    <option value="">-- Select --</option>
                    @foreach ($sourceNames as $slug => $label)
                        <option value="{{ $slug }}" @selected(old('admission_source', $student->admission_source) === $slug)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('admission_source') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
            </div>
            <div class="field">
                <label for="branch_id">Branch <span class="req">*</span></label>
                <select id="branch_id" name="branch_id" required>
                    <option value="">-- Select --</option>
                    @foreach ($branches as $branch)
                        <option value="{{ $branch->id }}" @selected((string) old('branch_id', $student->branch_id) === (string) $branch->id)>{{ $branch->name }}</option>
                    @endforeach
                </select>
                @error('branch_id') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
            </div>
            <div class="field">
                <label for="applied_course_id">Applied Course <span class="req">*</span></label>
                <select id="applied_course_id" name="applied_course_id" required>
                    <option value="">-- Select --</option>
                    @foreach ($courses as $course)
                        <option value="{{ $course->id }}" @selected((string) old('applied_course_id', $student->applied_course_id) === (string) $course->id)>{{ $course->name }} ({{ $course->course_code }})</option>
                    @endforeach
                </select>
                @error('applied_course_id') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
            </div>
            <div class="field">
                <label for="applied_academic_year_id">Intended Academic Year</label>
                <select id="applied_academic_year_id" name="applied_academic_year_id">
                    <option value="">-- Select --</option>
                    @foreach ($academicYears as $year)
                        <option value="{{ $year->id }}" @selected((string) old('applied_academic_year_id', $student->applied_academic_year_id) === (string) $year->id)>{{ $year->name }}</option>
                    @endforeach
                </select>
                @error('applied_academic_year_id') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
            </div>
        </div>

        <h3 class="section-title">Applicant</h3>
        <div class="grid">
            <div class="field">
                <label for="full_name">Full Name <span class="req">*</span></label>
                <input id="full_name" name="full_name" value="{{ old('full_name', $student->full_name) }}" required maxlength="120" placeholder="e.g. Rahima Akter">
                @error('full_name') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
            </div>
            <div class="field">
                <label for="gender">Gender</label>
                <select id="gender" name="gender">
                    <option value="">-- Select --</option>
                    @foreach ($genderNames as $g => $label)
                        <option value="{{ $g }}" @selected(old('gender', $student->gender) === $g)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('gender') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
            </div>
            <div class="field">
                <label for="dob">Date of Birth</label>
                <input id="dob" type="date" name="dob" value="{{ old('dob', $student->dob?->format('Y-m-d')) }}">
                @error('dob') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
            </div>
            <div class="field">
                <label for="phone">Phone <span class="req">*</span></label>
                @include('partials.phone', ['name' => 'phone', 'id' => 'phone', 'value' => old('phone', $student->phone), 'required' => true])
                @error('phone') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
            </div>
            <div class="field">
                <label for="email">Email</label>
                <input id="email" type="email" name="email" value="{{ old('email', $student->email) }}" maxlength="150" placeholder="student@example.com">
                @error('email') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
            </div>
        </div>

        <h3 class="section-title">Guardian</h3>
        <div class="grid">
            <div class="field">
                <label for="guardian_name">Guardian Name</label>
                <input id="guardian_name" name="guardian_name" value="{{ old('guardian_name', $student->guardian_name) }}" maxlength="120" placeholder="e.g. Md. Karim Uddin">
                @error('guardian_name') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
            </div>
            <div class="field">
                <label for="guardian_phone">Guardian Phone</label>
                @include('partials.phone', ['name' => 'guardian_phone', 'id' => 'guardian_phone', 'value' => old('guardian_phone', $student->guardian_phone)])
                @error('guardian_phone') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
            </div>
        </div>

        <div class="form-footer">
            <a class="btn btn-outline-secondary" href="{{ $student->exists ? route('admissions.show', $student) : route('admissions.index') }}">Cancel</a>
            <button class="btn-save" type="submit"><i class="bi bi-check-lg me-1"></i>{{ $student->exists ? 'Save Changes' : 'Create Application' }}</button>
        </div>

    </div>
</form>
@endsection
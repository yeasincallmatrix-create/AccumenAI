@php
    $isProfessionalTabs = \App\Support\InstituteDomain::isProfessional($institute ?? $instituteForTabs ?? null);
    $activeTab ??= request()->routeIs('students.*') ? 'students' : (request()->routeIs('admissions.pipeline*') ? 'pipeline' : (request()->routeIs('admissions.*') ? 'admissions' : 'students'));
    $instituteForTabs = $institute ?? ($instituteId ? \App\Models\Institute::find($instituteId) : null) ?? null;
    if (!$instituteForTabs && isset($user) && $user) {
        // fallback via view composer institute
        $instituteForTabs = $institute ?? null;
    }
    $studentsCount = $studentsCount ?? ($instituteForTabs ? \App\Models\Student::where('institute_id', $instituteForTabs->id)->where('admission_status', \App\Models\Student::ADMISSION_STATUS_ENROLLED)->count() : 0);
    $admissionsCount = $admissionsCount ?? ($instituteForTabs ? \App\Models\Student::where('institute_id', $instituteForTabs->id)->whereIn('admission_status', [\App\Models\Student::ADMISSION_STATUS_DRAFT, \App\Models\Student::ADMISSION_STATUS_SUBMITTED, \App\Models\Student::ADMISSION_STATUS_UNDER_REVIEW, \App\Models\Student::ADMISSION_STATUS_APPROVED])->count() : 0);
    $pipelineCount = $pipelineCount ?? ($instituteForTabs ? \App\Models\CrmLead::where('institute_id', $instituteForTabs->id)->count() + \App\Models\Student::where('institute_id', $instituteForTabs->id)->whereNotNull('admission_status')->count() : 0);
@endphp
<ul class="nav nav-tabs mb-3" role="tablist">
    <li class="nav-item" role="presentation">
        <a class="nav-link {{ $activeTab === 'students' ? 'active' : '' }}" href="{{ route('students.index') }}">
            <i class="bi bi-people-fill me-1"></i> {{ ($isProfessionalTabs ?? false) ? 'Trainees' : 'Students' }}
            <span class="badge text-bg-primary badge-soft ms-1">{{ $studentsCount }}</span>
        </a>
    </li>
    <li class="nav-item" role="presentation">
        <a class="nav-link {{ $activeTab === 'admissions' ? 'active' : '' }}" href="{{ route('admissions.index') }}">
            <i class="bi bi-file-earmark-person-fill me-1"></i> Admissions
            <span class="badge text-bg-secondary badge-soft ms-1">{{ $admissionsCount }}</span>
        </a>
    </li>
    <li class="nav-item" role="presentation">
        <a class="nav-link {{ $activeTab === 'pipeline' ? 'active' : '' }}" href="{{ route('admissions.pipeline') }}">
            <i class="bi bi-diagram-3-fill me-1"></i> Admission Pipeline
            <span class="badge text-bg-warning badge-soft ms-1">{{ $pipelineCount }}</span>
        </a>
    </li>
</ul>

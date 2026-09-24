@php
    $isProfessionalTabs = \App\Support\InstituteDomain::isProfessional($institute ?? $instituteForTabs ?? null);
    $activeTab ??= 'students';
    $studentsCount ??= 0;
@endphp
<ul class="nav nav-tabs mb-3" role="tablist">
    <li class="nav-item" role="presentation">
        <a class="nav-link {{ $activeTab === 'students' ? 'active' : '' }}" href="{{ route('training.students.index') }}">
            <i class="bi bi-people-fill me-1"></i> {{ ($isProfessionalTabs ?? false) ? 'Trainees' : 'Students' }}
            <span class="badge text-bg-primary badge-soft ms-1">{{ $studentsCount }}</span>
        </a>
    </li>
</ul>

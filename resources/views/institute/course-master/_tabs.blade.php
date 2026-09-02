@php
    $activeTab ??= 'courses';
    $coursesCount ??= 0;
    $subjectsCount ??= 0;
    $batchesCount ??= 0;
    $archiveCount ??= 0;
    $subjectsHref = route('courses.manage.subjects.index');
@endphp
<ul class="nav nav-tabs mb-3" role="tablist">
    <li class="nav-item" role="presentation">
        <a class="nav-link {{ $activeTab === 'courses' ? 'active' : '' }}" href="{{ route('courses.manage.index') }}">
            <i class="bi bi-journal-bookmark-fill me-1"></i>{{ mawa_e('courses.tab_courses') }}
            <span class="badge text-bg-success badge-soft ms-1">{{ $coursesCount }}</span>
        </a>
    </li>
    <li class="nav-item" role="presentation">
        <a class="nav-link {{ $activeTab === 'subjects' ? 'active' : '' }}" href="{{ $subjectsHref }}">
            <i class="bi bi-collection me-1"></i>{{ mawa_e('subjects.tab_subjects') }}
            <span class="badge text-bg-secondary badge-soft ms-1">{{ $subjectsCount }}</span>
        </a>
    </li>
</ul>
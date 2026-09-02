@php
    $activeTab ??= 'courses';
    $coursesCount ??= 0;
    $subjectsCount ??= 0;
    $batchesCount ??= 0;
    $archiveCount ??= 0;
    $subjectsHref = route('courses.subjects');
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
            <i class="bi bi-collection me-1"></i>{{ mawa_e('courses.tab_subjects') }}
            <span class="badge text-bg-secondary badge-soft ms-1">{{ $subjectsCount }}</span>
        </a>
    </li>
    <li class="nav-item" role="presentation">
        <a class="nav-link {{ $activeTab === 'batches' ? 'active' : '' }}" href="{{ route('batches.index') }}">
            <i class="bi bi-collection-fill me-1"></i>{{ mawa_e('courses.tab_batches') }}
            <span class="badge text-bg-secondary badge-soft ms-1">{{ $batchesCount }}</span>
        </a>
    </li>
    <li class="nav-item" role="presentation">
        <a class="nav-link {{ $activeTab === 'archive' ? 'active' : '' }}" href="{{ route('courses.archive') }}">
            <i class="bi bi-archive me-1"></i>{{ mawa_e('courses.tab_archive') }}
            <span class="badge text-bg-dark badge-soft ms-1">{{ $archiveCount }}</span>
        </a>
    </li>
</ul>
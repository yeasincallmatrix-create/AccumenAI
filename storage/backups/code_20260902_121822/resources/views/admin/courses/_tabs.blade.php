@php
    $activeTab ??= 'courses';
    $coursesCount ??= 0;
    $batchesCount ??= 0;
    $subjectsCount ??= 0;
    $archiveCount ??= 0;
    $subjectRequestsCount ??= \App\Models\SubjectRequest::query()->where('status', 'pending')->count();
@endphp
<ul class="nav nav-tabs mb-3" role="tablist">
    <li class="nav-item" role="presentation">
        <a class="nav-link {{ $activeTab === 'courses' ? 'active' : '' }}" href="{{ route('admin.courses.index', ['industry' => 'education']) }}">
            <i class="bi bi-journal-bookmark-fill me-1"></i> Courses
            <span class="badge text-bg-success badge-soft ms-1">{{ $coursesCount }}</span>
        </a>
    </li>
    <li class="nav-item" role="presentation">
        <a class="nav-link {{ $activeTab === 'subjects' ? 'active' : '' }}" href="{{ route('admin.courses.subjects', ['industry' => 'education']) }}">
            <i class="bi bi-collection me-1"></i> Professional Subjects
            <span class="badge text-bg-secondary badge-soft ms-1">{{ $subjectsCount }}</span>
        </a>
    </li>
    <li class="nav-item" role="presentation">
        <a class="nav-link {{ $activeTab === 'subject_requests' ? 'active' : '' }}" href="{{ route('admin.courses.subjects-requests', ['industry' => 'education']) }}">
            <i class="bi bi-inbox me-1"></i> Subject Requests
            @if ($subjectRequestsCount > 0)
                <span class="badge text-bg-warning badge-soft ms-1">{{ $subjectRequestsCount }}</span>
            @endif
        </a>
    </li>
    <li class="nav-item" role="presentation">
        <a class="nav-link {{ $activeTab === 'batches' ? 'active' : '' }}" href="{{ route('admin.courses.batches', ['industry' => 'education']) }}">
            <i class="bi bi-collection-fill me-1"></i> Batches
            <span class="badge text-bg-secondary badge-soft ms-1">{{ $batchesCount }}</span>
        </a>
    </li>
    <li class="nav-item" role="presentation">
        <a class="nav-link {{ $activeTab === 'archive' ? 'active' : '' }}" href="{{ route('admin.courses.archive', ['industry' => 'education']) }}">
            <i class="bi bi-archive me-1"></i> Archive
            <span class="badge text-bg-dark badge-soft ms-1">{{ $archiveCount }}</span>
        </a>
    </li>
</ul>
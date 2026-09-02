@php
    $activeTab ??= 'classes';
    $classesCount ??= 0;
    $subjectsCount ??= 0;
    $batchesCount ??= 0;
    $archiveCount ??= 0;
@endphp
<ul class="nav nav-tabs mb-3" role="tablist">
    <li class="nav-item" role="presentation">
        <a class="nav-link {{ $activeTab === 'classes' ? 'active' : '' }}" href="{{ route('admin.classes.index', ['industry' => 'education']) }}">
            <i class="bi bi-diagram-3-fill me-1"></i> Classes
            <span class="badge text-bg-success badge-soft ms-1">{{ $classesCount }}</span>
        </a>
    </li>
    <li class="nav-item" role="presentation">
        <a class="nav-link {{ $activeTab === 'subjects' ? 'active' : '' }}" href="{{ route('admin.classes.subjects', ['industry' => 'education']) }}">
            <i class="bi bi-collection me-1"></i> Academic Subjects
            <span class="badge text-bg-secondary badge-soft ms-1">{{ $subjectsCount }}</span>
        </a>
    </li>
    <li class="nav-item" role="presentation">
        <a class="nav-link {{ $activeTab === 'batches' ? 'active' : '' }}" href="{{ route('admin.classes.batches', ['industry' => 'education']) }}">
            <i class="bi bi-collection-fill me-1"></i> Batches
            <span class="badge text-bg-secondary badge-soft ms-1">{{ $batchesCount }}</span>
        </a>
    </li>
    <li class="nav-item" role="presentation">
        <a class="nav-link {{ $activeTab === 'archive' ? 'active' : '' }}" href="{{ route('admin.classes.archive', ['industry' => 'education']) }}">
            <i class="bi bi-archive me-1"></i> Archive
            <span class="badge text-bg-dark badge-soft ms-1">{{ $archiveCount }}</span>
        </a>
    </li>
</ul>
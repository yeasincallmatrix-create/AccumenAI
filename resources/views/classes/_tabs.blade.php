@php
    $activeTab ??= 'classes';
    $classesCount ??= 0;
    $subjectsCount ??= 0;
    $batchesCount ??= 0;
    $archiveCount ??= 0;
@endphp
<ul class="nav nav-tabs mb-3" role="tablist">
    <li class="nav-item" role="presentation">
        <a class="nav-link {{ $activeTab === 'classes' ? 'active' : '' }}" href="{{ route('classes.index') }}">
            <i class="bi bi-journal-bookmark-fill me-1"></i>{{ mawa_e('classes.tab_classes') }}
            <span class="badge text-bg-success badge-soft ms-1">{{ $classesCount }}</span>
        </a>
    </li>
    <li class="nav-item" role="presentation">
        <a class="nav-link {{ $activeTab === 'subjects' ? 'active' : '' }}" href="{{ route('classes.subjects') }}">
            <i class="bi bi-collection me-1"></i>{{ mawa_e('classes.tab_subjects') }}
            <span class="badge text-bg-secondary badge-soft ms-1">{{ $subjectsCount }}</span>
        </a>
    </li>
    <li class="nav-item" role="presentation">
        <a class="nav-link {{ $activeTab === 'batches' ? 'active' : '' }}" href="{{ route('classes.batches') }}">
            <i class="bi bi-collection-fill me-1"></i>{{ mawa_e('classes.tab_batches') }}
            <span class="badge text-bg-secondary badge-soft ms-1">{{ $batchesCount }}</span>
        </a>
    </li>
    <li class="nav-item" role="presentation">
        <a class="nav-link {{ $activeTab === 'archive' ? 'active' : '' }}" href="{{ route('classes.archive') }}">
            <i class="bi bi-archive me-1"></i>{{ mawa_e('classes.tab_archive') }}
            <span class="badge text-bg-dark badge-soft ms-1">{{ $archiveCount }}</span>
        </a>
    </li>
</ul>
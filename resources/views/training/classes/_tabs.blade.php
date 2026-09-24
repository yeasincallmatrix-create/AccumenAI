@php
    $activeTab ??= 'classes';
    $classesCount ??= 0;
@endphp
<ul class="nav nav-tabs mb-3" role="tablist">
    <li class="nav-item" role="presentation">
        <a class="nav-link {{ $activeTab === 'classes' ? 'active' : '' }}" href="{{ route('training.classes.index') }}">
            <i class="bi bi-journal-bookmark-fill me-1"></i>{{ mawa_e('classes.tab_classes') }}
            <span class="badge text-bg-success badge-soft ms-1">{{ $classesCount }}</span>
        </a>
    </li>
</ul>
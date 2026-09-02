@props(['title', 'subtitle' => null, 'export' => null, 'exportLabel' => 'Export CSV'])

<div class="page-header">
    <div class="page-header-text">
        <h4 class="page-header-title">{{ $title }}</h4>
        @if ($subtitle)
            <p class="page-header-desc">{{ $subtitle }}</p>
        @endif
    </div>
    <div class="d-flex align-items-center gap-2">
        <a href="{{ route('academic.analytics.index') }}" class="btn btn-outline-primary btn-sm">
            <i class="bi bi-grid-fill me-1"></i>Analytics Home
        </a>
        @if ($export)
            <a href="{{ $export }}" class="btn btn-outline-success btn-sm">
                <i class="bi bi-download me-1"></i>{{ $exportLabel }}
            </a>
        @endif
    </div>
</div>
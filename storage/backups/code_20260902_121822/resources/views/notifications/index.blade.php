@extends('layouts.institute')

@section('title', 'Notifications — AccumenAI')

@section('content')
@push('styles')
<style>
    @media print {
        .topbar, .sidebar, .sidebar-backdrop, .page-header, .monetix-print-hidden { display: none !important; }
        .layout { display: block !important; min-height: 0 !important; }
        .content { width: 100% !important; margin: 0 !important; padding: 0 !important; }
        .admin-card { box-shadow: none !important; border: none !important; }
        .print-header { display: block !important; margin-bottom: 12px; }
    }
</style>
@endpush
@php
    $categoryIcon = [
        'info'     => 'bi bi-info-circle-fill text-primary',
        'success'  => 'bi bi-check-circle-fill text-success',
        'warning'  => 'bi bi-exclamation-triangle-fill text-warning',
        'error'    => 'bi bi-x-circle-fill text-danger',
        'security' => 'bi bi-shield-lock-fill text-warning',
    ];
    $scopeBadge = [
        'platform'  => 'text-bg-primary',
        'institute' => 'text-bg-info',
        'user'      => 'text-bg-secondary',
    ];
@endphp

<div class="page-header">
    <div class="page-header-text">
        <h4 class="page-header-title">{{ mawa_e('notifications.title') }}</h4>
        <p class="page-header-desc">{{ mawa_e('notifications.center_subtitle') }}</p>
    </div>
    <div class="page-header-actions">
        <button class="btn btn-outline-primary" type="button" onclick="window.print()" title="{{ mawa_e('actions.print') }}">
            <i class="bi bi-printer me-1"></i>{{ mawa_e('actions.print') }}
        </button>
    </div>
</div>

<div class="admin-card">
    <div class="print-header d-none">
        <h4 class="mb-1">{{ $institute->name ?? '' }} — {{ mawa_e('notifications.title') }}</h4>
        <p class="mb-0 text-muted">{{ $notifications->count() }} notifications · {{ $unreadCount }} {{ mawa_e('notifications.unread') }} · {{ now()->format('d M Y') }}</p>
    </div>
    <div class="table-toolbar">
        <div class="toolbar-info"><i class="bi bi-bell-fill"></i> {{ $notifications->count() }} notifications · {{ $unreadCount }} {{ mawa_e('notifications.unread') }}</div>
        @if ($notifications->isNotEmpty())
            <form method="POST" action="{{ route('notifications.read-all') }}" class="monetix-print-hidden">
                @csrf
                <button type="submit" class="btn btn-sm btn-primary">
                    <i class="bi bi-check2-all"></i> {{ mawa_e('notifications.mark_all_read') }}
                </button>
            </form>
        @endif
    </div>

    <ul class="list-group list-group-flush">
        @forelse ($notifications as $notification)
            <li class="list-group-item d-flex align-items-start gap-3 py-3 notification-row {{ in_array($notification->id, $readIds, true) ? '' : 'notification-row-unread' }}">
                <i class="{{ $categoryIcon[$notification->category] ?? $categoryIcon['info'] }} fs-5 mt-1"></i>
                <div class="flex-grow-1">
                    <div class="fw-semibold">
                        {{ $notification->title }}
                        @if (! in_array($notification->id, $readIds, true))
                            <span class="badge text-bg-primary ms-1">{{ mawa_e('notifications.unread') }}</span>
                        @endif
                    </div>
                    <div class="text-muted small">{{ $notification->message }}</div>
                    <div class="text-muted small mt-1">
                        @if ($notification->institute)
                            <span class="ms-0">{{ $notification->institute->name }}</span>
                            <span class="ms-2">•</span>
                        @endif
                        <span class="ms-2">{{ $notification->created_at->format('d M Y H:i') }}</span>
                    </div>
                </div>
                @if (! in_array($notification->id, $readIds, true))
                    <form method="POST" action="{{ route('notifications.read', $notification->id) }}" class="monetix-print-hidden">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-outline-primary" title="{{ mawa_e('notifications.mark_read') }}">
                            <i class="bi bi-check2"></i>
                        </button>
                    </form>
                @endif
            </li>
        @empty
            <li class="list-group-item text-center text-muted py-4">{{ mawa_e('notifications.empty') }}</li>
        @endforelse
    </ul>
</div>
@endsection

@push('styles')
<style>
    .notification-row-unread { background: rgba(13, 110, 253, 0.05); }
    .notification-row-unread .fw-semibold { font-weight: 700; }
</style>
@endpush
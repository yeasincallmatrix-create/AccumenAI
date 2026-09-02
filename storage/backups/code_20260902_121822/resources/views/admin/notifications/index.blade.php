@extends('layouts.admin')

@section('title', 'Notifications — AccumenAI')

@section('content')
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

<nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none">Dashboard</a></li>
        <li class="breadcrumb-item active" aria-current="page">Notifications</li>
    </ol>
</nav>

<div class="page-header">
    <div class="page-header-text">
        <h4 class="page-header-title">{{ mawa_e('notifications.title') }}</h4>
        <p class="page-header-desc">{{ mawa_e('notifications.center_subtitle') }}</p>
    </div>
</div>

<div class="admin-card">
    <div class="table-toolbar">
        <div class="toolbar-info"><i class="bi bi-bell-fill"></i> {{ $notifications->count() }} notifications · {{ $unreadCount }} {{ mawa_e('notifications.unread') }}</div>
        @if ($notifications->isNotEmpty())
            <form method="POST" action="{{ route('admin.notifications.read-all') }}">
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
                        <span class="badge {{ $scopeBadge[$notification->scope] ?? 'text-bg-secondary' }}">{{ $notification->scope }}</span>
                        @if ($notification->institute)
                            <span class="ms-2">{{ $notification->institute->name }}</span>
                        @endif
                        <span class="ms-2">{{ $notification->created_at->format('d M Y H:i') }}</span>
                    </div>
                </div>
                @if (! in_array($notification->id, $readIds, true))
                    <form method="POST" action="{{ route('admin.notifications.read', $notification->id) }}">
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
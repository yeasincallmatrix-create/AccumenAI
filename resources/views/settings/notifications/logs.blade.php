@extends('layouts.standalone')

@php $backUrl = route('settings.notifications.index'); @endphp

@section('title', mawa_e('notifications_page.logs') . ' — AccumenAI')
@section('page_title', mawa_e('notifications_page.logs'))

@section('content')

<div class="standalone-heading">
    <h4>{{ mawa_e('notifications_page.logs') }}</h4>
    <p>{{ mawa_e('notifications_page.description') }}</p>
</div>

<ul class="nav nav-tabs mb-3">
    <li class="nav-item"><a class="nav-link" href="{{ route('settings.notifications.index') }}">{{ mawa_e('notifications_page.overview') }}</a></li>
    <li class="nav-item"><a class="nav-link" href="{{ route('settings.notifications.templates.index') }}">{{ mawa_e('notifications_page.templates') }}</a></li>
    <li class="nav-item"><a class="nav-link active" href="{{ route('settings.notifications.logs.index') }}">{{ mawa_e('notifications_page.logs') }}</a></li>
</ul>

<div class="admin-card">
    <div class="table-toolbar">
        <div class="toolbar-info"><i class="bi bi-journal-text"></i> {{ mawa_e('notifications_page.logs') }}</div>
    </div>

    <form method="GET" action="{{ route('settings.notifications.logs.index') }}" class="row g-2 mb-3">
        <div class="col-md-3">
            <select name="event" class="form-select form-select-sm">
                <option value="">{{ mawa_e('notifications_page.filter_event') }}</option>
                @foreach ($events as $event => $eventConfig)
                    <option value="{{ $event }}" @selected($filters['event'] === $event)>{{ $eventConfig['label'] ?? $event }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2">
            <select name="channel" class="form-select form-select-sm">
                <option value="">{{ mawa_e('notifications_page.filter_channel') }}</option>
                @foreach (['in_app' => 'in_app', 'email' => 'email', 'sms' => 'sms'] as $value => $label)
                    <option value="{{ $value }}" @selected($filters['channel'] === $value)>{{ mawa_e('notifications_page.' . $label) }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2">
            <select name="status" class="form-select form-select-sm">
                <option value="">{{ mawa_e('notifications_page.filter_status') }}</option>
                @foreach ($statuses as $status)
                    <option value="{{ $status }}" @selected($filters['status'] === $status)>{{ $status }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2">
            <input type="date" name="date" class="form-control form-control-sm" value="{{ $filters['date'] }}">
        </div>
        <div class="col-md-2">
            <button class="btn btn-outline-primary btn-sm" type="submit">{{ mawa_e('notifications_page.apply') }}</button>
            <a class="btn btn-outline-secondary btn-sm" href="{{ route('settings.notifications.logs.index') }}">{{ mawa_e('notifications_page.reset') }}</a>
        </div>
    </form>

    @if ($logs->isEmpty())
        <div class="text-muted text-center py-4">{{ mawa_e('notifications_page.no_logs') }}</div>
    @else
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th>{{ mawa_e('notifications_page.event') }}</th>
                    <th>{{ mawa_e('notifications_page.channel') }}</th>
                    <th>{{ mawa_e('notifications_page.recipient') }}</th>
                    <th>{{ mawa_e('notifications_page.contact') }}</th>
                    <th>{{ mawa_e('notifications_page.status') }}</th>
                    <th>{{ mawa_e('notifications_page.created_at') }}</th>
                    <th class="text-end">{{ mawa_e('notifications_page.actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($logs as $log)
                    <tr>
                        <td>{{ $log->event }}</td>
                        <td>{{ mawa_e('notifications_page.' . $log->channel) }}</td>
                        <td>{{ $log->recipient_type }}#{{ $log->recipient_id ?? '—' }}</td>
                        <td class="small text-muted">{{ $log->recipient_contact }}</td>
                        <td>
                            @php
                                $badge = match ($log->status) {
                                    'sent' => 'bg-success',
                                    'failed' => 'bg-danger',
                                    'queued' => 'bg-secondary',
                                    'sending' => 'bg-info',
                                    default => 'bg-light text-muted',
                                };
                            @endphp
                            <span class="badge {{ $badge }}">{{ $log->status }}</span>
                        </td>
                        <td class="small">{{ optional($log->created_at)->format('Y-m-d H:i') }}</td>
                        <td class="text-end">
                            <a class="btn btn-sm btn-outline-primary" href="{{ route('settings.notifications.logs.show', $log) }}"><i class="bi bi-eye"></i></a>
                            @if ($log->status === 'failed' && $log->retry_count < $log->max_retries)
                                <form class="d-inline" method="POST" action="{{ route('settings.notifications.logs.retry', $log) }}">
                                    @csrf
                                    <button class="btn btn-sm btn-outline-warning" type="submit" title="{{ mawa_e('notifications_page.retry_hint') }}"><i class="bi bi-arrow-clockwise"></i></button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="d-flex justify-content-end">
        {{ $logs->links() }}
    </div>
    @endif
</div>

@endsection
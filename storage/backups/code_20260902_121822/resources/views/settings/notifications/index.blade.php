@extends('layouts.standalone')

@php $backUrl = route('settings.index'); @endphp

@section('title', mawa_e('notifications_page.title') . ' — AccumenAI')
@section('page_title', mawa_e('notifications_page.hub'))

@section('content')

<div class="standalone-heading">
    <h4>{{ mawa_e('notifications_page.hub') }}</h4>
    <p>{{ mawa_e('notifications_page.description') }}</p>
</div>

<ul class="nav nav-tabs mb-3">
    <li class="nav-item"><a class="nav-link active" href="{{ route('settings.notifications.index') }}">{{ mawa_e('notifications_page.overview') }}</a></li>
    <li class="nav-item"><a class="nav-link" href="{{ route('settings.notifications.templates.index') }}">{{ mawa_e('notifications_page.templates') }}</a></li>
    <li class="nav-item"><a class="nav-link" href="{{ route('settings.notifications.logs.index') }}">{{ mawa_e('notifications_page.logs') }}</a></li>
</ul>

<div class="row g-3 mb-4">
    @foreach (['total' => 'stat_total', 'queued' => 'stat_queued', 'sending' => 'stat_sending', 'sent' => 'stat_sent', 'failed' => 'stat_failed'] as $key => $labelKey)
        <div class="col-6 col-md-3 col-lg">
            <div class="admin-card text-center p-3">
                <div class="fs-4 fw-bold">{{ $stats[$key] }}</div>
                <div class="small text-muted">{{ mawa_e('notifications_page.' . $labelKey) }}</div>
            </div>
        </div>
    @endforeach
</div>

@if ($instituteId !== null)
<div class="admin-card mb-4">
    <div class="table-toolbar">
        <div class="toolbar-info"><i class="bi bi-broadcast"></i> {{ mawa_e('notifications_page.channel_settings') }}</div>
    </div>
    <form method="POST" action="{{ route('settings.notifications.channels') }}">
        @csrf
        <p class="text-muted small">{{ mawa_e('notifications_page.channel_settings_desc') }}</p>
        <div class="row g-3 mb-3">
            @foreach (['in_app', 'email', 'sms'] as $channel)
                <div class="col-md-4">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="channel_{{ $channel }}" name="{{ $channel }}" value="1" @checked(! isset($toggles[$channel]) || $toggles[$channel])>
                        <label class="form-check-label" for="channel_{{ $channel }}">{{ mawa_e('notifications_page.' . $channel) }}</label>
                    </div>
                </div>
            @endforeach
        </div>
        <button class="btn btn-primary" type="submit"><i class="bi bi-check-lg"></i> {{ mawa_e('notifications_page.save_channels') }}</button>
    </form>
</div>
@endif

@if ($me !== null)
<div class="admin-card mb-4">
    <div class="table-toolbar">
        <div class="toolbar-info"><i class="bi bi-person-check"></i> {{ mawa_e('notifications_page.my_preferences') }}</div>
    </div>
    <form method="POST" action="{{ route('settings.notifications.preferences') }}">
        @csrf
        <p class="text-muted small">{{ mawa_e('notifications_page.my_preferences_desc') }}</p>
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>{{ mawa_e('notifications_page.event') }}</th>
                        <th>{{ mawa_e('notifications_page.in_app') }}</th>
                        <th>{{ mawa_e('notifications_page.email') }}</th>
                        <th>{{ mawa_e('notifications_page.sms') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($events as $event => $eventConfig)
                        <tr>
                            <td>{{ $eventConfig['label'] ?? $event }} <span class="text-muted small">({{ $event }})</span></td>
                            @foreach (['in_app', 'email', 'sms'] as $channel)
                                <td>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" name="disabled[{{ $event }}][{{ $channel }}]" value="1"
                                               @checked(! empty($disabled[$event][$channel]))>
                                        <label class="form-check-label small text-muted" for="">{{ mawa_e('notifications_page.pref_off') }}</label>
                                    </div>
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <button class="btn btn-primary" type="submit"><i class="bi bi-check-lg"></i> {{ mawa_e('notifications_page.save_preferences') }}</button>
    </form>
</div>
@endif

@endsection
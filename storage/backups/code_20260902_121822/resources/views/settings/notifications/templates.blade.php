@extends('layouts.standalone')

@php $backUrl = route('settings.notifications.index'); @endphp

@section('title', mawa_e('notifications_page.templates') . ' — AccumenAI')
@section('page_title', mawa_e('notifications_page.templates'))

@section('content')

<div class="standalone-heading">
    <h4>{{ mawa_e('notifications_page.templates') }}</h4>
    <p>{{ mawa_e('notifications_page.description') }}</p>
</div>

<ul class="nav nav-tabs mb-3">
    <li class="nav-item"><a class="nav-link" href="{{ route('settings.notifications.index') }}">{{ mawa_e('notifications_page.overview') }}</a></li>
    <li class="nav-item"><a class="nav-link active" href="{{ route('settings.notifications.templates.index') }}">{{ mawa_e('notifications_page.templates') }}</a></li>
    <li class="nav-item"><a class="nav-link" href="{{ route('settings.notifications.logs.index') }}">{{ mawa_e('notifications_page.logs') }}</a></li>
</ul>

<div class="admin-card">
    <div class="table-toolbar">
        <div class="toolbar-info"><i class="bi bi-file-earmark-text"></i> {{ mawa_e('notifications_page.templates') }}</div>
        <a class="btn btn-primary btn-sm" href="{{ route('settings.notifications.templates.create') }}"><i class="bi bi-plus-lg"></i> {{ mawa_e('notifications_page.new_template') }}</a>
    </div>

    <form method="GET" action="{{ route('settings.notifications.templates.index') }}" class="row g-2 mb-3">
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
            <select name="language" class="form-select form-select-sm">
                <option value="">{{ mawa_e('notifications_page.filter_language') }}</option>
                <option value="en" @selected($filters['language'] === 'en')>English</option>
                <option value="bn" @selected($filters['language'] === 'bn')>বাংলা</option>
            </select>
        </div>
        <div class="col-md-2">
            <button class="btn btn-outline-primary btn-sm" type="submit">{{ mawa_e('notifications_page.apply') }}</button>
            <a class="btn btn-outline-secondary btn-sm" href="{{ route('settings.notifications.templates.index') }}">{{ mawa_e('notifications_page.reset') }}</a>
        </div>
    </form>

    @if ($templates->isEmpty())
        <div class="text-muted text-center py-4">{{ mawa_e('notifications_page.no_templates') }}</div>
    @else
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th>{{ mawa_e('notifications_page.event') }}</th>
                    <th>{{ mawa_e('notifications_page.channel') }}</th>
                    <th>{{ mawa_e('notifications_page.language') }}</th>
                    <th>{{ mawa_e('notifications_page.template_name') }}</th>
                    <th>{{ mawa_e('notifications_page.status') }}</th>
                    <th class="text-end">{{ mawa_e('notifications_page.actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($templates as $template)
                    <tr>
                        <td>{{ $events[$template->event]['label'] ?? $template->event }} <span class="text-muted small">({{ $template->event }})</span></td>
                        <td>{{ mawa_e('notifications_page.' . $template->channel) }}</td>
                        <td>{{ $template->language === 'bn' ? 'বাংলা' : 'English' }}</td>
                        <td>{{ $template->name }}</td>
                        <td>
                            @if ($template->institute_id === $instituteId)
                                <span class="badge bg-primary">{{ mawa_e('notifications_page.override') }}</span>
                            @else
                                <span class="badge bg-secondary">{{ mawa_e('notifications_page.global') }}</span>
                            @endif
                            @if (! $template->is_active)
                                <span class="badge bg-danger">{{ mawa_e('notifications_page.inactive') }}</span>
                            @endif
                        </td>
                        <td class="text-end">
                            @if ($template->institute_id === $instituteId)
                                <a class="btn btn-sm btn-outline-primary" href="{{ route('settings.notifications.templates.edit', $template) }}"><i class="bi bi-pencil"></i></a>
                                <form class="d-inline" method="POST" action="{{ route('settings.notifications.templates.toggle', $template) }}">
                                    @csrf
                                    <button class="btn btn-sm btn-outline-secondary" type="submit" title="{{ mawa_e('notifications_page.toggle') }}"><i class="bi bi-power"></i></button>
                                </form>
                                <form class="d-inline" method="POST" action="{{ route('settings.notifications.templates.destroy', $template) }}" onsubmit="return confirm('Delete this template?')">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger" type="submit"><i class="bi bi-trash"></i></button>
                                </form>
                            @else
                                <span class="badge bg-light text-muted">{{ mawa_e('notifications_page.read_only') }}</span>
                                <a class="btn btn-sm btn-outline-secondary" href="{{ route('settings.notifications.templates.create', ['event' => $template->event, 'channel' => $template->channel, 'language' => $template->language]) }}">{{ mawa_e('notifications_page.copy') }}</a>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif
</div>

@endsection
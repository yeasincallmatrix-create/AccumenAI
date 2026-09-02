@extends('layouts.standalone')

@php
    $backUrl = $template->exists
        ? route('settings.notifications.templates.index')
        : route('settings.notifications.templates.index');
    $selectedEvent = old('event', request('event', $template->event));
    $selectedChannel = old('channel', request('channel', $template->channel));
    $selectedLanguage = old('language', request('language', $template->language));
    $eventVariables = isset($events[$selectedEvent]['variables']) ? $events[$selectedEvent]['variables'] : [];
    $selectedVariables = old('variables', $template->variables ?? []);
@endphp

@section('title', mawa_e('notifications_page.templates') . ' — AccumenAI')
@section('page_title', mawa_e('notifications_page.' . ($template->exists ? 'edit_template' : 'new_template')))

@section('content')

<div class="standalone-heading">
    <h4>{{ mawa_e('notifications_page.' . ($template->exists ? 'edit_template' : 'new_template')) }}</h4>
    <p>{{ mawa_e('notifications_page.description') }}</p>
</div>

<div class="admin-card">
    <form method="POST" action="{{ $template->exists ? route('settings.notifications.templates.update', $template) : route('settings.notifications.templates.store') }}">
        @csrf
        @if ($template->exists)
            @method('PUT')
        @endif

        <div class="row g-3 mb-3">
            <div class="col-md-4">
                <label class="form-label">{{ mawa_e('notifications_page.event') }}</label>
                <select name="event" class="form-select">
                    @foreach ($events as $event => $eventConfig)
                        <option value="{{ $event }}" @selected($selectedEvent === $event)>{{ $eventConfig['label'] ?? $event }}</option>
                    @endforeach
                </select>
                @error('event')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4">
                <label class="form-label">{{ mawa_e('notifications_page.channel') }}</label>
                <select name="channel" class="form-select">
                    @foreach (['in_app' => 'in_app', 'email' => 'email', 'sms' => 'sms'] as $value => $label)
                        <option value="{{ $value }}" @selected($selectedChannel === $value)>{{ mawa_e('notifications_page.' . $label) }}</option>
                    @endforeach
                </select>
                @error('channel')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4">
                <label class="form-label">{{ mawa_e('notifications_page.language') }}</label>
                <select name="language" class="form-select">
                    <option value="en" @selected($selectedLanguage === 'en')>English</option>
                    <option value="bn" @selected($selectedLanguage === 'bn')>বাংলা</option>
                </select>
                @error('language')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
            </div>
        </div>

        <div class="mb-3">
            <label class="form-label">{{ mawa_e('notifications_page.template_name') }}</label>
            <input type="text" name="name" class="form-control" value="{{ old('name', $template->name) }}" maxlength="190">
            @error('name')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
        </div>

        <div class="mb-3">
            <label class="form-label">{{ mawa_e('notifications_page.subject') }}</label>
            <input type="text" name="subject" class="form-control" value="{{ old('subject', $template->subject) }}" maxlength="190">
            @error('subject')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
        </div>

        <div class="mb-3">
            <label class="form-label">{{ mawa_e('notifications_page.body') }}</label>
            <textarea name="body" class="form-control" rows="6">{{ old('body', $template->body) }}</textarea>
            @error('body')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
        </div>

        <div class="mb-3">
            <label class="form-label">{{ mawa_e('notifications_page.variables') }}</label>
            <div class="small text-muted mb-2">{{ mawa_e('notifications_page.variables_hint') }}</div>
            @if ($eventVariables !== [])
                <div class="row g-2">
                    @foreach ($eventVariables as $variable)
                        <div class="col-6 col-md-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="variables[]" value="{{ $variable }}" id="var_{{ $variable }}" @checked(in_array($variable, (array) $selectedVariables, true))>
                                <label class="form-check-label font-monospace small" for="var_{{ $variable }}">@{{ {{ $variable }} }}</label>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="text-muted small">—</div>
            @endif
        </div>

        <div class="mb-4">
            <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" name="is_active" value="1" id="is_active" @checked($template->is_active !== false)>
                <label class="form-check-label" for="is_active">{{ mawa_e('notifications_page.is_active') }}</label>
            </div>
        </div>

        <button class="btn btn-primary" type="submit"><i class="bi bi-check-lg"></i> {{ mawa_e('notifications_page.save_template') }}</button>
        <a class="btn btn-outline-secondary" href="{{ route('settings.notifications.templates.index') }}">{{ mawa_e('notifications_page.cancel') }}</a>
    </form>
</div>

@endsection
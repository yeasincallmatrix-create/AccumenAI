@extends('layouts.standalone')

@php $backUrl = route('settings.notifications.logs.index'); @endphp

@section('title', mawa_e('notifications_page.details') . ' — AccumenAI')
@section('page_title', mawa_e('notifications_page.details'))

@section('content')

<div class="standalone-heading">
    <h4>{{ mawa_e('notifications_page.details') }}</h4>
    <p>{{ $log->event }} · {{ mawa_e('notifications_page.' . $log->channel) }}</p>
</div>

<div class="admin-card">
    <dl class="row mb-3">
        <dt class="col-sm-3">{{ mawa_e('notifications_page.status') }}</dt>
        <dd class="col-sm-9"><span class="badge bg-secondary">{{ $log->status }}</span></dd>

        <dt class="col-sm-3">{{ mawa_e('notifications_page.event') }}</dt>
        <dd class="col-sm-9">{{ $log->event }}</dd>

        <dt class="col-sm-3">{{ mawa_e('notifications_page.channel') }}</dt>
        <dd class="col-sm-9">{{ mawa_e('notifications_page.' . $log->channel) }}</dd>

        <dt class="col-sm-3">{{ mawa_e('notifications_page.recipient') }}</dt>
        <dd class="col-sm-9">{{ $log->recipient_type }}#{{ $log->recipient_id ?? '—' }}</dd>

        <dt class="col-sm-3">{{ mawa_e('notifications_page.contact') }}</dt>
        <dd class="col-sm-9">{{ $log->recipient_contact }}</dd>

        <dt class="col-sm-3">{{ mawa_e('notifications_page.retry_count') }}</dt>
        <dd class="col-sm-9">{{ $log->retry_count }} / {{ $log->max_retries }}</dd>

        <dt class="col-sm-3">{{ mawa_e('notifications_page.subject') }}</dt>
        <dd class="col-sm-9">{{ $log->subject }}</dd>

        <dt class="col-sm-3">{{ mawa_e('notifications_page.body') }}</dt>
        <dd class="col-sm-9"><pre class="mb-0" style="white-space:pre-wrap">{{ $log->body }}</pre></dd>

        <dt class="col-sm-3">{{ mawa_e('notifications_page.provider') }}</dt>
        <dd class="col-sm-9">{{ $log->provider ?? '—' }}</dd>

        <dt class="col-sm-3">{{ mawa_e('notifications_page.provider_message_id') }}</dt>
        <dd class="col-sm-9">{{ $log->provider_message_id ?? '—' }}</dd>

        <dt class="col-sm-3">{{ mawa_e('notifications_page.provider_response') }}</dt>
        <dd class="col-sm-9"><pre class="mb-0 small" style="white-space:pre-wrap">{{ $log->provider_response ?? '—' }}</pre></dd>

        <dt class="col-sm-3">{{ mawa_e('notifications_page.error') }}</dt>
        <dd class="col-sm-9 text-danger">{{ $log->error ?? '—' }}</dd>

        <dt class="col-sm-3">{{ mawa_e('notifications_page.created_at') }}</dt>
        <dd class="col-sm-9">{{ optional($log->created_at)->format('Y-m-d H:i:s') }}</dd>

        <dt class="col-sm-3">{{ mawa_e('notifications_page.sent_at') }}</dt>
        <dd class="col-sm-9">{{ optional($log->sent_at)->format('Y-m-d H:i:s') ?: '—' }}</dd>

        <dt class="col-sm-3">{{ mawa_e('notifications_page.failed_at') }}</dt>
        <dd class="col-sm-9">{{ optional($log->failed_at)->format('Y-m-d H:i:s') ?: '—' }}</dd>

        <dt class="col-sm-3">{{ mawa_e('notifications_page.metadata') }}</dt>
        <dd class="col-sm-9"><pre class="mb-0 small" style="white-space:pre-wrap">{{ json_encode($log->metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre></dd>
    </dl>

    <div class="d-flex gap-2">
        <a class="btn btn-outline-secondary" href="{{ route('settings.notifications.logs.index') }}"><i class="bi bi-arrow-left"></i> {{ mawa_e('notifications_page.back') }}</a>
        @if ($log->status === 'failed' && $log->retry_count < $log->max_retries)
            <form method="POST" action="{{ route('settings.notifications.logs.retry', $log) }}">
                @csrf
                <button class="btn btn-outline-warning" type="submit"><i class="bi bi-arrow-clockwise"></i> {{ mawa_e('notifications_page.retry') }}</button>
            </form>
        @endif
    </div>
</div>

@endsection
@extends('layouts.standalone')

@section('title', 'Template ' . $template->template_number . ' — AccumenAI')
@section('page_title', 'Finance')

@section('content')

<div class="standalone-heading">
    <h4>Template {{ $template->template_number }}</h4>
    <p>
        {{ $template->name }} · <span class="badge text-bg-{{ $template->statusColor() }}">{{ $template->status }}</span>
        · <span class="badge text-bg-light border">{{ str_replace('_', ' ', $template->transaction_type) }}</span>
    </p>
    <div class="d-flex gap-2 flex-wrap">
        @if($template->isActive() || $template->isPaused())
            <form method="POST" action="{{ route('finance.recurring-templates.generate-now', $template) }}" class="d-inline">
                @csrf
                <button class="btn btn-success btn-sm" type="submit"><i class="bi bi-play me-1"></i>Generate Now</button>
            </form>
        @endif
        @if($template->isActive())
            <form method="POST" action="{{ route('finance.recurring-templates.pause', $template) }}" class="d-inline">
                @csrf
                <button class="btn btn-warning btn-sm" type="submit"><i class="bi bi-pause me-1"></i>Pause</button>
            </form>
        @endif
        @if($template->isPaused())
            <form method="POST" action="{{ route('finance.recurring-templates.resume', $template) }}" class="d-inline">
                @csrf
                <button class="btn btn-primary btn-sm" type="submit"><i class="bi bi-play-circle me-1"></i>Resume</button>
            </form>
        @endif
        @if($template->isActive() || $template->isPaused())
            <form method="POST" action="{{ route('finance.recurring-templates.cancel', $template) }}" class="d-inline" data-confirm="Cancel this template?">
                @csrf
                <button class="btn btn-outline-danger btn-sm" type="submit"><i class="bi bi-x-lg me-1"></i>Cancel</button>
            </form>
        @endif
        @if($template->isActive() || $template->isPaused())
            <a href="{{ route('finance.recurring-templates.edit', $template) }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-pencil me-1"></i>Edit</a>
        @endif
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-3">
        <div class="admin-card text-center">
            <small class="text-muted text-uppercase">Frequency</small>
            <h6 class="mb-0 mt-1">{{ ucfirst($template->frequency) }} @if($template->interval_count > 1)(every {{ $template->interval_count }})@endif</h6>
        </div>
    </div>
    <div class="col-md-3">
        <div class="admin-card text-center">
            <small class="text-muted text-uppercase">Next Run</small>
            <h6 class="mb-0 mt-1">{{ $template->next_run_at?->format('d M Y H:i') ?? '—' }}</h6>
        </div>
    </div>
    <div class="col-md-3">
        <div class="admin-card text-center">
            <small class="text-muted text-uppercase">Last Generated</small>
            <h6 class="mb-0 mt-1">{{ $template->last_generated_at?->format('d M Y H:i') ?? 'Never' }}</h6>
        </div>
    </div>
    <div class="col-md-3">
        <div class="admin-card text-center">
            <small class="text-muted text-uppercase">Occurrences</small>
            <h6 class="mb-0 mt-1">{{ $template->occurrences_generated }}@if($template->max_occurrences) / {{ $template->max_occurrences }}@endif</h6>
        </div>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-lg-7">
        <div class="admin-card mb-3">
            <h6 class="card-title">Template Details</h6>
            <table class="table table-sm mb-0">
                <tbody>
                    <tr><td class="text-muted" style="width:180px">Template #</td><td>{{ $template->template_number }}</td></tr>
                    <tr><td class="text-muted">Transaction Type</td><td>{{ str_replace('_', ' ', ucfirst($template->transaction_type)) }}</td></tr>
                    <tr><td class="text-muted">Start Date</td><td>{{ $template->start_date?->format('d M Y') ?? '—' }}</td></tr>
                    <tr><td class="text-muted">End Date</td><td>{{ $template->end_date?->format('d M Y') ?? 'No end' }}</td></tr>
                    <tr><td class="text-muted">Auto-post</td><td>{{ $template->auto_post ? 'Yes' : 'No' }}</td></tr>
                    @if($template->custom_cron)
                        <tr><td class="text-muted">Custom Cron</td><td><code>{{ $template->custom_cron }}</code></td></tr>
                    @endif
                    @if($template->consecutive_failures > 0)
                        <tr><td class="text-muted">Consecutive Failures</td><td class="text-danger">{{ $template->consecutive_failures }}</td></tr>
                    @endif
                    @if($template->last_error)
                        <tr><td class="text-muted">Last Error</td><td class="text-danger small">{{ Str::limit($template->last_error, 200) }}</td></tr>
                    @endif
                    @if($template->notes)
                        <tr><td class="text-muted">Notes</td><td>{!! nl2br(e($template->notes)) !!}</td></tr>
                    @endif
                </tbody>
            </table>
        </div>

        <div class="admin-card mb-3">
            <h6 class="card-title">Template Data</h6>
            <pre class="bg-light p-3 rounded mb-0 small" style="max-height:300px;overflow:auto">{{ json_encode($template->template_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="admin-card mb-3">
            <h6 class="card-title">Preview (Next 5 Occurrences)</h6>
            @if(count($preview) > 0)
                <table class="table table-sm mb-0">
                    <thead><tr><th>#</th><th>Scheduled Date</th></tr></thead>
                    <tbody>
                        @foreach($preview as $i => $date)
                            <tr>
                                <td>{{ $template->occurrences_generated + $i + 1 }}</td>
                                <td>{{ $date->format('d M Y H:i') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <p class="text-muted mb-0">No upcoming occurrences.</p>
            @endif
        </div>

        <div class="admin-card">
            <h6 class="card-title">Generation History (Last 20)</h6>
            @forelse($template->generations as $gen)
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <span>
                        <span class="badge text-bg-{{ $gen->status === 'success' ? 'success' : ($gen->status === 'failed' ? 'danger' : 'secondary') }}">{{ $gen->status }}</span>
                        {{ $gen->scheduled_for?->format('d M Y') ?? '—' }}
                    </span>
                    <span class="small text-muted">
                        @if($gen->generated_type)
                            {{ class_basename($gen->generated_type) }} #{{ $gen->generated_id }}
                        @endif
                        @if($gen->generated_at)
                            · {{ $gen->generated_at->format('H:i') }}
                        @endif
                    </span>
                </div>
                @if($gen->error_message)
                    <div class="small text-danger mb-1">{{ Str::limit($gen->error_message, 100) }}</div>
                @endif
            @empty
                <p class="text-muted mb-0">No generations yet.</p>
            @endforelse
        </div>
    </div>
</div>

@endsection

@extends('layouts.institute')

@section('title', $analyzer->name . ' — AccumenAI')

@section('content')
<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div class="page-header-text">
        <h4 class="page-header-title">{{ $analyzer->name }} <small class="text-muted">{{ $analyzer->code }}</small></h4>
        @include('medical.lab.analyzers._status_badge', ['status' => $analyzer->status])
    </div>
    <div class="page-header-actions">
        <a class="btn btn-secondary" href="{{ route('medical.laboratory.analyzers.index') }}">Back</a>
        <a class="btn btn-warning" href="{{ route('medical.laboratory.analyzers.edit', $analyzer) }}">
            <i class="bi bi-pencil me-1"></i>Edit
        </a>
    </div>
</div>

@if(session('plain_token'))
<div class="alert alert-warning">
    <h6><i class="bi bi-key me-1"></i>Device token (shown once only)</h6>
    <div class="input-group">
        <input type="text" class="form-control font-monospace" value="{{ session('plain_token') }}" readonly onclick="this.select()">
        <button class="btn btn-outline-secondary" type="button" onclick="navigator.clipboard.writeText(this.previousElementSibling.value)">Copy</button>
    </div>
    <small>Store this in the gateway <code>.env</code> as <code>DEVICE_TOKEN</code>. It will never be shown again.</small>
</div>
@endif

<ul class="nav nav-tabs mb-3" role="tablist">
    <li class="nav-item"><a class="nav-link active" href="#overview">Overview</a></li>
    <li class="nav-item"><a class="nav-link" href="#credential">Credential</a></li>
    <li class="nav-item"><a class="nav-link" href="{{ route('medical.laboratory.analyzers.maps.index', $analyzer) }}">Parameter Maps</a></li>
    <li class="nav-item"><a class="nav-link" href="{{ route('medical.laboratory.analyzers.messages.index', $analyzer) }}">Messages</a></li>
    <li class="nav-item"><a class="nav-link" href="{{ route('medical.laboratory.analyzers.worklist.index', $analyzer) }}">Worklist</a></li>
</ul>

<div id="overview" class="row mb-3">
    <div class="col-md-4">
        <div class="card mb-3">
            <div class="card-header"><h6 class="mb-0">Device</h6></div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-5">Manufacturer</dt><dd class="col-7">{{ $analyzer->manufacturer ?? '—' }}</dd>
                    <dt class="col-5">Model</dt><dd class="col-7">{{ $analyzer->model ?? '—' }}</dd>
                    <dt class="col-5">Serial</dt><dd class="col-7">{{ $analyzer->serial_no ?? '—' }}</dd>
                    <dt class="col-5">Type</dt><dd class="col-7">{{ ucfirst($analyzer->instrument_type) }}</dd>
                    <dt class="col-5">Protocol</dt><dd class="col-7">{{ strtoupper($analyzer->protocol) }}</dd>
                    <dt class="col-5">Adapter</dt><dd class="col-7"><code>{{ $analyzer->adapter_key }}:{{ $analyzer->adapter_version }}</code></dd>
                </dl>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card mb-3">
            <div class="card-header"><h6 class="mb-0">Connection</h6></div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-5">Type</dt><dd class="col-7">{{ strtoupper($analyzer->connection_type) }}</dd>
                    <dt class="col-5">Host</dt><dd class="col-7">{{ $analyzer->host ?? '—' }}</dd>
                    <dt class="col-5">Port</dt><dd class="col-7">{{ $analyzer->port ?? '—' }}</dd>
                    <dt class="col-5">Serial</dt><dd class="col-7">{{ $analyzer->serial_port ?? '—' }}</dd>
                    <dt class="col-5">Last Seen</dt><dd class="col-7">{{ $analyzer->last_seen_at ? $analyzer->last_seen_at->diffForHumans() : 'Never' }}</dd>
                    <dt class="col-5">Last Message</dt><dd class="col-7">{{ $analyzer->last_message_at ? $analyzer->last_message_at->diffForHumans() : 'Never' }}</dd>
                </dl>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card mb-3">
            <div class="card-header"><h6 class="mb-0">Stats</h6></div>
            <div class="card-body">
                <h2>{{ $stats['total_messages'] }} <small class="text-muted fs-6">messages</small></h2>
                <p class="mb-1">Pending: <strong>{{ $stats['pending_messages'] }}</strong></p>
                <p class="mb-0">Failed: <strong class="text-danger">{{ $stats['failed_messages'] }}</strong></p>
                <hr>
                <h6>Capabilities</h6>
                @foreach(['result_upload' => 'Upload', 'worklist' => 'Worklist', 'query' => 'Query', 'bidirectional' => 'Bidirectional'] as $cap => $label)
                    <span class="badge bg-{{ ($analyzer->capabilities[$cap] ?? false) ? 'success' : 'secondary' }} me-1">{{ $label }}</span>
                @endforeach
            </div>
        </div>
    </div>
</div>

<div id="credential" class="card mb-3">
    <div class="card-header"><h6 class="mb-0">Device Credential</h6></div>
    <div class="card-body">
        @if($analyzer->credential)
            <dl class="row">
                <dt class="col-md-3">Token Prefix</dt><dd class="col-md-9"><code>{{ $analyzer->credential->token_prefix }}••••••••</code> <span class="text-muted">(masked)</span></dd>
                <dt class="col-md-3">Name</dt><dd class="col-md-9">{{ $analyzer->credential->name ?? '—' }}</dd>
                <dt class="col-md-3">Abilities</dt><dd class="col-md-9">{{ implode(', ', $analyzer->credential->abilities ?? []) }}</dd>
                <dt class="col-md-3">Last Used</dt><dd class="col-md-9">{{ $analyzer->credential->last_used_at ? $analyzer->credential->last_used_at->diffForHumans() : 'Never' }}</dd>
                <dt class="col-md-3">Status</dt>
                <dd class="col-md-9">
                    @if($analyzer->credential->revoked_at)
                        <span class="badge bg-danger">Revoked</span>
                    @else
                        <span class="badge bg-success">Active</span>
                    @endif
                </dd>
            </dl>
            <form method="POST" action="{{ route('medical.laboratory.analyzers.credentials.rotate', $analyzer) }}" class="d-inline" onsubmit="return confirm('Rotate credential? Update the gateway .env afterwards.');">
                @csrf
                <button type="submit" class="btn btn-warning btn-sm">Rotate</button>
            </form>
            <form method="POST" action="{{ route('medical.laboratory.analyzers.credentials.revoke', $analyzer) }}" class="d-inline" onsubmit="return confirm('Revoke credential? The gateway will stop working.');">
                @csrf
                <button type="submit" class="btn btn-outline-danger btn-sm">Revoke</button>
            </form>
        @else
            <p class="text-muted">No credential issued yet.</p>
            <form method="POST" action="{{ route('medical.laboratory.analyzers.credentials.issue', $analyzer) }}">
                @csrf
                <button type="submit" class="btn btn-primary btn-sm">Issue Credential</button>
            </form>
        @endif
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h6 class="mb-0">Recent Messages</h6>
        <a href="{{ route('medical.laboratory.analyzers.messages.index', $analyzer) }}" class="btn btn-sm btn-link">View all</a>
    </div>
    <div class="card-body">
        @if($recentMessages->count() > 0)
            <ul class="list-unstyled mb-0">
                @foreach($recentMessages as $message)
                <li class="border-bottom py-2">
                    <a href="{{ route('medical.laboratory.analyzers.messages.show', [$analyzer, $message]) }}"><strong>#{{ $message->id }}</strong></a>
                    <span class="text-muted">· {{ $message->accession_number ?? 'no accession' }}</span>
                    <span class="badge bg-{{ $message->status === 'stored' ? 'success' : (in_array($message->status, ['error', 'dead']) ? 'danger' : 'secondary') }} float-end">{{ $message->status }}</span>
                </li>
                @endforeach
            </ul>
        @else
            <p class="text-muted mb-0">No messages yet.</p>
        @endif
    </div>
</div>
@endsection

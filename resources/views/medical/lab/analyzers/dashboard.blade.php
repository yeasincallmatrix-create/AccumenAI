@extends('layouts.institute')

@section('title', 'Analyzer Dashboard — AccumenAI')

@section('content')
<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div class="page-header-text">
        <h4 class="page-header-title">Analyzer Status</h4>
    </div>
    <div class="page-header-actions">
        <a class="btn btn-primary" href="{{ route('medical.laboratory.analyzers.index') }}">
            <i class="bi bi-robot me-1"></i>Manage Analyzers
        </a>
    </div>
</div>

<div class="row mb-3">
    <div class="col-md-3">
        <div class="card bg-primary text-white"><div class="card-body">
            <h6 class="card-title">Analyzers</h6><h2 class="card-text">{{ $totals['analyzers'] }}</h2>
        </div></div>
    </div>
    <div class="col-md-3">
        <div class="card bg-success text-white"><div class="card-body">
            <h6 class="card-title">Online</h6><h2 class="card-text">{{ $totals['online'] }}</h2>
        </div></div>
    </div>
    <div class="col-md-3">
        <div class="card bg-warning text-dark"><div class="card-body">
            <h6 class="card-title">Pending Messages</h6><h2 class="card-text">{{ $totals['pending'] }}</h2>
        </div></div>
    </div>
    <div class="col-md-3">
        <div class="card bg-danger text-white"><div class="card-body">
            <h6 class="card-title">Failed Messages</h6><h2 class="card-text">{{ $totals['failed'] }}</h2>
        </div></div>
    </div>
</div>

<div class="row">
    @forelse($cards as $card)
    <div class="col-md-4 mb-3">
        <div class="card h-100">
            <div class="card-body">
                <h6 class="card-title">
                    <span class="badge rounded-pill bg-{{ $card['online'] ? 'success' : 'danger' }} me-1">&nbsp;</span>
                    {{ $card['analyzer']->name }}
                </h6>
                <p class="text-muted small mb-2">{{ $card['analyzer']->code }} · {{ strtoupper($card['analyzer']->protocol) }}</p>
                <p class="mb-1">Last seen: <strong>{{ $card['analyzer']->last_seen_at ? $card['analyzer']->last_seen_at->diffForHumans() : 'Never' }}</strong></p>
                <p class="mb-1">Messages (24h): <strong>{{ $card['messages_24h'] }}</strong></p>
                <p class="mb-3">Failed (24h): <strong class="{{ $card['failed_24h'] > 0 ? 'text-danger' : '' }}">{{ $card['failed_24h'] }}</strong></p>
                <a href="{{ route('medical.laboratory.analyzers.show', $card['analyzer']) }}" class="btn btn-sm btn-info">View</a>
                @if($card['last_failed_id'])
                    <a href="{{ route('medical.laboratory.analyzers.messages.show', [$card['analyzer'], $card['last_failed_id']]) }}" class="btn btn-sm btn-warning">Last Failed</a>
                @endif
            </div>
        </div>
    </div>
    @empty
    <div class="col-12">
        <div class="card"><div class="card-body text-center text-muted py-4">
            <i class="bi bi-robot fs-2 d-block mb-2"></i>
            No analyzers registered.
        </div></div>
    </div>
    @endforelse
</div>

<script>
// v1 polling: reload every 30s for near-live status (no extra endpoint needed).
setTimeout(function () { window.location.reload(); }, 30000);
</script>
@endsection

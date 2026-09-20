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

<div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h6 class="mb-0">Live Feed <small class="text-muted">(latest stored results)</small></h6>
        <span class="badge bg-secondary" id="live-feed-status">polling</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead><tr><th>Message</th><th>Accession</th><th>Analyzer</th><th>Received</th></tr></thead>
                <tbody id="live-lab-feed">
                    <tr><td colspan="4" class="text-muted text-center py-3">Waiting for new results…</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
// v1: reload every 30s for near-live status when Echo/Reverb is absent.
window.__labPollTimer = setTimeout(function () { window.location.reload(); }, 30000);
</script>

@push('scripts')
<script src="https://js.pusher.com/8.2.0/pusher.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    if (!window.Echo) {
        // Echo not loaded — polling fallback stays armed (see above).
        return;
    }

    clearTimeout(window.__labPollTimer);
    document.getElementById('live-feed-status').textContent = 'live';
    document.getElementById('live-feed-status').className = 'badge bg-success';

    const instituteId = {{ (int) (\App\Support\Workspace::id() ?? \App\Support\TenantContext::id() ?? 0) }};
    const channel = window.Echo.private(`institute.${instituteId}.lab-analyzer`);

    channel.listen('.result.stored', (data) => {
        const feed = document.getElementById('live-lab-feed');
        if (feed) {
            if (feed.querySelector('td[colspan]')) { feed.innerHTML = ''; }
            const row = document.createElement('tr');
            row.className = 'table-success';
            row.innerHTML = `
                <td>${data.message_id}</td>
                <td>${data.accession_number ?? '—'}</td>
                <td>${data.analyzer_id}</td>
                <td>Just now</td>
            `;
            feed.prepend(row);
        }

        if (window.showToast) {
            window.showToast(`New result: ${data.accession_number ?? 'msg ' + data.message_id}`);
        }
    });
});
</script>
@endpush
@endsection

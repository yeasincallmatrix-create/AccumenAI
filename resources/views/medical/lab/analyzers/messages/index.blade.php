@extends('layouts.institute')

@section('title', 'Analyzer Messages — AccumenAI')

@section('content')
<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div class="page-header-text">
        <h4 class="page-header-title">Messages <small class="text-muted">{{ $analyzer->code }}</small></h4>
    </div>
    <div class="page-header-actions">
        <a class="btn btn-secondary" href="{{ route('medical.laboratory.analyzers.show', $analyzer) }}">Back</a>
    </div>
</div>

<div class="row mb-3">
    <div class="col-md-2 col-6 mb-2">
        <div class="card bg-warning text-dark"><div class="card-body py-2">
            <h6 class="card-title mb-0 small">Pending</h6><h4 class="card-text mb-0">{{ $summary['pending'] ?? 0 }}</h4>
        </div></div>
    </div>
    <div class="col-md-2 col-6 mb-2">
        <div class="card bg-danger text-white"><div class="card-body py-2">
            <h6 class="card-title mb-0 small">Failed</h6><h4 class="card-text mb-0">{{ $summary['failed'] ?? 0 }}</h4>
        </div></div>
    </div>
    <div class="col-md-2 col-6 mb-2">
        <div class="card bg-dark text-white"><div class="card-body py-2">
            <h6 class="card-title mb-0 small">Dead</h6><h4 class="card-text mb-0">{{ $summary['dead'] ?? 0 }}</h4>
        </div></div>
    </div>
    <div class="col-md-3 col-6 mb-2">
        <div class="card bg-info text-dark"><div class="card-body py-2">
            <h6 class="card-title mb-0 small">Unresolved</h6><h4 class="card-text mb-0">{{ $summary['unresolved'] ?? 0 }}</h4>
        </div></div>
    </div>
    <div class="col-md-3 col-6 mb-2">
        <div class="card bg-success text-white"><div class="card-body py-2">
            <h6 class="card-title mb-0 small">Resolved</h6><h4 class="card-text mb-0">{{ $summary['resolved'] ?? 0 }}</h4>
        </div></div>
    </div>
</div>

<div class="mb-2 d-flex gap-2 flex-wrap">
    <a href="{{ route('medical.laboratory.analyzers.messages.index', $analyzer) }}" class="btn btn-sm {{ !request('status') && !request('resolution') ? 'btn-primary' : 'btn-outline-primary' }}">All</a>
    <a href="{{ route('medical.laboratory.analyzers.messages.index', [$analyzer, 'status' => 'received']) }}" class="btn btn-sm {{ request('status') === 'received' ? 'btn-primary' : 'btn-outline-primary' }}">Pending</a>
    <a href="{{ route('medical.laboratory.analyzers.messages.index', [$analyzer, 'status' => 'error']) }}" class="btn btn-sm {{ request('status') === 'error' ? 'btn-primary' : 'btn-outline-primary' }}">Failed</a>
    <a href="{{ route('medical.laboratory.analyzers.messages.index', [$analyzer, 'status' => 'dead']) }}" class="btn btn-sm {{ request('status') === 'dead' ? 'btn-primary' : 'btn-outline-primary' }}">Dead</a>
    <a href="{{ route('medical.laboratory.analyzers.messages.index', [$analyzer, 'resolution' => 'unresolved']) }}" class="btn btn-sm {{ request('resolution') === 'unresolved' ? 'btn-primary' : 'btn-outline-primary' }}">Unresolved</a>
</div>

<div class="card">
    <div class="card-body">
        <form method="GET" class="mb-3">
            <div class="row g-2">
                <div class="col-md-2">
                    <select name="status" class="form-select">
                        <option value="">All Status</option>
                        @foreach(['received', 'parsed', 'stored', 'error', 'dead', 'duplicate'] as $status)
                            <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="resolution" class="form-select">
                        <option value="">All Resolutions</option>
                        @foreach(['unresolved' => 'Unresolved', 'retried' => 'Retried', 'resolved_manual' => 'Resolved', 'discarded' => 'Discarded', 'escalated' => 'Escalated'] as $value => $label)
                            <option value="{{ $value }}" @selected(request('resolution') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <input type="text" name="accession_number" value="{{ request('accession_number') }}" class="form-control" placeholder="Accession...">
                </div>
                <div class="col-md-3">
                    <input type="text" name="message_id" value="{{ request('message_id') }}" class="form-control" placeholder="Message ID...">
                </div>
                <div class="col-md-2">
                    <input type="date" name="from_date" value="{{ request('from_date') }}" class="form-control">
                </div>
                <div class="col-md-2 text-end">
                    <button type="submit" class="btn btn-primary">Filter</button>
                    <a href="{{ route('medical.laboratory.analyzers.messages.index', $analyzer) }}" class="btn btn-secondary">Reset</a>
                </div>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr><th></th><th>ID</th><th>Received</th><th>Status</th><th>Resolution</th><th>Message ID</th><th>Accession</th><th>Error</th><th class="text-end">Actions</th></tr>
                </thead>
                <tbody>
                    @forelse($messages as $message)
                    <tr>
                        <td>
                            @if(in_array($message->status, ['error', 'dead']))
                                <input type="checkbox" class="form-check-input bulk-check" value="{{ $message->id }}">
                            @endif
                        </td>
                        <td><strong>#{{ $message->id }}</strong></td>
                        <td>{{ $message->received_at ? $message->received_at->format('d M H:i') : '—' }}</td>
                        <td><span class="badge bg-{{ $message->status === 'stored' ? 'success' : (in_array($message->status, ['error', 'dead']) ? 'danger' : 'secondary') }}">{{ $message->status }}</span></td>
                        <td>{{ $message->resolution_status ?? (in_array($message->status, ['error', 'dead']) ? 'unresolved' : '—') }}</td>
                        <td>{{ $message->message_id ?? '—' }}</td>
                        <td>{{ $message->accession_number ?? '—' }}</td>
                        <td class="text-danger small">{{ $message->error_code ?? '—' }}</td>
                        <td class="text-end">
                            <div class="btn-group btn-group-sm">
                                <a href="{{ route('medical.laboratory.analyzers.messages.show', [$analyzer, $message]) }}" class="btn btn-info" title="View">
                                    <i class="bi bi-eye"></i>
                                </a>
                                @if(in_array($message->status, ['error', 'dead']))
                                    <form method="POST" action="{{ route('medical.laboratory.analyzers.messages.retry', [$analyzer, $message]) }}" onsubmit="return confirm('Re-queue message #{{ $message->id }}?');">
                                        @csrf
                                        <button type="submit" class="btn btn-warning" title="Retry"><i class="bi bi-arrow-repeat"></i></button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="9" class="text-center text-muted py-4">
                            <i class="bi bi-inbox fs-2 d-block mb-2"></i>
                            No messages found.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="d-flex gap-2 mt-2">
            <button type="button" class="btn btn-warning btn-sm" id="bulk-retry-btn">Retry Selected</button>
            <button type="button" class="btn btn-outline-danger btn-sm" id="bulk-discard-btn">Discard Selected</button>
        </div>

        <form id="bulk-action-form" method="POST" class="d-none">
            @csrf
            <div id="bulk-ids"></div>
        </form>

        <script>
        function collectBulkIds() {
            return Array.from(document.querySelectorAll('.bulk-check:checked')).map(function (c) { return c.value; });
        }
        document.getElementById('bulk-retry-btn').addEventListener('click', function () {
            var ids = collectBulkIds();
            if (ids.length === 0) { alert('Select at least one message.'); return; }
            if (!confirm('Re-queue ' + ids.length + ' message(s)?')) { return; }
            var form = document.getElementById('bulk-action-form');
            form.action = "{{ route('medical.laboratory.analyzers.messages.bulk-retry', $analyzer) }}";
            document.getElementById('bulk-ids').innerHTML = ids.map(function (id) {
                return '<input type="hidden" name="ids[]" value="' + id + '">';
            }).join('');
            form.submit();
        });
        document.getElementById('bulk-discard-btn').addEventListener('click', function () {
            var ids = collectBulkIds();
            if (ids.length === 0) { alert('Select at least one message.'); return; }
            if (!confirm('Discard ' + ids.length + ' message(s)? They will never retry.')) { return; }
            var form = document.getElementById('bulk-action-form');
            form.action = "{{ route('medical.laboratory.analyzers.messages.bulk-discard', $analyzer) }}";
            document.getElementById('bulk-ids').innerHTML = ids.map(function (id) {
                return '<input type="hidden" name="ids[]" value="' + id + '">';
            }).join('');
            form.submit();
        });
        </script>

        {{ $messages->links('pagination::bootstrap-5') }}
    </div>
</div>

<div class="alert alert-info d-none mt-3" id="live-new-results">
    New results arrived for this analyzer.
    <a href="{{ route('medical.laboratory.analyzers.messages.index', $analyzer) }}" class="alert-link">Refresh</a>
</div>

@push('scripts')
<script src="https://js.pusher.com/8.2.0/pusher.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    if (!window.Echo) { return; } // polling dashboard covers non-Echo clients

    const channel = window.Echo.private(`lab-analyzer.{{ $analyzer->id }}`);
    channel.listen('.result.stored', () => {
        document.getElementById('live-new-results').classList.remove('d-none');
    });
});
</script>
@endpush
@endsection

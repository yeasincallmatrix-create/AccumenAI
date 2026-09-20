@extends('layouts.institute')

@section('title', 'Message #' . $message->id . ' — AccumenAI')

@section('content')
<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div class="page-header-text">
        <h4 class="page-header-title">Message #{{ $message->id }} <small class="text-muted">{{ $analyzer->code }}</small></h4>
    </div>
    <div class="page-header-actions">
        <a class="btn btn-secondary" href="{{ route('medical.laboratory.analyzers.messages.index', $analyzer) }}">Back</a>
        @if(in_array($message->status, ['error', 'dead']))
            <form method="POST" action="{{ route('medical.laboratory.analyzers.messages.retry', [$analyzer, $message]) }}" class="d-inline" onsubmit="return confirm('Re-queue this message?');">
                @csrf
                <button type="submit" class="btn btn-warning">Retry</button>
            </form>
        @endif
    </div>
</div>

@if($message->error_code)
<div class="alert alert-danger">
    <strong>{{ $message->error_code }}</strong> — {{ $message->error_message }}
    <span class="text-muted">(attempts: {{ $message->attempts }})</span>
</div>
@endif

@if(in_array($message->status, ['error', 'dead']))
<div class="card border-danger mb-3">
    <div class="card-header bg-danger-subtle">
        <h5 class="mb-0"><i class="bi bi-exclamation-octagon me-2"></i>Failed Message</h5>
    </div>
    <div class="card-body">
        <p><strong>Error Code:</strong> {{ $message->error_code ?? '—' }}</p>
        <p><strong>Error Message:</strong> {{ $message->error_message ?? '—' }}</p>
        <p><strong>Attempts:</strong> {{ $message->attempts }}</p>
        <p><strong>Last Attempt:</strong> {{ $message->last_attempted_at?->diffForHumans() ?? '—' }}</p>

        @if($message->isResolved())
            <div class="alert alert-info mb-0">
                <strong>Resolution:</strong> {{ $message->resolution_status }}<br>
                <strong>Notes:</strong> {{ $message->resolution_notes }}<br>
                <strong>By:</strong> {{ $message->resolved_by }} at {{ $message->resolved_at?->format('Y-m-d H:i') }}
            </div>
        @else
            <div class="d-flex gap-2 flex-wrap">
                <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#retryModal">
                    <i class="bi bi-arrow-clockwise"></i> Retry
                </button>
                <button class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#resolveModal">
                    <i class="bi bi-check-circle"></i> Resolve Manually
                </button>
                <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#escalateModal">
                    <i class="bi bi-arrow-up-circle"></i> Escalate
                </button>
                <button class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#discardModal">
                    <i class="bi bi-trash"></i> Discard
                </button>
            </div>
        @endif
    </div>
</div>

<div class="modal fade" id="retryModal" tabindex="-1">
    <div class="modal-dialog"><div class="modal-content">
        <form method="POST" action="{{ route('medical.laboratory.analyzers.messages.retry', [$analyzer, $message]) }}">
            @csrf
            <div class="modal-header"><h5 class="modal-title">Retry Message #{{ $message->id }}</h5></div>
            <div class="modal-body">
                <p>Re-queue this message for processing? Attempts reset to 0.</p>
                <textarea name="notes" class="form-control" rows="2" placeholder="Optional notes"></textarea>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Retry</button>
            </div>
        </form>
    </div></div>
</div>

<div class="modal fade" id="resolveModal" tabindex="-1">
    <div class="modal-dialog"><div class="modal-content">
        <form method="POST" action="{{ route('medical.laboratory.analyzers.messages.resolve-manual', [$analyzer, $message]) }}">
            @csrf
            <div class="modal-header"><h5 class="modal-title">Resolve Manually</h5></div>
            <div class="modal-body">
                <label class="form-label" for="resolve_notes">Resolution notes *</label>
                <textarea id="resolve_notes" name="notes" class="form-control" rows="3" required minlength="5" maxlength="500" placeholder="What was fixed (sample linked, mapping corrected...)"></textarea>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-info">Resolve</button>
            </div>
        </form>
    </div></div>
</div>

<div class="modal fade" id="escalateModal" tabindex="-1">
    <div class="modal-dialog"><div class="modal-content">
        <form method="POST" action="{{ route('medical.laboratory.analyzers.messages.escalate', [$analyzer, $message]) }}">
            @csrf
            <div class="modal-header"><h5 class="modal-title">Escalate for Review</h5></div>
            <div class="modal-body">
                <label class="form-label" for="escalate_reason">Reason *</label>
                <textarea id="escalate_reason" name="reason" class="form-control" rows="3" required minlength="5" maxlength="500"></textarea>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-warning">Escalate</button>
            </div>
        </form>
    </div></div>
</div>

<div class="modal fade" id="discardModal" tabindex="-1">
    <div class="modal-dialog"><div class="modal-content">
        <form method="POST" action="{{ route('medical.laboratory.analyzers.messages.discard', [$analyzer, $message]) }}">
            @csrf
            <div class="modal-header"><h5 class="modal-title">Discard Message</h5></div>
            <div class="modal-body">
                <p class="text-danger">The message stays in dead-letter but will never retry.</p>
                <label class="form-label" for="discard_reason">Reason *</label>
                <textarea id="discard_reason" name="reason" class="form-control" rows="3" required minlength="5" maxlength="500"></textarea>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-outline-danger">Discard</button>
            </div>
        </form>
    </div></div>
</div>
@endif

<div class="card mb-3">
    <div class="card-header"><h6 class="mb-0">Metadata</h6></div>
    <div class="card-body">
        <dl class="row mb-0">
            <dt class="col-md-3">Status</dt><dd class="col-md-3">{{ $message->status }}</dd>
            <dt class="col-md-3">Direction</dt><dd class="col-md-3">{{ $message->direction }}</dd>
            <dt class="col-md-3">Protocol</dt><dd class="col-md-3">{{ strtoupper($message->protocol) }} ({{ $message->adapter_key }}:{{ $message->adapter_version }})</dd>
            <dt class="col-md-3">Message ID</dt><dd class="col-md-3">{{ $message->message_id ?? '—' }}</dd>
            <dt class="col-md-3">Accession</dt><dd class="col-md-3">{{ $message->accession_number ?? '—' }}</dd>
            <dt class="col-md-3">Sample / Order</dt><dd class="col-md-3">{{ $message->sample_id ?? '—' }} / {{ $message->lab_order_id ?? '—' }}</dd>
            <dt class="col-md-3">Received</dt><dd class="col-md-3">{{ $message->received_at }}</dd>
            <dt class="col-md-3">Source</dt><dd class="col-md-3">{{ $message->source_ip ?? '—' }}</dd>
        </dl>
    </div>
</div>

<div class="row mb-3">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header"><h6 class="mb-0">Raw Payload</h6></div>
            <div class="card-body">
                <pre class="bg-light p-3 rounded overflow-auto" style="max-height:400px"><code>{{ $message->raw_payload }}</code></pre>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card">
            <div class="card-header"><h6 class="mb-0">Parsed JSON</h6></div>
            <div class="card-body">
                @if($message->parsed_json)
                    <pre class="bg-light p-3 rounded overflow-auto" style="max-height:400px"><code>{{ json_encode($message->parsed_json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</code></pre>
                @else
                    <p class="text-muted mb-0">Not parsed yet.</p>
                @endif
            </div>
        </div>
    </div>
</div>

@if($linkedResults->count() > 0)
<div class="card mb-3">
    <div class="card-header"><h6 class="mb-0">Linked Results</h6></div>
    <div class="card-body">
        <ul class="list-unstyled mb-0">
            @foreach($linkedResults as $result)
            <li class="border-bottom py-2">
                <strong>{{ $result->labTest->code ?? 'Test #'.$result->lab_test_id }}</strong>
                <span class="text-muted">· {{ $result->result_value ?? $result->result_text ?? 'pending' }}</span>
                <span class="badge bg-secondary float-end">{{ $result->status }}</span>
                @if($message->lab_order_id)
                    <a href="{{ route('medical.lab.orders.show', $message->lab_order_id) }}" class="btn btn-sm btn-link">View Linked Order</a>
                @endif
            </li>
            @endforeach
        </ul>
    </div>
</div>
@endif

<div class="card">
    <div class="card-header"><h6 class="mb-0">Lifecycle Timeline</h6></div>
    <div class="card-body">
        <ul class="list-unstyled mb-0">
            <li class="border-bottom py-2"><i class="bi bi-inbox me-2"></i>Received — {{ $message->received_at }} (attempts: {{ $message->attempts }})</li>
            @if($message->processed_at)
                <li class="border-bottom py-2"><i class="bi bi-gear me-2"></i>Processed — {{ $message->processed_at }}</li>
            @endif
            @if($message->isResolved())
                <li class="border-bottom py-2"><i class="bi bi-check-circle me-2"></i>Resolved as <strong>{{ $message->resolution_status }}</strong> — {{ $message->resolved_at?->format('Y-m-d H:i') }} by user #{{ $message->resolved_by }}</li>
                <li class="py-2 text-muted small">{{ $message->resolution_notes }}</li>
            @elseif(in_array($message->status, ['error', 'dead']))
                <li class="py-2 text-muted">Awaiting resolution — use Retry, Resolve, Escalate, or Discard above.</li>
            @endif
        </ul>
    </div>
</div>
@endsection

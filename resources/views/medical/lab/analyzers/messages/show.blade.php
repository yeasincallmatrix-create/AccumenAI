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
<div class="card">
    <div class="card-header"><h6 class="mb-0">Linked Results</h6></div>
    <div class="card-body">
        <ul class="list-unstyled mb-0">
            @foreach($linkedResults as $result)
            <li class="border-bottom py-2">
                <strong>{{ $result->labTest->code ?? 'Test #'.$result->lab_test_id }}</strong>
                <span class="text-muted">· {{ $result->result_value ?? $result->result_text ?? 'pending' }}</span>
                <span class="badge bg-secondary float-end">{{ $result->status }}</span>
            </li>
            @endforeach
        </ul>
    </div>
</div>
@endif
@endsection

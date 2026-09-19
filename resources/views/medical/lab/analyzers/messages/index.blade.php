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
                <div class="col-md-3">
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
                    <tr><th>ID</th><th>Received</th><th>Status</th><th>Message ID</th><th>Accession</th><th>Error</th><th class="text-end">Actions</th></tr>
                </thead>
                <tbody>
                    @forelse($messages as $message)
                    <tr>
                        <td><strong>#{{ $message->id }}</strong></td>
                        <td>{{ $message->received_at ? $message->received_at->format('d M H:i') : '—' }}</td>
                        <td><span class="badge bg-{{ $message->status === 'stored' ? 'success' : (in_array($message->status, ['error', 'dead']) ? 'danger' : 'secondary') }}">{{ $message->status }}</span></td>
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
                        <td colspan="7" class="text-center text-muted py-4">
                            <i class="bi bi-inbox fs-2 d-block mb-2"></i>
                            No messages found.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $messages->links('pagination::bootstrap-5') }}
    </div>
</div>
@endsection

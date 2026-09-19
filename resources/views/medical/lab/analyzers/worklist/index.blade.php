@extends('layouts.institute')

@section('title', 'Analyzer Worklist — AccumenAI')

@section('content')
<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div class="page-header-text">
        <h4 class="page-header-title">Worklist <small class="text-muted">{{ $analyzer->code }}</small></h4>
    </div>
    <div class="page-header-actions">
        <a class="btn btn-secondary" href="{{ route('medical.laboratory.analyzers.show', $analyzer) }}">Back</a>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <form method="GET" class="mb-3">
            <div class="row g-2">
                <div class="col-md-4">
                    <select name="status" class="form-select">
                        <option value="">All Status</option>
                        @foreach(['pending', 'sent', 'acked', 'expired', 'failed'] as $status)
                            <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-8 text-end">
                    <button type="submit" class="btn btn-primary">Filter</button>
                    <a href="{{ route('medical.laboratory.analyzers.worklist.index', $analyzer) }}" class="btn btn-secondary">Reset</a>
                </div>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr><th>ID</th><th>Accession</th><th>Status</th><th>Created</th><th>Sent At</th><th>Acked At</th></tr>
                </thead>
                <tbody>
                    @forelse($worklists as $worklist)
                    <tr>
                        <td><strong>#{{ $worklist->id }}</strong></td>
                        <td>{{ $worklist->order_snapshot['accession_number'] ?? '—' }}</td>
                        <td><span class="badge bg-{{ $worklist->status === 'acked' ? 'success' : ($worklist->status === 'failed' ? 'danger' : ($worklist->status === 'sent' ? 'info' : 'secondary')) }}">{{ $worklist->status }}</span></td>
                        <td>{{ $worklist->created_at ? $worklist->created_at->format('d M H:i') : '—' }}</td>
                        <td>{{ $worklist->sent_at ? $worklist->sent_at->format('d M H:i') : '—' }}</td>
                        <td>{{ $worklist->acked_at ? $worklist->acked_at->format('d M H:i') : '—' }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">
                            <i class="bi bi-list-check fs-2 d-block mb-2"></i>
                            No worklist entries.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $worklists->links('pagination::bootstrap-5') }}
    </div>
</div>
@endsection

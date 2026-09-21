@extends('layouts.standalone')

@section('title', 'Bank Feed Import — AccumenAI')
@section('page_title', 'Accounting')

@section('content')

<div class="standalone-heading">
    <h4>Bank Feed Import</h4>
    <p>Import bank statements and auto-match transactions to journal entries.</p>
    <div class="d-flex gap-2">
        <a href="{{ route('accounting.bank-feed.upload') }}" class="btn btn-primary btn-sm"><i class="bi bi-upload me-1"></i>Import Statement</a>
        <a href="{{ route('accounting.bank-feed.rules') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-gear me-1"></i>Rules</a>
    </div>
</div>

<div class="admin-card">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Bank Account</th>
                    <th>Source</th>
                    <th>File</th>
                    <th>Lines</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($statements as $stmt)
                    <tr>
                        <td>{{ $stmt->statement_date?->format('d M Y') ?? '—' }}</td>
                        <td>{{ $stmt->bankAccount?->name ?? '—' }}</td>
                        <td><span class="badge text-bg-light border">{{ strtoupper($stmt->import_source ?? 'manual') }}</span></td>
                        <td>{{ $stmt->original_filename ?? $stmt->file_name ?? '—' }}</td>
                        <td>{{ $stmt->lines()->count() }}</td>
                        <td><span class="badge text-bg-{{ $stmt->status === 'imported' ? 'success' : ($stmt->status === 'cancelled' ? 'danger' : 'primary') }}">{{ $stmt->status }}</span></td>
                        <td class="text-end">
                            <a href="{{ route('accounting.bank-feed.statement', $stmt) }}" class="btn btn-outline-primary btn-sm"><i class="bi bi-eye"></i> View</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center text-muted py-4">No statements imported yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-3">{{ $statements->links() }}</div>
</div>

@endsection

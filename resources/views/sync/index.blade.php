@extends('layouts.institute')

@section('title', 'Offline Review — AccumenAI')

@section('content')
@push('styles')
<style>
    @media print {
        .topbar, .sidebar, .sidebar-backdrop, .page-header, .monetix-print-hidden { display: none !important; }
        .layout { display: block !important; min-height: 0 !important; }
        .content { width: 100% !important; margin: 0 !important; padding: 0 !important; }
        .admin-card { box-shadow: none !important; border: none !important; }
        .print-header { display: block !important; margin-bottom: 12px; }
        .table-responsive { overflow: visible !important; }
        .table { width: 100% !important; border-collapse: collapse; }
    }
</style>
@endpush
@php
    $statusBadge = [
        'pending_review' => 'bg-warning text-dark',
        'approved'       => 'bg-success',
        'rejected'       => 'bg-danger',
    ];
    $statusLabel = [
        'pending_review' => 'Pending Review',
        'approved'       => 'Approved',
        'rejected'       => 'Rejected',
    ];
@endphp

<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div class="page-header-text">
        <h4 class="page-header-title">Offline Review</h4>
        <p class="page-header-desc">{{ $counts['pending_review'] }} record(s) awaiting approval</p>
    </div>
    <div class="page-header-actions d-flex gap-2">
        <button class="btn btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#syncUploadCard" aria-expanded="false">
            <i class="bi bi-cloud-upload me-1"></i>Test Offline Upload
        </button>
        <button class="btn btn-outline-primary" type="button" onclick="window.print()" title="Print">
            <i class="bi bi-printer me-1"></i>Print
        </button>
    </div>
</div>

@if ($user->hasPermission('finance.manage'))
<div class="collapse mb-3 monetix-print-hidden" id="syncUploadCard">
    <div class="admin-card">
        <h5 class="mb-3">Simulate an offline client upload</h5>
        <form method="POST" action="{{ route('sync.upload') }}" class="row g-2 align-items-end">
            @csrf
            <input type="hidden" name="records[0][client_uuid]" value="{{ Illuminate\Support\Str::uuid() }}">
            <input type="hidden" name="records[0][entity_type]" value="cash_memo">
            <input type="hidden" name="records[0][created_offline_at]" value="{{ now()->format('Y-m-d H:i:s') }}">
            <div class="col-md-3">
                <label class="form-label small">Student (optional)</label>
                <select name="records[0][payload][student_id]" class="form-select">
                    <option value="">—</option>
                    @foreach (\App\Models\Student::query()->latest('id')->limit(200)->get() as $student)
                        <option value="{{ $student->id }}">{{ $student->student_id }} — {{ $student->full_name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small">Amount (BDT)</label>
                <input type="number" step="0.01" min="0.01" name="records[0][payload][amount]"
                       class="form-control" placeholder="1500" required>
            </div>
            <div class="col-md-2">
                <label class="form-label small">Payment Method</label>
                <select name="records[0][payload][payment_method]" class="form-select">
                    @foreach (['cash', 'bkash', 'nagad', 'bank', 'other'] as $method)
                        <option value="{{ $method }}">{{ ucfirst($method) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small">Description</label>
                <input type="text" name="records[0][payload][description]" class="form-control" placeholder="Tuition payment">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100"><i class="bi bi-cloud-upload me-1"></i>Upload</button>
            </div>
        </form>
    </div>
</div>
@endif

<div class="admin-card">

    <div class="print-header d-none">
        <h4 class="mb-1">{{ $institute->name ?? '' }} — Offline Review</h4>
        <p class="mb-0 text-muted">{{ $counts['pending_review'] }} record(s) awaiting approval · {{ now()->format('d M Y') }}</p>
    </div>

    <ul class="nav nav-pills mb-3 gap-1 monetix-print-hidden">
        <li class="nav-item">
            <a class="nav-link {{ $status === 'pending_review' ? 'active' : '' }}" href="{{ route('sync.index', ['status' => 'pending_review']) }}">
                Pending <span class="badge text-bg-light ms-1">{{ $counts['pending_review'] }}</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ $status === 'approved' ? 'active' : '' }}" href="{{ route('sync.index', ['status' => 'approved']) }}">
                Approved <span class="badge text-bg-light ms-1">{{ $counts['approved'] }}</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ $status === 'rejected' ? 'active' : '' }}" href="{{ route('sync.index', ['status' => 'rejected']) }}">
                Rejected <span class="badge text-bg-light ms-1">{{ $counts['rejected'] }}</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ $status === 'all' ? 'active' : '' }}" href="{{ route('sync.index', ['status' => 'all']) }}">
                All
            </a>
        </li>
    </ul>

    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Created Offline</th>
                    <th>Type</th>
                    <th>Cash Memo Data</th>
                    <th>Student</th>
                    <th>Uploaded By</th>
                    <th>Status</th>
                    <th class="text-end monetix-print-hidden">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($records as $record)
                    <tr>
                        <td class="text-muted">{{ $records->firstItem() + $loop->index }}</td>
                        <td>
                            <span class="d-block">{{ $record->created_offline_at->format('d M Y h:i A') }}</span>
                            <small class="text-muted">{{ strtoupper($record->client_uuid) }}</small>
                        </td>
                        <td><span class="badge bg-secondary">{{ $record->entity_type }}</span></td>
                        <td>
                            @php $p = $record->payload_data; @endphp
                            <div>
                                <span class="fw-semibold text-primary">{{ number_format((float) $p['amount'], 2) }} BDT</span>
                                <span class="badge bg-light text-dark border ms-1">{{ $p['payment_method'] ?? 'cash' }}</span>
                            </div>
                            <small class="text-muted d-block">
                                @if ($record->status === 'approved' && $record->materialized_id)
                                    <i class="bi bi-receipt"></i> {{ \App\Models\CashMemo::query()->find($record->materialized_id)?->memo_number }}
                                @elseif (!empty($p['memo_number']))
                                    {{ $p['memo_number'] }}
                                @else
                                    auto-number on approval
                                @endif
                            </small>
                            @if (!empty($p['description']))
                                <small class="text-muted d-block">{{ $p['description'] }}</small>
                            @endif
                        </td>
                        <td>
                            @if (!empty($p['student_id']) && ($student = $students->get($p['student_id'])))
                                <span class="fw-semibold">{{ $student->full_name }}</span>
                                <small class="text-muted d-block">{{ $student->student_id }}</small>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td>
                            <span class="fw-semibold">{{ $record->creator?->email }}</span>
                            @if ($record->status !== 'pending_review' && $record->reviewed_at)
                                <small class="text-muted d-block">
                                    @if ($record->status === 'rejected' && $record->reject_reason)
                                        <i class="bi bi-x-circle text-danger"></i> {{ $record->reject_reason }}
                                    @else
                                        reviewed {{ $record->reviewed_at->format('d M Y h:i A') }}
                                    @endif
                                </small>
                            @endif
                        </td>
                        <td>
                            <span class="badge {{ $statusBadge[$record->status] ?? 'bg-secondary' }}">
                                {{ $statusLabel[$record->status] ?? $record->status }}
                            </span>
                        </td>
                        <td class="text-end monetix-print-hidden">
                            @if ($record->status === 'pending_review' && $user->hasPermission('finance.manage'))
                                <form class="d-inline" method="POST" action="{{ route('sync.approve', $record) }}"
                                      onsubmit="return confirm('Approve and materialize this {{ $record->entity_type }}?');">
                                    @csrf
                                    <button class="btn btn-sm btn-outline-success" type="submit" title="Approve">
                                        <i class="bi bi-check-lg"></i> Approve
                                    </button>
                                </form>
                                <button class="btn btn-sm btn-outline-danger" type="button"
                                        title="Reject" data-bs-toggle="modal" data-bs-target="#rejectModal-{{ $record->id }}">
                                    <i class="bi bi-x-lg"></i> Reject
                                </button>

                                <div class="modal fade" id="rejectModal-{{ $record->id }}" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog modal-dialog-centered">
                                        <form class="modal-content" method="POST" action="{{ route('sync.reject', $record) }}">
                                            @csrf
                                            <div class="modal-header">
                                                <h5 class="modal-title">Reject {{ $record->entity_type }}</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body">
                                                <input type="hidden" name="client_uuid" value="{{ $record->client_uuid }}">
                                                <label class="form-label" for="reject-reason-{{ $record->id }}">Reason</label>
                                                <textarea id="reject-reason-{{ $record->id }}" name="reject_reason"
                                                          class="form-control" rows="2" maxlength="255" required></textarea>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                                                <button type="submit" class="btn btn-danger">Reject</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">No records found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <nav class="mt-4 d-flex flex-column align-items-center gap-2 monetix-print-hidden">
        {{ $records->links('pagination::bootstrap-5') }}
        <span class="text-muted small">{{ $records->total() }} record(s)</span>
    </nav>

</div>
@endsection
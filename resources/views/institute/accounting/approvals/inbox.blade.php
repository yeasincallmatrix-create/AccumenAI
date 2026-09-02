@extends('layouts.standalone')

@section('title', 'Approval Inbox — AccumenAI')
@section('page_title', 'Approvals')

@section('content')

<div class="standalone-heading">
    <h4>Approval Inbox</h4>
    <p>Pending approval requests awaiting your action.</p>
</div>

<div class="admin-card">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Workflow</th>
                    <th>Reference</th>
                    <th class="text-end">Amount</th>
                    <th>Requested</th>
                    <th>Step</th>
                    <th class="text-end">Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($pending as $req)
                    <tr>
                        <td class="text-muted">{{ $req->id }}</td>
                        <td>{{ $req->workflow->name ?? '—' }}</td>
                        <td>{{ $req->ref_type }} #{{ $req->ref_id }}</td>
                        <td class="text-end fw-semibold">{{ number_format((float) $req->amount, 2) }}</td>
                        <td>{{ $req->requested_at?->format('d M Y H:i') ?? '—' }}</td>
                        <td>Step {{ $req->current_step }}</td>
                        <td class="text-end d-flex gap-1 justify-content-end">
                            <form method="POST" action="{{ route('accounting.approvals.approve', $req->id) }}" class="d-inline">
                                @csrf
                                <button type="submit" class="btn btn-success btn-sm"><i class="bi bi-check-lg"></i> Approve</button>
                            </form>
                            <form method="POST" action="{{ route('accounting.approvals.reject', $req->id) }}" class="d-inline">
                                @csrf
                                <button type="submit" class="btn btn-danger btn-sm"><i class="bi bi-x-lg"></i> Reject</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center text-muted py-4">No pending approvals.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@endsection

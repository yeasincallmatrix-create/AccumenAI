@extends('layouts.standalone')

@section('title', 'Approval Workflows — AccumenAI')
@section('page_title', 'Approvals')

@section('content')

<div class="standalone-heading">
    <h4>Approval Workflows</h4>
    <p>Configure multi-step approval chains for journals, payments, purchases and salary disbursements.</p>
    <a href="{{ route('accounting.approvals.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> New Workflow</a>
</div>

<div class="admin-card">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Workflow</th>
                    <th>Module</th>
                    <th class="text-end">Amount From</th>
                    <th class="text-end">Amount To</th>
                    <th>Steps</th>
                    <th>Status</th>
                    <th class="text-end">Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($workflows as $wf)
                    <tr>
                        <td class="text-muted">{{ $loop->iteration }}</td>
                        <td class="fw-semibold">{{ $wf->name }}</td>
                        <td><span class="badge bg-secondary">{{ $wf->module }}</span></td>
                        <td class="text-end">{{ number_format((float) $wf->amount_from, 2) }}</td>
                        <td class="text-end">{{ number_format((float) $wf->amount_to, 2) }}</td>
                        <td>{{ $wf->steps->count() }}</td>
                        <td>
                            @if ($wf->is_active)
                                <span class="badge bg-success">Active</span>
                            @else
                                <span class="badge bg-secondary">Inactive</span>
                            @endif
                        </td>
                        <td class="text-end">
                            <a href="{{ route('accounting.approvals.show', $wf->id) }}" class="btn btn-outline-primary btn-sm">
                                <i class="bi bi-eye"></i>
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">No approval workflows configured.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@endsection

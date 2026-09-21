@extends('layouts.standalone')

@section('title', 'Progressive Contracts — AccumenAI')
@section('page_title', 'Finance')

@section('content')

<div class="standalone-heading">
    <h4>Progressive Contracts</h4>
    <p>Manage milestone-based and progress billing contracts.</p>
    <div class="d-flex gap-2">
        <a href="{{ route('finance.progressive-contracts.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>New Contract</a>
    </div>
</div>

<div class="admin-card mb-3">
    <form method="GET" class="row g-2 align-items-end">
        <div class="col-md-4">
            <input type="text" class="form-control form-control-sm" name="q" value="{{ request('q') }}" placeholder="Search contract # or title...">
        </div>
        <div class="col-md-3">
            <select class="form-select form-select-sm" name="status">
                <option value="">All statuses</option>
                @foreach (['active', 'on_hold', 'completed', 'cancelled', 'terminated'] as $s)
                    <option value="{{ $s }}" @selected(request('status') === $s)>{{ ucfirst(str_replace('_', ' ', $s)) }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2">
            <button class="btn btn-outline-primary btn-sm" type="submit"><i class="bi bi-search me-1"></i>Filter</button>
            @if (request('q') || request('status'))
                <a href="{{ route('finance.progressive-contracts.index') }}" class="btn btn-outline-secondary btn-sm">Clear</a>
            @endif
        </div>
    </form>
</div>

<div class="admin-card">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>Contract #</th>
                    <th>Client</th>
                    <th>Title</th>
                    <th class="text-end">Total Value</th>
                    <th class="text-end">Billed</th>
                    <th class="text-end">Remaining</th>
                    <th>Progress</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($contracts as $contract)
                    <tr>
                        <td><a href="{{ route('finance.progressive-contracts.show', $contract) }}" class="text-decoration-none fw-semibold">{{ $contract->contract_number }}</a></td>
                        <td>{{ $contract->party?->name ?? '—' }}</td>
                        <td>{{ $contract->title }}</td>
                        <td class="text-end">{{ number_format((float) $contract->total_value, 2) }}</td>
                        <td class="text-end">{{ number_format((float) $contract->total_billed, 2) }}</td>
                        <td class="text-end">{{ number_format((float) $contract->remaining_value, 2) }}</td>
                        <td style="min-width: 120px">
                            @php $pct = $contract->percentBilled(); @endphp
                            <div class="progress" style="height: 6px;">
                                <div class="progress-bar bg-{{ $contract->statusColor() }}" role="progressbar" style="width: {{ min($pct, 100) }}%"></div>
                            </div>
                            <small class="text-muted">{{ $pct }}%</small>
                        </td>
                        <td><span class="badge text-bg-{{ $contract->statusColor() }}">{{ str_replace('_', ' ', $contract->status) }}</span></td>
                        <td class="text-end">
                            <a href="{{ route('finance.progressive-contracts.show', $contract) }}" class="btn btn-sm btn-outline-primary" title="View"><i class="bi bi-eye"></i></a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="text-center text-muted py-4">No progressive contracts found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-3">
        {{ $contracts->links() }}
    </div>
</div>

@endsection

@extends('layouts.standalone')

@section('title', 'Contract ' . $contract->contract_number . ' — AccumenAI')
@section('page_title', 'Finance')

@section('content')

<div class="standalone-heading">
    <h4>Contract {{ $contract->contract_number }}</h4>
    <p>
        {{ $contract->party?->name ?? '—' }} · {{ $contract->title }}
        · <span class="badge text-bg-{{ $contract->statusColor() }}">{{ str_replace('_', ' ', $contract->status) }}</span>
    </p>
    <div class="d-flex gap-2 flex-wrap">
        @if ($contract->isActive())
            <a href="{{ route('finance.progressive-contracts.create-invoice', $contract) }}" class="btn btn-primary btn-sm"><i class="bi bi-receipt me-1"></i>Create Progress Invoice</a>
            <a href="{{ route('finance.progressive-contracts.edit', $contract) }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-pencil me-1"></i>Edit</a>
        @endif
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-3">
        <div class="admin-card text-center">
            <small class="text-muted text-uppercase">Total Value</small>
            <h5 class="mb-0 mt-1">{{ number_format($summary['total_value'], 2) }}</h5>
        </div>
    </div>
    <div class="col-md-3">
        <div class="admin-card text-center">
            <small class="text-muted text-uppercase">Billed</small>
            <h5 class="mb-0 mt-1">{{ number_format($summary['total_billed'], 2) }}</h5>
        </div>
    </div>
    <div class="col-md-3">
        <div class="admin-card text-center">
            <small class="text-muted text-uppercase">Remaining</small>
            <h5 class="mb-0 mt-1">{{ number_format($summary['remaining_value'], 2) }}</h5>
        </div>
    </div>
    <div class="col-md-3">
        <div class="admin-card text-center">
            <small class="text-muted text-uppercase">Retention Held</small>
            <h5 class="mb-0 mt-1">{{ number_format($summary['retention_held'], 2) }}</h5>
        </div>
    </div>
</div>

<div class="admin-card mb-3">
    <h6 class="card-title">Progress</h6>
    @php $pct = $summary['percent_billed']; @endphp
    <div class="progress mb-2" style="height: 12px;">
        <div class="progress-bar bg-{{ $contract->statusColor() }}" role="progressbar" style="width: {{ min($pct, 100) }}%">{{ $pct }}%</div>
    </div>
    <div class="d-flex justify-content-between small text-muted">
        <span>{{ $summary['invoice_count'] }} invoice(s) issued</span>
        <span>{{ $pct }}% billed</span>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-lg-7">
        <div class="admin-card mb-3">
            <h6 class="card-title">Contract Details</h6>
            <table class="table table-sm mb-0">
                <tbody>
                    <tr><td class="text-muted" style="width:180px">Contract #</td><td>{{ $contract->contract_number }}</td></tr>
                    <tr><td class="text-muted">Client</td><td>{{ $contract->party?->name ?? '—' }}</td></tr>
                    <tr><td class="text-muted">Currency</td><td>{{ $contract->currency }}</td></tr>
                    <tr><td class="text-muted">Retention %</td><td>{{ number_format((float) $contract->retention_percentage, 2) }}%</td></tr>
                    <tr><td class="text-muted">Retention Amount</td><td>{{ number_format((float) $contract->retention_amount, 2) }}</td></tr>
                    <tr><td class="text-muted">Retention Released</td><td>{{ number_format((float) $contract->retention_released, 2) }}</td></tr>
                    <tr><td class="text-muted">Start Date</td><td>{{ $contract->start_date?->format('d M Y') ?? '—' }}</td></tr>
                    <tr><td class="text-muted">Expected End</td><td>{{ $contract->expected_end_date?->format('d M Y') ?? '—' }}</td></tr>
                    @if ($contract->actual_end_date)
                        <tr><td class="text-muted">Actual End</td><td>{{ $contract->actual_end_date->format('d M Y') }}</td></tr>
                    @endif
                    @if ($contract->description)
                        <tr><td class="text-muted">Description</td><td>{!! nl2br(e($contract->description)) !!}</td></tr>
                    @endif
                    @if ($contract->notes)
                        <tr><td class="text-muted">Notes</td><td>{!! nl2br(e($contract->notes)) !!}</td></tr>
                    @endif
                </tbody>
            </table>
        </div>
    </div>
    <div class="col-lg-5">
        @if ($contract->isActive())
            <div class="admin-card mb-3">
                <h6 class="card-title">Cancel Contract</h6>
                <form method="POST" action="{{ route('finance.progressive-contracts.cancel', $contract) }}" data-confirm="Cancel this contract? This action cannot be undone.">
                    @csrf
                    <div class="mb-2">
                        <input type="text" class="form-control form-control-sm" name="reason" placeholder="Reason for cancellation (required)" required minlength="5" maxlength="500">
                    </div>
                    <button class="btn btn-outline-danger btn-sm" type="submit"><i class="bi bi-x-lg me-1"></i>Cancel contract</button>
                </form>
            </div>
        @endif
    </div>
</div>

<div class="admin-card">
    <h6 class="card-title">Progress Invoices</h6>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>Invoice #</th>
                    <th>Date</th>
                    <th>Milestone</th>
                    <th class="text-end">Amount</th>
                    <th class="text-end">Retention</th>
                    <th class="text-end">Cumulative</th>
                    <th>Status</th>
                    <th>Final</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($contract->invoices as $inv)
                    <tr>
                        <td><a href="{{ route('finance.invoices.show', $inv) }}" class="text-decoration-none">{{ $inv->invoice_number }}</a></td>
                        <td><x-tdate :value="$inv->created_at" fallback="Y-m-d" /></td>
                        <td>{{ $inv->milestone_name ?? '—' }}</td>
                        <td class="text-end">{{ number_format((float) $inv->total_amount, 2) }}</td>
                        <td class="text-end">{{ number_format((float) $inv->retention_amount, 2) }}</td>
                        <td class="text-end">{{ number_format((float) $inv->cumulative_billed, 2) }}</td>
                        <td><span class="badge text-bg-{{ $inv->status === 'paid' ? 'success' : ($inv->status === 'partial' ? 'warning' : 'danger') }}">{{ $inv->status }}</span></td>
                        <td>{!! $inv->is_final_progressive ? '<i class="bi bi-check-circle-fill text-success"></i>' : '<i class="text-muted">—</i>' !!}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="text-center text-muted py-3">No progress invoices yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@endsection

@extends('layouts.institute')

@section('title', $budget->name . ' — Budget')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1">{{ $budget->name }}</h4>
        <div class="d-flex gap-2 align-items-center">
            @php $statusColors = ['draft' => 'secondary', 'submitted' => 'warning', 'approved' => 'success', 'rejected' => 'danger', 'locked' => 'primary']; @endphp
            <span class="badge bg-{{ $statusColors[$budget->status] }}">{{ ucfirst($budget->status) }}</span>
            <span class="badge bg-info">v{{ $budget->version }}</span>
            <span class="badge bg-secondary">{{ ucfirst($budget->type) }}</span>
        </div>
    </div>
    <div class="d-flex gap-2">
        <a class="btn btn-outline-secondary btn-sm rounded-pill" href="{{ route('finance.budgets.index') }}"><i class="bi bi-arrow-left me-1"></i>Back</a>
        @if ($budget->isEditable())
            <a class="btn btn-outline-primary btn-sm rounded-pill" href="{{ route('finance.budgets.edit', $budget->id) }}"><i class="bi bi-pencil me-1"></i>Edit</a>
        @endif
        @if ($budget->isDraft() || $budget->isRejected())
            <form method="POST" action="{{ route('finance.budgets.submit', $budget->id) }}" class="d-inline">
                @csrf
                <button class="btn btn-warning btn-sm rounded-pill" type="submit"><i class="bi bi-send me-1"></i>Submit</button>
            </form>
        @endif
        @if ($budget->isSubmitted())
            <form method="POST" action="{{ route('finance.budgets.approve', $budget->id) }}" class="d-inline">
                @csrf
                <button class="btn btn-success btn-sm rounded-pill" type="submit"><i class="bi bi-check-lg me-1"></i>Approve</button>
            </form>
            <button class="btn btn-danger btn-sm rounded-pill" type="button" data-bs-toggle="modal" data-bs-target="#rejectModal"><i class="bi bi-x-lg me-1"></i>Reject</button>
        @endif
        @if ($budget->isApproved())
            <form method="POST" action="{{ route('finance.budgets.lock', $budget->id) }}" class="d-inline">
                @csrf
                <button class="btn btn-primary btn-sm rounded-pill" type="submit"><i class="bi bi-lock me-1"></i>Lock</button>
            </form>
            <button class="btn btn-outline-warning btn-sm rounded-pill" type="button" data-bs-toggle="modal" data-bs-target="#reviseModal"><i class="bi bi-arrow-repeat me-1"></i>Revise</button>
        @endif
        @if ($budget->isLocked())
            <button class="btn btn-outline-warning btn-sm rounded-pill" type="button" data-bs-toggle="modal" data-bs-target="#reviseModal"><i class="bi bi-arrow-repeat me-1"></i>Revise</button>
        @endif
    </div>
</div>

@if ($alerts && count($alerts) > 0)
    <div class="card mb-4 border-warning">
        <div class="card-header bg-warning text-dark fw-semibold"><i class="bi bi-exclamation-triangle me-1"></i>Budget Alerts</div>
        <div class="card-body p-0">
            <div class="list-group list-group-flush">
                @foreach ($alerts as $alert)
                    <div class="list-group-item d-flex justify-content-between align-items-center">
                        <div>
                            <span class="badge bg-{{ $alert['level'] === 'severe' ? 'danger' : ($alert['level'] === 'critical' ? 'warning' : 'info') }} me-2">{{ ucfirst($alert['level']) }}</span>
                            {{ $alert['message'] }}
                        </div>
                        <span class="fw-semibold">{{ $alert['consumed_pct'] }}%</span>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
@endif

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card">
            <div class="card-body text-center">
                <div class="text-muted small">Total Budget</div>
                <div class="fs-5 fw-bold">{{ number_format($comparison['totals']['budget'], 2) }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card">
            <div class="card-body text-center">
                <div class="text-muted small">Actual</div>
                <div class="fs-5 fw-bold">{{ number_format($comparison['totals']['actual'], 2) }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card">
            <div class="card-body text-center">
                <div class="text-muted small">Variance</div>
                <div class="fs-5 fw-bold {{ $comparison['totals']['variance'] >= 0 ? 'text-success' : 'text-danger' }}">
                    {{ number_format($comparison['totals']['variance'], 2) }}
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card">
            <div class="card-body text-center">
                <div class="text-muted small">Variance %</div>
                <div class="fs-5 fw-bold {{ $comparison['totals']['variance_pct'] >= 0 ? 'text-success' : 'text-danger' }}">
                    {{ $comparison['totals']['variance_pct'] }}%
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header fw-semibold"><i class="bi bi-journal-text me-1"></i>Budget Lines</div>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Account</th>
                    <th>Month</th>
                    <th class="text-end">Budget</th>
                    <th class="text-end">Actual</th>
                    <th class="text-end">Variance</th>
                    <th class="text-end">Variance %</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($comparison['lines'] as $line)
                    <tr>
                        <td class="text-muted">{{ $line['code'] }}</td>
                        <td class="fw-semibold">{{ $line['name'] }}</td>
                        <td>{{ $line['month'] > 0 ? \Carbon\Carbon::create()->month($line['month'])->format('M') : 'Annual' }}</td>
                        <td class="text-end">{{ number_format($line['budget_amount'], 2) }}</td>
                        <td class="text-end">{{ number_format($line['actual_amount'], 2) }}</td>
                        <td class="text-end {{ $line['variance'] >= 0 ? 'text-success' : 'text-danger' }}">{{ number_format($line['variance'], 2) }}</td>
                        <td class="text-end {{ $line['variance_pct'] >= 0 ? 'text-success' : 'text-danger' }}">{{ $line['variance_pct'] }}%</td>
                        <td>
                            @if ($line['is_favorable'])
                                <span class="badge bg-success">Favorable</span>
                            @else
                                <span class="badge bg-danger">Unfavorable</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">No budget lines.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@if ($budget->versions->count() > 1)
    <div class="card">
        <div class="card-header fw-semibold"><i class="bi bi-clock-history me-1"></i>Version History</div>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr><th>Version</th><th>Status</th><th class="text-end">Amount</th><th>Reason</th><th>Created</th></tr>
                </thead>
                <tbody>
                    @foreach ($budget->versions->sortByDesc('version') as $v)
                        <tr>
                            <td>v{{ $v->version }}</td>
                            <td><span class="badge bg-{{ $statusColors[$v->status] ?? 'secondary' }}">{{ ucfirst($v->status) }}</span></td>
                            <td class="text-end">{{ number_format($v->total_amount, 2) }}</td>
                            <td>{{ $v->reason ?? '—' }}</td>
                            <td>{{ $v->created_at?->format('d M Y H:i') ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif

<div class="modal fade" id="rejectModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="{{ route('finance.budgets.reject', $budget->id) }}">
            @csrf
            <div class="modal-content">
                <div class="modal-header"><h5 class="modal-title">Reject Budget</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <label class="form-label">Reason for rejection <span class="text-danger">*</span></label>
                    <textarea name="reason" class="form-control" rows="3" required></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Reject</button>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="reviseModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="{{ route('finance.budgets.revise', $budget->id) }}">
            @csrf
            <div class="modal-content">
                <div class="modal-header"><h5 class="modal-title">Revise Budget</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <p class="text-muted">This will create a new draft version. The current approved version will be preserved.</p>
                    <label class="form-label">Reason for revision <span class="text-danger">*</span></label>
                    <textarea name="reason" class="form-control" rows="3" required></textarea>
                    <label class="form-label mt-3">Notes</label>
                    <textarea name="notes" class="form-control" rows="2"></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning">Create Revision</button>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection

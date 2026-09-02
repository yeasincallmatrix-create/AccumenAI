@extends('layouts.standalone')

@section('title', 'Statement Lines — AccumenAI')
@section('page_title', 'Bank Reconciliation')

@section('content')

<div class="standalone-heading">
    <h4>Statement — {{ $statement->bankAccount->code }} {{ $statement->bankAccount->name }}</h4>
    <p>Statement date: {{ $statement->statement_date }} · Status: {{ ucfirst($statement->status) }}</p>
    <div class="d-flex gap-2">
        <form method="POST" action="{{ route('accounting.bank-reconciliation.auto-match', ['statementId' => $statement->id]) }}">
            @csrf
            <button class="btn btn-primary btn-sm" type="submit"><i class="bi bi-search"></i> Auto Match</button>
        </form>
        <a href="{{ route('accounting.bank-reconciliation.statements', ['accountId' => $statement->bank_account_id]) }}" class="btn btn-outline-secondary btn-sm">Back</a>
    </div>
</div>

@if (session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

{{-- Summary --}}
<div class="row g-3 mb-3">
    <div class="col-md-3">
        <div class="admin-card text-center">
            <div class="small text-muted">Total Lines</div>
            <div class="fs-5 fw-semibold">{{ $summary['total'] }}</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="admin-card text-center">
            <div class="small text-muted">Matched</div>
            <div class="fs-5 fw-semibold text-success">{{ $summary['matched'] }}</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="admin-card text-center">
            <div class="small text-muted">Unmatched</div>
            <div class="fs-5 fw-semibold text-warning">{{ $summary['unmatched'] }}</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="admin-card text-center">
            <div class="small text-muted">Ignored</div>
            <div class="fs-5 fw-semibold text-muted">{{ $summary['ignored'] }}</div>
        </div>
    </div>
</div>

{{-- Add line form --}}
<div class="admin-card mb-3">
    <h6 class="card-title">Add Statement Line</h6>
    <form method="POST" action="{{ route('accounting.bank-reconciliation.lines.store', ['statementId' => $statement->id]) }}">
        @csrf
        <div class="row g-2 align-items-end">
            <div class="col-md-2">
                <label class="form-label mb-1">Date</label>
                <input type="date" class="form-control form-control-sm" name="transaction_date" value="{{ $statement->statement_date }}" required>
            </div>
            <div class="col-md-3">
                <label class="form-label mb-1">Description</label>
                <input type="text" class="form-control form-control-sm" name="description" required maxlength="500">
            </div>
            <div class="col-md-2">
                <label class="form-label mb-1">Amount</label>
                <input type="number" step="0.01" class="form-control form-control-sm" name="amount" value="0" required min="0.01">
            </div>
            <div class="col-md-2">
                <label class="form-label mb-1">Type</label>
                <select class="form-select form-select-sm" name="type" required>
                    <option value="deposit">Deposit</option>
                    <option value="withdrawal">Withdrawal</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label mb-1">Reference</label>
                <input type="text" class="form-control form-control-sm" name="reference">
            </div>
            <div class="col-md-1">
                <button class="btn btn-primary btn-sm w-100" type="submit"><i class="bi bi-plus"></i></button>
            </div>
        </div>
    </form>
</div>

{{-- Statement lines --}}
<div class="admin-card">
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Description</th>
                    <th>Reference</th>
                    <th class="text-end">Amount</th>
                    <th>Type</th>
                    <th class="text-end"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($lines as $line)
                    <tr>
                        <td>{{ $line->transaction_date }}</td>
                        <td>{{ $line->description }}</td>
                        <td class="text-muted">{{ $line->reference ?? '—' }}</td>
                        <td class="text-end">{{ number_format($line->amount, 2) }}</td>
                        <td>
                            @if ($line->type === 'deposit')
                                <span class="badge text-bg-success">Deposit</span>
                            @else
                                <span class="badge text-bg-danger">Withdrawal</span>
                            @endif
                        </td>
                        <td class="text-end">
                            <form method="POST" action="{{ route('accounting.bank-reconciliation.lines.destroy', ['lineId' => $line->id]) }}" class="d-inline" onsubmit="return confirm('Remove this line?')">
                                @csrf @method('DELETE')
                                <button class="btn btn-outline-danger btn-sm" type="submit"><i class="bi bi-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">No statement lines.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@endsection

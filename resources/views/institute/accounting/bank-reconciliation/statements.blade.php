@extends('layouts.standalone')

@section('title', 'Bank Statements — ' . $account->code . ' — AccumenAI')
@section('page_title', 'Bank Reconciliation')

@section('content')

<div class="standalone-heading">
    <h4>Bank Statements — {{ $account->code }} {{ $account->name }}</h4>
    <p>Manage bank statements for this account.</p>
</div>

@if (session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

<div class="admin-card mb-3">
    <h6 class="card-title">New Statement</h6>
    <form method="POST" action="{{ route('accounting.bank-reconciliation.statements.store', ['accountId' => $account->id]) }}">
        @csrf
        <div class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label mb-1">Statement Date</label>
                <input type="date" class="form-control form-control-sm" name="statement_date" value="{{ now()->toDateString() }}" required>
            </div>
            <div class="col-md-2">
                <button class="btn btn-primary btn-sm w-100" type="submit"><i class="bi bi-plus"></i> Create</button>
            </div>
        </div>
    </form>
</div>

<div class="admin-card">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($statements as $stmt)
                    <tr>
                        <td>{{ $stmt->statement_date }}</td>
                        <td>
                            @if ($stmt->status === 'reconciled')
                                <span class="badge text-bg-success">Reconciled</span>
                            @else
                                <span class="badge text-bg-warning">{{ ucfirst($stmt->status) }}</span>
                            @endif
                        </td>
                        <td class="text-end">
                            <a href="{{ route('accounting.bank-reconciliation.show', ['statementId' => $stmt->id]) }}" class="btn btn-outline-primary btn-sm">
                                <i class="bi bi-eye"></i> View
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="text-center text-muted py-4">No statements yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@endsection

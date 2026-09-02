@extends('layouts.standalone')

@section('title', 'Account Ledger — AccumenAI')
@section('page_title', 'Accounting Reports')

@push('styles')
<style>
    @media print {
        .topbar, .standalone-heading .btn, .filter-card { display: none !important; }
        .standalone-heading h4 { font-size: 1.4rem; }
        .admin-card { box-shadow: none !important; border: none !important; }
    }
</style>
@endpush

@section('content')

<div class="standalone-heading">
    <h4>Account Ledger</h4>
    <p>Transactions for one account with opening balance, running balance and closing balance over a date range.</p>
    <div class="d-flex gap-2 flex-wrap">
        <form class="filter-layout d-flex align-items-end gap-2 flex-wrap" method="GET" action="{{ route('accounting.reports.account-ledger') }}">
            <div>
                <label class="form-label mb-1">Account</label>
                <select class="form-select form-select-sm" name="account_id" required>
                    <option value="">— Select account —</option>
                    @foreach ($accounts as $account)
                        <option value="{{ $account->id }}" @selected((string) $accountId === (string) $account->id)>{{ $account->code }} — {{ $account->name }} ({{ ucfirst($account->type) }})</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="form-label mb-1">From</label>
                <input type="date" class="form-control form-control-sm" name="from" value="{{ $from }}">
            </div>
            <div>
                <label class="form-label mb-1">To</label>
                <input type="date" class="form-control form-control-sm" name="to" value="{{ $to }}">
            </div>
            <button class="btn btn-outline-primary btn-sm" type="submit"><i class="bi bi-funnel"></i> Apply</button>
        </form>
        @if ($statement !== null)
            <button class="btn btn-outline-secondary btn-sm" type="button" onclick="window.print()"><i class="bi bi-printer"></i> Print</button>
        @endif
    </div>
</div>

@if ($statement === null)
    <div class="admin-card">
        <p class="text-muted mb-0">Select an account to view its ledger.</p>
    </div>
@else
    <div class="admin-card">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
            <h6 class="card-title mb-0">
                {{ $statement['account']->code }} — {{ $statement['account']->name }}
                <span class="badge text-bg-light border ms-1">{{ ucfirst($statement['account']->type) }}</span>
            </h6>
            <div class="text-muted small">Opening: <span class="fw-semibold">{{ number_format($statement['opening'], 2) }}</span></div>
        </div>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Journal</th>
                        <th>Description</th>
                        <th>Created By</th>
                        <th class="text-end">Debit</th>
                        <th class="text-end">Credit</th>
                        <th class="text-end">Running balance</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($statement['lines'] as $row)
                        <tr>
                            <td>{{ $row->journal_date }}</td>
                            <td><a href="{{ route('finance.journals.show', ['journal' => $row->journal_id]) }}" class="text-decoration-none">{{ $row->journal_no }}</a></td>
                            <td>{{ $row->memo ?? $row->journal_description ?? '—' }}</td>
                            <td>{{ $row->created_by_name ?? '—' }}</td>
                            <td class="text-end">{{ $row->debit > 0 ? number_format((float) $row->debit, 2) : '—' }}</td>
                            <td class="text-end">{{ $row->credit > 0 ? number_format((float) $row->credit, 2) : '—' }}</td>
                            <td class="text-end fw-semibold">{{ number_format((float) $row->running_balance, 2) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">No transactions in this range.</td>
                        </tr>
                    @endforelse
                </tbody>
                <tfoot>
                    <tr class="fw-semibold">
                        <td colspan="4">Totals</td>
                        <td class="text-end">{{ number_format($statement['debit'], 2) }}</td>
                        <td class="text-end">{{ number_format($statement['credit'], 2) }}</td>
                        <td class="text-end"></td>
                    </tr>
                    <tr class="fw-semibold">
                        <td colspan="6">Closing balance</td>
                        <td class="text-end">{{ number_format($statement['closing'], 2) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
@endif

@endsection
@extends('layouts.standalone')
@section('title', 'Journal Audit Trail — AccumenAI')
@section('page_title', 'Reports')

@section('content')
<div class="standalone-heading">
    <h4>Journal Audit Trail</h4>
    <p>Posted journal entries with audit metadata for the selected period.</p>
    <button class="btn btn-outline-secondary btn-sm" type="button" onclick="window.print()"><i class="bi bi-printer"></i> Print</button>
</div>

<div class="filter-card mb-3">
    <form class="filter-layout" method="GET">
        <div class="filter-search-row align-items-end flex-wrap">
            <div class="filter-span">
                <label class="form-label mb-1">From</label>
                <input type="date" class="form-control form-control-sm" name="from" value="{{ $from }}">
            </div>
            <div class="filter-span">
                <label class="form-label mb-1">To</label>
                <input type="date" class="form-control form-control-sm" name="to" value="{{ $to }}">
            </div>
            <div class="filter-span">
                <button class="btn btn-outline-primary btn-sm mt-1" type="submit"><i class="bi bi-search"></i> Filter</button>
            </div>
        </div>
    </form>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="admin-card text-center">
            <small class="text-muted">Total Journals</small>
            <h4 class="mb-0 mt-1">{{ $summary['total_journals'] }}</h4>
        </div>
    </div>
    <div class="col-md-4">
        <div class="admin-card text-center">
            <small class="text-muted">Total Entries</small>
            <h4 class="mb-0 mt-1">{{ $summary['total_entries'] }}</h4>
        </div>
    </div>
    <div class="col-md-4">
        <div class="admin-card text-center">
            <small class="text-muted">Period</small>
            <h6 class="mb-0 mt-1">{{ $from }} to {{ $to }}</h6>
        </div>
    </div>
</div>

<div class="admin-card">
    <h6 class="card-title">Journal Entries</h6>
    @forelse ($trail as $item)
        <div class="border-bottom pb-3 mb-3">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <strong>{{ $item['journal_no'] }}</strong>
                    <span class="badge bg-secondary ms-2">{{ $item['type'] }}</span>
                    <span class="text-muted ms-2">{{ $item['journal_date'] }}</span>
                </div>
                <div class="text-end">
                    <small class="text-muted">Audit: {{ $item['audit_action'] ?? 'N/A' }}</small>
                    @if ($item['audit_actor'])
                        <br><small class="text-muted">By: User #{{ $item['audit_actor'] }}</small>
                    @endif
                    @if ($item['audit_ip'])
                        <br><small class="text-muted">IP: {{ $item['audit_ip'] }}</small>
                    @endif
                </div>
            </div>
            <p class="mb-2 mt-1">{{ $item['description'] }}</p>
            <table class="table table-sm mb-0">
                <thead><tr><th>Account</th><th>Description</th><th class="text-end">Debit</th><th class="text-end">Credit</th></tr></thead>
                <tbody>
                    @foreach ($item['entries'] as $entry)
                        <tr>
                            <td>{{ $entry['account_code'] }} — {{ $entry['account_name'] }}</td>
                            <td>{{ $entry['description'] }}</td>
                            <td class="text-end">{{ $entry['debit'] > 0 ? number_format($entry['debit'], 2) : '' }}</td>
                            <td class="text-end">{{ $entry['credit'] > 0 ? number_format($entry['credit'], 2) : '' }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="fw-bold">
                        <td colspan="2">Totals</td>
                        <td class="text-end">{{ number_format($item['total_debit'], 2) }}</td>
                        <td class="text-end">{{ number_format($item['total_credit'], 2) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    @empty
        <p class="text-muted mb-0">No journal entries found for the selected period.</p>
    @endforelse
</div>
@endsection

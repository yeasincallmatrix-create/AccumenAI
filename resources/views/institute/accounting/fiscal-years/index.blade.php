@extends('layouts.standalone')

@section('title', 'Fiscal Year End Closing — AccumenAI')
@section('page_title', 'Accounting')

@section('content')

<div class="standalone-heading">
    <h4>Fiscal Year End Closing</h4>
    <p>Closing a fiscal year posts a closing journal that sweeps the profit &amp; loss into Retained Earnings (code 3002), locks every period, closes the year and carries balance-sheet balances forward into the next fiscal year.</p>
</div>

<div class="admin-card">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>Fiscal year</th>
                    <th>Period</th>
                    <th>Status</th>
                    <th class="text-end">Net income</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($fiscalYears as $year)
                    <tr>
                        <td class="fw-semibold">{{ $year->name }}</td>
                        <td>{{ $year->start_date }} → {{ $year->end_date }}</td>
                        <td>
                            <span class="badge text-bg-{{ $year->status === 'open' ? 'success' : 'secondary' }}">{{ $year->status }}</span>
                            @if ($year->is_current)
                                <span class="badge text-bg-light border ms-1">Current</span>
                            @endif
                        </td>
                        <td class="text-end {{ ($year->net_income ?? 0) < 0 ? 'text-danger' : 'text-success' }}">{{ number_format($year->net_income ?? 0, 2) }}</td>
                        <td class="text-end">
                            @if ($year->status === 'open')
                                <form method="POST" action="{{ route('accounting.fiscal-years.close', $year) }}" class="d-inline" data-ajax-submit="1" data-confirm="Close {{ $year->name }}? A closing journal will be posted to Retained Earnings, all periods will be locked and balance-sheet balances carried forward to the next fiscal year. This requires the next fiscal year to exist.">
                                    @csrf
                                    <button class="btn btn-sm btn-outline-warning" type="submit"><i class="bi bi-archive me-1"></i>Close year</button>
                                </form>
                            @else
                                <form method="POST" action="{{ route('accounting.fiscal-years.reopen', $year) }}" class="d-inline" data-ajax-submit="1" data-confirm="Reopen {{ $year->name }}? Its periods will be open for postings again. Blocked if the following year already contains postings.">
                                    @csrf
                                    <button class="btn btn-sm btn-outline-success" type="submit"><i class="bi bi-arrow-counterclockwise me-1"></i>Reopen year</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center text-muted py-4">No fiscal years yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@if ($fiscalYears->hasPages())
    <div class="p-2">{{ $fiscalYears->links() }}</div>
@endif

@endsection
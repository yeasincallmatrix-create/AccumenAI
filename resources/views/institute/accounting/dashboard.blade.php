@extends('layouts.standalone')

@section('title', 'Accounting Dashboard — AccumenAI')
@section('page_title', 'Accounting')

@push('styles')
<style>
    .dash-chart {
        display: flex;
        gap: 6px;
        height: 180px;
        align-items: flex-end;
        border-bottom: 1px solid var(--bs-border-color, #dee2e6);
        padding: 0 4px;
    }
    .dash-month {
        flex: 1;
        display: flex;
        gap: 3px;
        align-items: flex-end;
        justify-content: center;
        height: 100%;
    }
    .dash-bar {
        width: 12px;
        border-radius: 3px 3px 0 0;
        min-height: 2px;
    }
    .dash-bar-rev { background: var(--bs-success, #198754); }
    .dash-bar-exp { background: var(--bs-danger, #dc3545); }
    .dash-labels {
        display: flex;
        gap: 6px;
        margin-top: 6px;
        padding: 0 4px;
    }
    .dash-month-label {
        flex: 1;
        text-align: center;
        font-size: 0.68rem;
        color: var(--bs-secondary-color, #6c757d);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .dash-profit-chart {
        position: relative;
        height: 180px;
        display: flex;
        align-items: center;
        border-bottom: 1px solid var(--bs-border-color, #dee2e6);
    }
    .dash-zero-line {
        position: absolute;
        left: 0;
        right: 0;
        top: 50%;
        border-top: 1px dashed rgba(128, 128, 128, 0.55);
        z-index: 1;
    }
    .dash-pcol {
        flex: 1;
        position: relative;
        height: 100%;
        display: flex;
        justify-content: center;
    }
    .dash-pbar {
        position: absolute;
        width: 16px;
        border-radius: 3px;
        z-index: 2;
    }
    .dash-pbar.pos { bottom: 50%; background: var(--bs-success, #198754); }
    .dash-pbar.neg { top: 50%; background: var(--bs-danger, #dc3545); }
    .dash-legend span { display: inline-flex; align-items: center; gap: 4px; font-size: 0.75rem; }
    .dash-legend i { width: 10px; height: 10px; display: inline-block; border-radius: 2px; }
</style>
@endpush

@section('content')

<div class="standalone-heading">
    <h4>Accounting Dashboard</h4>
    <p>Financial overview for {{ $institute->name }} — every figure is derived from posted journal entries and opening balances.
        @if ($branchId !== null && $branch)
            <span class="badge text-bg-primary ms-1">Branch: {{ $branch->name }}</span>
        @elseif ($branchId !== null)
            <span class="badge text-bg-primary ms-1">Selected branch</span>
        @else
            <span class="badge text-bg-light border ms-1">All branches</span>
        @endif
    </p>

    @livewire('accounting-dashboard-filter')
</div>

@php
    $hasMonthly = $monthly->isNotEmpty();
    $monthlyMax = max(1.0, (float) $monthly->max(fn ($m) => max($m['revenue'], $m['expense'])));
    $profitMax = max(1.0, (float) $monthly->max(fn ($m) => abs($m['profit'])));
@endphp

{{-- Financial summary --}}
<div class="row g-3 mb-3">
    <div class="col-6 col-md-4 col-xl-2">
        <a class="admin-card h-100 d-block text-decoration-none" href="{{ route('accounting.reports.profit-loss', ['from' => $range['from'], 'to' => $range['to']]) }}">
            <div class="small text-muted">Revenue</div>
            <div class="fs-5 fw-semibold">{{ number_format($summary['revenue'], 2) }}</div>
            <div class="small text-muted">{{ $baseCurrency }} · {{ $range['from'] }} → {{ $range['to'] }}</div>
        </a>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <a class="admin-card h-100 d-block text-decoration-none" href="{{ route('accounting.reports.profit-loss', ['from' => $range['from'], 'to' => $range['to']]) }}">
            <div class="small text-muted">Expenses</div>
            <div class="fs-5 fw-semibold text-danger">{{ number_format($summary['expenses'], 2) }}</div>
            <div class="small text-muted">{{ $baseCurrency }}</div>
        </a>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <a class="admin-card h-100 d-block text-decoration-none" href="{{ route('accounting.reports.profit-loss', ['from' => $range['from'], 'to' => $range['to']]) }}">
            <div class="small text-muted">Net profit / (loss)</div>
            <div class="fs-5 fw-semibold {{ $summary['net'] < 0 ? 'text-danger' : 'text-success' }}">{{ number_format($summary['net'], 2) }}</div>
            <div class="small text-muted">{{ $baseCurrency }}</div>
        </a>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <a class="admin-card h-100 d-block text-decoration-none" href="{{ route('accounting.reports.receivables') }}">
            <div class="small text-muted">Accounts receivable</div>
            <div class="fs-5 fw-semibold">{{ number_format($receivableTotal, 2) }}</div>
            <div class="small text-muted">{{ $baseCurrency }} · as of {{ $range['to'] }}</div>
        </a>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <a class="admin-card h-100 d-block text-decoration-none" href="{{ route('accounting.reports.payables') }}">
            <div class="small text-muted">Accounts payable</div>
            <div class="fs-5 fw-semibold text-danger">{{ number_format($payableTotal, 2) }}</div>
            <div class="small text-muted">{{ $baseCurrency }} · as of {{ $range['to'] }}</div>
        </a>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <a class="admin-card h-100 d-block text-decoration-none" href="{{ route('accounting.reports.cash-bank') }}">
            <div class="small text-muted">Cash &amp; bank balance</div>
            <div class="fs-5 fw-semibold">{{ number_format($cash['total_closing'], 2) }}</div>
            <div class="small text-muted">{{ $baseCurrency }} · as of {{ $range['to'] }}</div>
        </a>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="admin-card h-100">
            <div class="small text-muted">Budget utilization</div>
            <div class="fs-5 fw-semibold">{{ $budgetUtilization['utilization_pct'] }}%</div>
            <div class="small text-muted">{{ $baseCurrency }} {{ number_format($budgetUtilization['total_actual'], 2) }} / {{ number_format($budgetUtilization['total_budget'], 2) }}</div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="admin-card h-100">
            <div class="small text-muted">Pending approvals</div>
            <div class="fs-5 fw-semibold {{ $pendingApprovals > 0 ? 'text-warning' : '' }}">{{ $pendingApprovals }}</div>
            <div class="small text-muted">requests awaiting action</div>
        </div>
    </div>
</div>

{{-- Charts --}}
<div class="row g-3 mb-3">
    <div class="col-lg-6">
        <div class="admin-card h-100">
            <h6 class="card-title">Revenue vs Expense</h6>
            @if ($hasMonthly)
                <div class="dash-chart">
                    @foreach ($monthly as $m)
                        <div class="dash-month" title="{{ $m['label'] }} — Revenue {{ number_format($m['revenue'], 2) }} / Expense {{ number_format($m['expense'], 2) }}">
                            <div class="dash-bar dash-bar-rev" style="height: {{ round($m['revenue'] / $monthlyMax * 100, 1) }}%"></div>
                            <div class="dash-bar dash-bar-exp" style="height: {{ round($m['expense'] / $monthlyMax * 100, 1) }}%"></div>
                        </div>
                    @endforeach
                </div>
                <div class="dash-labels">
                    @foreach ($monthly as $m)
                        <div class="dash-month-label">{{ $m['label'] }}</div>
                    @endforeach
                </div>
                <div class="dash-legend mt-2">
                    <span class="me-3"><i style="background: var(--bs-success, #198754)"></i>Revenue</span>
                    <span><i style="background: var(--bs-danger, #dc3545)"></i>Expense</span>
                </div>
            @else
                <p class="text-muted mb-0">No activity in this period.</p>
            @endif
        </div>
    </div>
    <div class="col-lg-6">
        <div class="admin-card h-100">
            <h6 class="card-title">Profit Trend</h6>
            @if ($hasMonthly)
                <div class="dash-profit-chart">
                    <div class="dash-zero-line"></div>
                    @foreach ($monthly as $m)
                        @php $ph = round(abs($m['profit']) / $profitMax * 70, 1); @endphp
                        <div class="dash-pcol" title="{{ $m['label'] }} — {{ number_format($m['profit'], 2) }}">
                            @if ($m['profit'] >= 0)
                                <div class="dash-pbar pos" style="height: {{ $ph }}px"></div>
                            @else
                                <div class="dash-pbar neg" style="height: {{ $ph }}px"></div>
                            @endif
                        </div>
                    @endforeach
                </div>
                <div class="dash-labels">
                    @foreach ($monthly as $m)
                        <div class="dash-month-label">{{ $m['label'] }}</div>
                    @endforeach
                </div>
                <div class="dash-legend mt-2">
                    <span class="me-3"><i style="background: var(--bs-success, #198754)"></i>Profit</span>
                    <span><i style="background: var(--bs-danger, #dc3545)"></i>Loss</span>
                </div>
            @else
                <p class="text-muted mb-0">No activity in this period.</p>
            @endif
        </div>
    </div>
</div>

{{-- Cash flows + AR/AP aging --}}
<div class="row g-3 mb-3">
    <div class="col-lg-6">
        <div class="admin-card h-100">
            <h6 class="card-title">Cash &amp; Bank Flows
                <a class="btn btn-sm btn-link p-0 ms-1" href="{{ route('accounting.reports.cash-bank') }}">view report</a>
            </h6>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Account</th>
                            <th class="text-end">Opening</th>
                            <th class="text-end">Inflow</th>
                            <th class="text-end">Outflow</th>
                            <th class="text-end">Closing</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($cash['accounts'] as $account)
                            <tr>
                                <td>
                                    {{ $account->code }} — {{ $account->name }}
                                    @if ($account->is_cash)<span class="badge text-bg-info ms-1">Cash</span>@endif
                                    @if ($account->is_bank)<span class="badge text-bg-primary ms-1">Bank</span>@endif
                                </td>
                                <td class="text-end">{{ number_format($account->opening, 2) }}</td>
                                <td class="text-end text-success">{{ number_format($account->inflow, 2) }}</td>
                                <td class="text-end text-danger">{{ number_format($account->outflow, 2) }}</td>
                                <td class="text-end fw-semibold">{{ number_format($account->closing, 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-muted py-4">No cash/bank accounts.</td></tr>
                        @endforelse
                    </tbody>
                    <tfoot>
                        <tr class="fw-semibold">
                            <td>Total</td>
                            <td class="text-end">{{ number_format($cash['total_opening'], 2) }}</td>
                            <td class="text-end text-success">{{ number_format($cash['total_inflow'], 2) }}</td>
                            <td class="text-end text-danger">{{ number_format($cash['total_outflow'], 2) }}</td>
                            <td class="text-end">{{ number_format($cash['total_closing'], 2) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="row g-3">
            <div class="col-md-6">
                <div class="admin-card h-100">
                    <h6 class="card-title">Receivables Aging
                        <a class="btn btn-sm btn-link p-0 ms-1" href="{{ route('accounting.reports.receivables') }}">view report</a>
                    </h6>
                    <table class="table table-sm align-middle mb-0">
                        <tbody>
                            <tr><td>Total</td><td class="text-end fw-semibold">{{ number_format($arpAging['customers']['total'], 2) }}</td></tr>
                            <tr><td>Current (1–30 days)</td><td class="text-end">{{ number_format($arpAging['customers']['current'], 2) }}</td></tr>
                            <tr><td>31–60 days</td><td class="text-end">{{ number_format($arpAging['customers']['b31_60'], 2) }}</td></tr>
                            <tr><td>61–90 days</td><td class="text-end">{{ number_format($arpAging['customers']['b61_90'], 2) }}</td></tr>
                            <tr><td>90+ days</td><td class="text-end">{{ number_format($arpAging['customers']['b91_plus'], 2) }}</td></tr>
                            <tr class="fw-semibold text-danger"><td>Overdue</td><td class="text-end">{{ number_format($arpAging['customers']['overdue'], 2) }}</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="col-md-6">
                <div class="admin-card h-100">
                    <h6 class="card-title">Payables Aging
                        <a class="btn btn-sm btn-link p-0 ms-1" href="{{ route('accounting.reports.payables') }}">view report</a>
                    </h6>
                    <table class="table table-sm align-middle mb-0">
                        <tbody>
                            <tr><td>Total</td><td class="text-end fw-semibold">{{ number_format($arpAging['suppliers']['total'], 2) }}</td></tr>
                            <tr><td>Current (1–30 days)</td><td class="text-end">{{ number_format($arpAging['suppliers']['current'], 2) }}</td></tr>
                            <tr><td>31–60 days</td><td class="text-end">{{ number_format($arpAging['suppliers']['b31_60'], 2) }}</td></tr>
                            <tr><td>61–90 days</td><td class="text-end">{{ number_format($arpAging['suppliers']['b61_90'], 2) }}</td></tr>
                            <tr><td>90+ days</td><td class="text-end">{{ number_format($arpAging['suppliers']['b91_plus'], 2) }}</td></tr>
                            <tr class="fw-semibold text-danger"><td>Overdue</td><td class="text-end">{{ number_format($arpAging['suppliers']['overdue'], 2) }}</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Top accounts --}}
<div class="row g-3 mb-3">
    @foreach ([
        'income' => ['Top Revenue Accounts', 'text-success'],
        'expense' => ['Top Expense Accounts', 'text-danger'],
        'debit' => ['Largest Debit Activity', ''],
        'credit' => ['Largest Credit Activity', ''],
    ] as $key => [$title, $amountClass])
        <div class="col-md-6 col-xl-3">
            <div class="admin-card h-100">
                <h6 class="card-title">{{ $title }}</h6>
                @php $rows = $topAccounts[$key]; @endphp
                @if ($rows->isEmpty())
                    <p class="text-muted mb-0">No activity.</p>
                @else
                    <table class="table table-sm align-middle mb-0">
                        <tbody>
                            @foreach ($rows as $row)
                                <tr>
                                    <td class="text-muted">{{ $row->code }}</td>
                                    <td class="text-truncate">{{ $row->name }}</td>
                                    <td class="text-end fw-semibold {{ $amountClass }}">{{ number_format($row->amount, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>
    @endforeach
</div>

{{-- Period status + recent activity --}}
<div class="row g-3">
    <div class="col-lg-4">
        <div class="admin-card mb-3">
            <h6 class="card-title">Current Period</h6>
            @if ($periodStatus['fiscal_year'] === null)
                <p class="text-warning mb-0"><i class="bi bi-exclamation-triangle-fill me-1"></i>No open/current fiscal year exists.</p>
            @else
                <div class="d-flex justify-content-between mb-1">
                    <span class="text-muted">Fiscal year</span>
                    <span class="fw-semibold">{{ $periodStatus['fiscal_year']->name }}</span>
                </div>
                <div class="d-flex justify-content-between mb-1">
                    <span class="text-muted">Current period</span>
                    <span class="fw-semibold">{{ $periodStatus['period']?->name ?? '—' }}</span>
                </div>
                <div class="d-flex justify-content-between mb-1">
                    <span class="text-muted">Status</span>
                    @if ($periodStatus['period'] === null)
                        <span class="badge text-bg-warning">No open period</span>
                    @elseif ($periodStatus['period']->isOpen())
                        <span class="badge text-bg-success">OPEN</span>
                    @else
                        <span class="badge text-bg-danger">CLOSED</span>
                    @endif
                </div>
            @endif
        </div>
        @php $yearEnd = $periodStatus['year_end']; @endphp
        <div class="admin-card">
            <h6 class="card-title">Year-End Status</h6>
            @if ($yearEnd['year'] === null)
                <p class="text-warning mb-0"><i class="bi bi-exclamation-triangle-fill me-1"></i>No fiscal year to report on.</p>
            @else
                <div class="d-flex justify-content-between mb-1">
                    <span class="text-muted">Fiscal year</span>
                    <span class="fw-semibold">{{ $yearEnd['year']->name }}</span>
                </div>
                <div class="d-flex justify-content-between mb-1">
                    <span class="text-muted">Status</span>
                    @if ($yearEnd['year']->isClosed())
                        <span class="badge text-bg-danger">CLOSED</span>
                    @else
                        <span class="badge text-bg-success">OPEN</span>
                    @endif
                </div>
                <div class="d-flex justify-content-between mb-1">
                    <span class="text-muted">Periods closed</span>
                    <span class="fw-semibold">{{ $yearEnd['closed_periods'] }} / {{ $yearEnd['total_periods'] }}</span>
                </div>
                @if (! $yearEnd['year']->isClosed() && $yearEnd['days_to_end'] !== null)
                    <div class="small text-muted mt-1">
                        <i class="bi bi-calendar-event me-1"></i>{{ $yearEnd['days_to_end'] }} day(s) until year end.
                    </div>
                @endif
            @endif
        </div>
    </div>
    <div class="col-lg-8">
        <div class="admin-card h-100">
            <h6 class="card-title">Recent Journal Activity</h6>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Journal No.</th>
                            <th>Description</th>
                            <th class="text-end">Debit</th>
                            <th class="text-end">Credit</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($recentJournals as $journal)
                            <tr>
                                <td>{{ $journal->journal_date?->toDateString() }}</td>
                                <td>
                                    <a href="{{ route('finance.journals.show', $journal->id) }}" class="text-decoration-none">{{ $journal->journal_no }}</a>
                                </td>
                                <td class="text-truncate" style="max-width: 240px;" title="{{ $journal->description }}">{{ $journal->description ?: '—' }}</td>
                                <td class="text-end">{{ number_format($journal->debit, 2) }}</td>
                                <td class="text-end">{{ number_format($journal->credit, 2) }}</td>
                                <td>
                                    <span class="badge text-bg-success">{{ $journal->status }}</span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">No posted journals in this period.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

@endsection


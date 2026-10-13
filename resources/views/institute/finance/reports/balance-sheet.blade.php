@extends('layouts.institute')

@section('title', 'Balance Sheet — AccumenAI')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/reports.css') }}">
@endpush

@section('content')

@php
    $lPlusE = $statement['total_liabilities'] + $statement['total_equity'];
    $diff = round($statement['total_assets'] - $lPlusE, 2);
    $ok = abs($diff) < 0.01;
    $fmt = fn ($value) => $value < 0
        ? '('.number_format(abs((float) $value), 2).')'
        : number_format((float) $value, 2);
    $branchName = data_get($institute, 'branch.name');
@endphp

<div class="container-fluid py-3">
    <div class="rpt-page">

        <form class="rpt-filters rpt-no-print d-flex flex-wrap gap-3 align-items-end p-3 mb-3" method="GET" action="{{ url()->current() }}">
            <div>
                <label class="form-label mb-1" for="rpt-as-of">As of</label>
                <x-tdate-input id="rpt-as-of" class="form-control form-control-sm" name="as_of_date" :value="$asOf" />
            </div>
            <div>
                <label class="form-label mb-1" for="rpt-fy">Fiscal year</label>
                <select class="form-select form-select-sm" id="rpt-fy" name="fiscal_year_id">
                    <option value="">All years (to date)</option>
                    @foreach ($fiscalYears as $fy)
                        <option value="{{ $fy->id }}" @selected((string) $fiscalYearId === (string) $fy->id)>{{ $fy->name }}</option>
                    @endforeach
                </select>
            </div>
            <button class="btn btn-primary btn-sm" type="submit"><i class="bi bi-funnel"></i> Apply</button>
            <button class="btn btn-outline-secondary btn-sm" type="button" onclick="window.print()"><i class="bi bi-printer"></i> Print</button>
        </form>

        <div class="admin-card p-4">

            {{-- Zone 1 — Header --}}
            <header class="rpt-header">
                <div class="rpt-header__org">
                    {{ $institute->name }}
                    @if ($branchName)
                        <span class="rpt-header__branch">{{ $branchName }}</span>
                    @endif
                </div>
                <div class="rpt-header__center">
                    <h2 class="rpt-header__title">Balance Sheet</h2>
                </div>
                <div class="rpt-header__right">
                    <div><span class="rpt-header__meta">As of:</span> {{ \Illuminate\Support\Carbon::parse($asOf)->format('d M Y') }}</div>
                    <div><span class="rpt-header__meta">Currency:</span> {{ config('app.currency', 'BDT') }}</div>
                </div>
            </header>

            {{-- Zone 3 — Statement table --}}
            <div class="table-responsive">
                <table class="rpt-table">
                    <colgroup>
                        <col class="rpt-col-code">
                        <col>
                        <col class="rpt-col-amount">
                    </colgroup>

                    {{-- ASSETS --}}
                    <tbody class="rpt-section">
                        <tr>
                            <th class="rpt-section__title" colspan="3">Assets</th>
                        </tr>
                        @forelse ($statement['assets'] as $row)
                            <tr class="rpt-row">
                                <td class="rpt-code">{{ $row->code }}</td>
                                <td>{{ $row->name }}</td>
                                <td class="rpt-amount {{ $row->balance < 0 ? 'rpt-neg' : '' }}">{{ $fmt($row->balance) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td class="text-secondary small py-2 ps-2" colspan="3">No asset balances.</td>
                            </tr>
                        @endforelse
                        <tr class="rpt-subtotal">
                            <td colspan="2">Total Assets</td>
                            <td class="rpt-amount">{{ number_format($statement['total_assets'], 2) }}</td>
                        </tr>
                    </tbody>

                    {{-- LIABILITIES --}}
                    <tbody class="rpt-section">
                        <tr>
                            <th class="rpt-section__title" colspan="3">Liabilities</th>
                        </tr>
                        @forelse ($statement['liabilities'] as $row)
                            <tr class="rpt-row">
                                <td class="rpt-code">{{ $row->code }}</td>
                                <td>{{ $row->name }}</td>
                                <td class="rpt-amount {{ $row->balance < 0 ? 'rpt-neg' : '' }}">{{ $fmt($row->balance) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td class="text-secondary small py-2 ps-2" colspan="3">No liability balances.</td>
                            </tr>
                        @endforelse
                        <tr class="rpt-subtotal">
                            <td colspan="2">Total Liabilities</td>
                            <td class="rpt-amount">{{ number_format($statement['total_liabilities'], 2) }}</td>
                        </tr>
                    </tbody>

                    {{-- EQUITY --}}
                    <tbody class="rpt-section">
                        <tr>
                            <th class="rpt-section__title" colspan="3">Equity</th>
                        </tr>
                        @forelse ($statement['equity'] as $row)
                            <tr class="rpt-row">
                                <td class="rpt-code">{{ $row->code }}</td>
                                <td>{{ $row->name }}</td>
                                <td class="rpt-amount {{ $row->balance < 0 ? 'rpt-neg' : '' }}">{{ $fmt($row->balance) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td class="text-secondary small py-2 ps-2" colspan="3">No equity balances.</td>
                            </tr>
                        @endforelse
                        <tr class="rpt-row">
                            <td class="rpt-code">—</td>
                            <td>Net income (to date)</td>
                            <td class="rpt-amount {{ $statement['net_income'] < 0 ? 'rpt-neg' : '' }}">{{ $fmt($statement['net_income']) }}</td>
                        </tr>
                        <tr class="rpt-subtotal">
                            <td colspan="2">Total Equity</td>
                            <td class="rpt-amount">{{ number_format($statement['total_equity'], 2) }}</td>
                        </tr>
                    </tbody>

                    {{-- GRAND TOTAL --}}
                    <tbody>
                        <tr class="rpt-grandtotal">
                            <td colspan="2">TOTAL LIABILITIES + EQUITY</td>
                            <td class="rpt-amount">{{ number_format($lPlusE, 2) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            {{-- Zone 4 — Balance check banner --}}
            @if ($ok)
                <div class="rpt-check rpt-check--ok">
                    <i class="bi bi-check-circle-fill"></i>
                    <span>Balanced: Assets ({{ number_format($statement['total_assets'], 2) }}) = Liabilities + Equity ({{ number_format($lPlusE, 2) }})</span>
                </div>
            @else
                <div class="rpt-check rpt-check--bad">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <span>Out of balance by {{ number_format($diff, 2) }}. Assets ({{ number_format($statement['total_assets'], 2) }}) ≠ Liabilities + Equity ({{ number_format($lPlusE, 2) }}). Check opening balances and unposted journals.</span>
                </div>
            @endif

            {{-- Zone 5 — Footer --}}
            <footer class="rpt-footer">
                <span>Generated: {{ now()->format('d M Y H:i') }}</span>
                <span>System-generated statement — read-only.</span>
            </footer>

        </div>
    </div>
</div>

@endsection

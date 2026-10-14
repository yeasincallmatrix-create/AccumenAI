@extends('layouts.institute')

@section('title', 'Profit & Loss — AccumenAI')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/reports.css') }}">
@endpush

@section('content')

@php
    $fmt = fn ($value) => $value < 0
        ? '('.number_format(abs((float) $value), 2).')'
        : number_format((float) $value, 2);
    $branchName = data_get($institute, 'branch.name');
@endphp

<div class="container-fluid py-3">
    <div class="rpt-page">

        <form class="rpt-filters rpt-no-print d-flex flex-wrap gap-3 align-items-end p-3 mb-3" method="GET" action="{{ url()->current() }}" style="background: var(--bs-tertiary-bg, #f8f9fa); border-radius: .5rem;">
            <div>
                <label class="form-label mb-1" for="rpt-pl-from">From</label>
                <x-tdate-input id="rpt-pl-from" class="form-control form-control-sm" name="from" :value="$from" />
            </div>
            <div>
                <label class="form-label mb-1" for="rpt-pl-to">To</label>
                <x-tdate-input id="rpt-pl-to" class="form-control form-control-sm" name="to" :value="$to" />
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
                    <h2 class="rpt-header__title">Profit &amp; Loss</h2>
                </div>
                <div class="rpt-header__right">
                    <div>
                        <span class="rpt-header__meta">From:</span> {{ \Illuminate\Support\Carbon::parse($from)->format('d M Y') }}
                        <span class="rpt-header__meta">to:</span> {{ \Illuminate\Support\Carbon::parse($to)->format('d M Y') }}
                    </div>
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

                    {{-- INCOME --}}
                    <tbody class="rpt-section">
                        <tr>
                            <th class="rpt-section__title" colspan="3">Income</th>
                        </tr>
                        @forelse ($statement['income'] as $row)
                            <tr class="rpt-row">
                                <td class="rpt-code">{{ $row->code }}</td>
                                <td>{{ $row->name }}</td>
                                <td class="rpt-amount">{{ $fmt($row->balance) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td class="text-secondary small py-2 ps-2" colspan="3">No income recorded.</td>
                            </tr>
                        @endforelse
                        <tr class="rpt-subtotal">
                            <td colspan="2">Total Income</td>
                            <td class="rpt-amount">{{ number_format($statement['total_income'], 2) }}</td>
                        </tr>
                    </tbody>

                    {{-- EXPENSES --}}
                    <tbody class="rpt-section">
                        <tr>
                            <th class="rpt-section__title" colspan="3">Expenses</th>
                        </tr>
                        @forelse ($statement['expense'] as $row)
                            <tr class="rpt-row">
                                <td class="rpt-code">{{ $row->code }}</td>
                                <td>{{ $row->name }}</td>
                                <td class="rpt-amount">{{ $fmt($row->balance) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td class="text-secondary small py-2 ps-2" colspan="3">No expenses recorded.</td>
                            </tr>
                        @endforelse
                        <tr class="rpt-subtotal">
                            <td colspan="2">Total Expenses</td>
                            <td class="rpt-amount">{{ number_format($statement['total_expense'], 2) }}</td>
                        </tr>
                    </tbody>

                    {{-- NET PROFIT / (LOSS) --}}
                    <tbody>
                        <tr class="rpt-grandtotal">
                            <td colspan="2">NET PROFIT / (LOSS)</td>
                            <td class="rpt-amount {{ $statement['net'] < 0 ? 'rpt-neg' : '' }}">{{ $fmt($statement['net']) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            {{-- Zone 5 — Footer --}}
            <footer class="rpt-footer">
                <span>Generated: {{ now()->format('d M Y H:i') }}</span>
                <span>System-generated statement — read-only.</span>
            </footer>

        </div>
    </div>
</div>

@endsection

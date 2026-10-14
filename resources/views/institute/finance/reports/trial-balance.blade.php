@extends('layouts.institute')

@section('title', 'Trial Balance — AccumenAI')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/reports.css') }}">
@endpush

@section('content')

@php
    $fmt = fn ($value) => $value < 0
        ? '('.number_format(abs((float) $value), 2).')'
        : number_format((float) $value, 2);
    $branchName = data_get($institute, 'branch.name');
    $debit = 0.0;
    $credit = 0.0;
    foreach ($rows as $row) {
        $debit += (float) $row->debit;
        $credit += (float) $row->credit;
    }
    $tbDiff = round($debit - $credit, 2);
    $tbOk = abs($tbDiff) < 0.01;
@endphp

<div class="container-fluid py-3">
    <div class="rpt-page">

        <form class="rpt-filters rpt-no-print d-flex flex-wrap gap-3 align-items-end p-3 mb-3" method="GET" action="{{ url()->current() }}" style="background: var(--bs-tertiary-bg, #f8f9fa); border-radius: .5rem;">
            <div>
                <label class="form-label mb-1" for="rpt-tb-as-of">As of</label>
                <x-tdate-input id="rpt-tb-as-of" class="form-control form-control-sm" name="as_of_date" :value="$asOf" />
            </div>
            <div>
                <label class="form-label mb-1" for="rpt-tb-fy">Fiscal year</label>
                <select class="form-select form-select-sm" id="rpt-tb-fy" name="fiscal_year_id">
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
                    <h2 class="rpt-header__title">Trial Balance</h2>
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
                        <col style="width: 110px;">
                        <col class="rpt-col-amount">
                        <col class="rpt-col-amount">
                        <col class="rpt-col-amount">
                    </colgroup>
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Account</th>
                            <th>Type</th>
                            <th class="rpt-amount">Debit</th>
                            <th class="rpt-amount">Credit</th>
                            <th class="rpt-amount">Balance</th>
                        </tr>
                    </thead>
                    <tbody class="rpt-section">
                        @forelse ($rows as $row)
                            <tr class="rpt-row">
                                <td class="rpt-code">{{ $row->code }}</td>
                                <td>{{ $row->name }}</td>
                                <td><span class="badge text-bg-light border">{{ ucfirst($row->type) }}</span></td>
                                <td class="rpt-amount">{{ $fmt($row->debit) }}</td>
                                <td class="rpt-amount">{{ $fmt($row->credit) }}</td>
                                <td class="rpt-amount fw-semibold {{ $row->balance < 0 ? 'rpt-neg' : '' }}">{{ $fmt($row->balance) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td class="text-secondary small py-2 ps-2" colspan="6">No entries yet.</td>
                            </tr>
                        @endforelse
                        @if ($rows->isNotEmpty())
                            <tr class="rpt-subtotal">
                                <td colspan="3">Totals</td>
                                <td class="rpt-amount">{{ number_format($debit, 2) }}</td>
                                <td class="rpt-amount">{{ number_format($credit, 2) }}</td>
                                <td class="rpt-amount">{{ number_format($tbDiff, 2) }}</td>
                            </tr>
                        @endif
                    </tbody>
                </table>
            </div>

            {{-- Zone 4 — Debit = Credit check --}}
            @if ($tbOk)
                <div class="rpt-check rpt-check--ok">
                    <i class="bi bi-check-circle-fill"></i>
                    <span>Balanced: Total debits ({{ number_format($debit, 2) }}) = Total credits ({{ number_format($credit, 2) }})</span>
                </div>
            @else
                <div class="rpt-check rpt-check--bad">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <span>Out of balance by {{ number_format($tbDiff, 2) }}. Total debits ({{ number_format($debit, 2) }}) ≠ Total credits ({{ number_format($credit, 2) }}). Check opening balances and unposted journals.</span>
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

@extends('layouts.standalone')

@section('title', 'General Ledger — AccumenAI')
@section('page_title', 'Finance Reports')

@push('styles')
<style>
    @media print {
        .topbar, .standalone-heading .btn, .filter-card { display: none !important; }
        .standalone-heading h4 { font-size: 1.4rem; }
        .admin-card { box-shadow: none !important; border: none !important; }
    }
    .sortable { cursor: pointer; user-select: none; }
    .sortable:hover { background-color: rgba(0,0,0,0.05); }
</style>
@endpush

@section('content')

<div class="standalone-heading">
    <h4>General Ledger</h4>
    <p>Journal lines with running balances, optionally filtered to one account and a date range.</p>
    <div class="d-flex gap-2 flex-wrap">
        <button class="btn btn-outline-secondary btn-sm" type="button" onclick="window.print()"><i class="bi bi-printer"></i> Print</button>
    </div>
</div>

@livewire('general-ledger-list')

@endsection

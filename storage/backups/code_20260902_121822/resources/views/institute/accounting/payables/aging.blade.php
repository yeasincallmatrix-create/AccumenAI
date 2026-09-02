@extends('layouts.standalone')

@section('title', 'Payables Aging — AccumenAI')
@section('page_title', 'Payables')

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
    <h4>Payables Aging Report</h4>
    <p>Supplier balances by aging period.</p>
    <button class="btn btn-outline-secondary btn-sm" type="button" onclick="window.print()"><i class="bi bi-printer"></i> Print</button>
</div>

@livewire('payable-list', ['viewMode' => 'aging'])

@endsection

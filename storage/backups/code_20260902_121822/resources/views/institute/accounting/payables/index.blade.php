@extends('layouts.standalone')

@section('title', 'Accounts Payable — AccumenAI')
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
    <h4>Accounts Payable</h4>
    <p>Outstanding supplier balances derived from posted journal entries, with aging buckets based on journal date.</p>
    <button class="btn btn-outline-secondary btn-sm" type="button" onclick="window.print()"><i class="bi bi-printer"></i> Print</button>
</div>

@livewire('payable-list')

@endsection

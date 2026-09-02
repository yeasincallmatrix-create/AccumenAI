@extends('layouts.standalone')

@section('title', 'Bank Reconciliation — AccumenAI')
@section('page_title', 'Bank Reconciliation')

@push('styles')
<style>
    .sortable { cursor: pointer; user-select: none; }
    .sortable:hover { background-color: rgba(0,0,0,0.05); }
</style>
@endpush

@section('content')

<div class="standalone-heading">
    <h4>Bank Reconciliation</h4>
    <p>Select a bank account to view statements and reconcile transactions.</p>
</div>

@livewire('bank-reconciliation-list')

@endsection

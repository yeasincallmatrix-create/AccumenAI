@extends('layouts.standalone')

@section('title', 'Payments — AccumenAI')
@section('page_title', 'Finance')

@section('content')

<div class="standalone-heading">
    <h4>Payments</h4>
    <p>AR receipts recorded against invoices. Every payment posts a receipt journal (debit cash / credit Accounts Receivable).</p>
</div>

@livewire('payment-list')

@endsection

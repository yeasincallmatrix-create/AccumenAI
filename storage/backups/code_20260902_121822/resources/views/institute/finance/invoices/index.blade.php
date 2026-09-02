@extends('layouts.standalone')

@section('title', 'Invoices — AccumenAI')
@section('page_title', 'Finance')

@section('content')

<div class="standalone-heading">
    <h4>Invoices</h4>
    <p>Accounts-receivable documents. Creating an invoice posts a sale journal to the ledger; payments reduce the outstanding balance.</p>
    <a href="{{ route('finance.invoices.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>New Invoice</a>
</div>

@livewire('invoice-list')

@endsection

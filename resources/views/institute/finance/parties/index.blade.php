@extends('layouts.standalone')

@section('title', 'Parties — AccumenAI')
@section('page_title', 'Finance')

@section('content')

<div class="standalone-heading">
    <h4>Parties</h4>
    <p>Customers, suppliers and both — the counterparties behind receivables and payables. Duplicate phone numbers are blocked within the same scope and type.</p>
    <a href="{{ route('finance.parties.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>New Party</a>
</div>

@livewire('party-list')

@endsection

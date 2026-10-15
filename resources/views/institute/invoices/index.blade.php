@extends('layouts.institute')

@section('title', 'All Invoices — AccumenAI')

@section('content')

<div class="standalone-heading">
    <h4>All Invoices</h4>
    <p>Unified view of Finance and Sales invoices. Use the source filter to narrow the list.</p>
</div>

@livewire('unified-invoice-list')

@endsection

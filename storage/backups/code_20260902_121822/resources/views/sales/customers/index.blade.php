@extends('layouts.standalone')

@section('title', 'Customers — AccumenAI')
@section('page_title', 'Customers')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-people me-2"></i>Customers</h4>
    <a class="btn btn-primary btn-sm rounded-pill" href="{{ route('sales.customers.manage.create') }}"><i class="bi bi-plus-lg me-1"></i>New Customer</a>
</div>

@livewire('customer-list')
@endsection

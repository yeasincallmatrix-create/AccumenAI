@extends('layouts.institute')

@section('title', 'Lab Test Details — AccumenAI')

@section('content')
<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div class="page-header-text">
        <h4 class="page-header-title">{{ $test->display_name }}</h4>
    </div>
    <div class="page-header-actions">
        <a class="btn btn-warning" href="{{ route('medical.lab.tests.edit', $test) }}">
            <i class="bi bi-pencil me-1"></i>Edit
        </a>
        <a class="btn btn-secondary" href="{{ route('medical.lab.tests.index') }}">
            <i class="bi bi-arrow-left me-1"></i>Back
        </a>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <p><strong>Code:</strong> {{ $test->code }}</p>
        <p><strong>Name:</strong> {{ $test->name }}</p>
        <p><strong>Category:</strong> {{ $test->category ?? '—' }}</p>
        <p><strong>Normal Range:</strong> {{ $test->normal_range ?? '—' }}</p>
        <p><strong>Unit:</strong> {{ $test->unit ?? '—' }}</p>
        <p><strong>Price:</strong> ৳{{ number_format($test->price, 2) }}</p>
        <p><strong>Description:</strong> {{ $test->description ?? '—' }}</p>
        <p class="mb-0"><strong>Status:</strong>
            @if($test->is_active)
                <span class="badge bg-success">Active</span>
            @else
                <span class="badge bg-danger">Inactive</span>
            @endif
        </p>
    </div>
</div>
@endsection

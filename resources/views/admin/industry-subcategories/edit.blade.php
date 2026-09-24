@extends('layouts.admin')

@section('title', 'Edit ' . ($subcategory->name ?? '') . ' — AccumenAI')

@section('content')
<nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none">Dashboard</a></li>
        <li class="breadcrumb-item"><a href="{{ route('admin.industry-subcategories.index') }}" class="text-decoration-none">Sub-Categories</a></li>
        <li class="breadcrumb-item active">Edit {{ $subcategory->name }}</li>
    </ol>
</nav>

<div class="page-header">
    <div class="page-header-text">
        <h4 class="page-header-title">Edit — {{ $subcategory->name }}</h4>
        <p class="page-header-desc">Key: <code>{{ $subcategory->subcategory_key }}</code> (renaming the key re-points Layer 3 for tenants using it)</p>
    </div>
    <div class="page-header-actions">
        <a class="btn btn-outline-secondary btn-sm" href="{{ route('admin.industry-subcategories.show', $subcategory->id) }}">
            <i class="bi bi-arrow-left"></i> Back
        </a>
    </div>
</div>

<form method="POST" action="{{ route('admin.industry-subcategories.update', $subcategory->id) }}">
    @method('PUT')
    @include('admin.industry-subcategories._form', ['submitLabel' => 'Update Sub-Category'])
</form>
@endsection

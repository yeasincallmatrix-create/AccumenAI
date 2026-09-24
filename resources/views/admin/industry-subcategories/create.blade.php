@extends('layouts.admin')

@section('title', 'Add Sub-Category — AccumenAI')

@section('content')
<nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none">Dashboard</a></li>
        <li class="breadcrumb-item"><a href="{{ route('admin.industry-subcategories.index') }}" class="text-decoration-none">Sub-Categories</a></li>
        <li class="breadcrumb-item active">Add</li>
    </ol>
</nav>

<div class="page-header">
    <div class="page-header-text">
        <h4 class="page-header-title">Add Sub-Category</h4>
        <p class="page-header-desc">Creates a Layer 3 tenant-assignment option. Module mappings are added on the show page scope via seeders.</p>
    </div>
    <div class="page-header-actions">
        <a class="btn btn-outline-secondary btn-sm" href="{{ route('admin.industry-subcategories.index') }}">
            <i class="bi bi-arrow-left"></i> Back
        </a>
    </div>
</div>

<form method="POST" action="{{ route('admin.industry-subcategories.store') }}">
    @include('admin.industry-subcategories._form', ['submitLabel' => 'Create Sub-Category'])
</form>
@endsection

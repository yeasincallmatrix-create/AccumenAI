@extends('layouts.admin')

@section('title', 'Add Industry — AccumenAI')

@section('content')
<nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none">Dashboard</a></li>
        <li class="breadcrumb-item"><a href="{{ route('admin.industries.index') }}" class="text-decoration-none">Industries</a></li>
        <li class="breadcrumb-item active">Add Industry</li>
    </ol>
</nav>

<div class="page-header">
    <div class="page-header-text">
        <h4 class="page-header-title">Add Industry</h4>
    </div>
</div>

@if ($errors->any())
    <div class="alert alert-danger py-2">
        @foreach ($errors->all() as $error)
            <div class="small">{{ $error }}</div>
        @endforeach
    </div>
@endif

<div class="admin-card" style="max-width:600px">
    <form method="POST" action="{{ route('admin.industries.store') }}">
        @csrf
        <div class="mb-3">
            <label class="form-label" for="name">Name <span class="text-danger">*</span></label>
            <input id="name" type="text" class="form-control" name="name" value="{{ old('name') }}" required autofocus>
        </div>
        <div class="mb-3">
            <label class="form-label" for="slug">Slug <span class="text-danger">*</span></label>
            <input id="slug" type="text" class="form-control" name="slug" value="{{ old('slug') }}" required>
            <div class="form-text">Unique machine-readable identifier. Use lowercase with underscores.</div>
        </div>
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label" for="code">Code</label>
                <input id="code" type="text" class="form-control" name="code" value="{{ old('code') }}">
            </div>
            <div class="col-md-6">
                <label class="form-label" for="sort_order">Sort Order</label>
                <input id="sort_order" type="number" class="form-control" name="sort_order" value="{{ old('sort_order', 0) }}" min="0">
            </div>
        </div>
        <div class="mb-3 mt-3">
            <label class="form-label" for="description">Description</label>
            <textarea id="description" class="form-control" name="description" rows="2">{{ old('description') }}</textarea>
        </div>
        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Create Industry</button>
            <a href="{{ route('admin.industries.index') }}" class="btn btn-outline-secondary">Cancel</a>
        </div>
    </form>
</div>

<script>
document.getElementById('name').addEventListener('input', function() {
    var slug = this.value.toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_|_$/g, '');
    document.getElementById('slug').value = slug;
});
</script>
@endsection

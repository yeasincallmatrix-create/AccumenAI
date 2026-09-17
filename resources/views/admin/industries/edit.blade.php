@extends('layouts.admin')

@section('title', 'Edit Industry — AccumenAI')

@section('content')
<nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none">Dashboard</a></li>
        <li class="breadcrumb-item"><a href="{{ route('admin.industries.index') }}" class="text-decoration-none">Industries</a></li>
        <li class="breadcrumb-item active">{{ $industry->name }}</li>
    </ol>
</nav>

<div class="page-header">
    <div class="page-header-text">
        <h4 class="page-header-title">Edit: {{ $industry->name }}</h4>
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
    <form method="POST" action="{{ route('admin.industries.update', $industry) }}">
        @csrf
        @method('PUT')
        <div class="mb-3">
            <label class="form-label" for="name">Name <span class="text-danger">*</span></label>
            <input id="name" type="text" class="form-control" name="name" value="{{ old('name', $industry->name) }}" required>
        </div>
        <div class="mb-3">
            <label class="form-label" for="slug">Slug <span class="text-danger">*</span></label>
            <input id="slug" type="text" class="form-control" name="slug" value="{{ old('slug', $industry->slug) }}" required>
            <div class="form-text">Changing the slug may affect existing references.</div>
        </div>
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label" for="code">Code</label>
                <input id="code" type="text" class="form-control" name="code" value="{{ old('code', $industry->code) }}">
            </div>
            <div class="col-md-6">
                <label class="form-label" for="sort_order">Sort Order</label>
                <input id="sort_order" type="number" class="form-control" name="sort_order" value="{{ old('sort_order', $industry->sort_order) }}" min="0">
            </div>
        </div>
        <div class="mb-3 mt-3">
            <label class="form-label" for="description">Description</label>
            <textarea id="description" class="form-control" name="description" rows="2">{{ old('description', $industry->description) }}</textarea>
        </div>
        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Save Changes</button>
            <a href="{{ route('admin.industries.index') }}" class="btn btn-outline-secondary">Cancel</a>
        </div>
    </form>
</div>
@endsection

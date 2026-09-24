@extends('layouts.institute')

@section('title', 'Edit Batch — Training — AccumenAI')

@section('content')
<nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb small mb-0">
        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none">Dashboard</a></li>
        <li class="breadcrumb-item"><a href="{{ route('training.batches.index') }}" class="text-decoration-none">Batches</a></li>
        <li class="breadcrumb-item active">Edit</li>
    </ol>
</nav>

<div class="page-header">
    <h4 class="page-header-title">Edit Batch — {{ $batch->name }}</h4>
</div>

<div class="admin-card">
    <form method="POST" action="{{ route('training.batches.update', $batch->id) }}">
        @csrf
        @method('PUT')
        <div class="row g-3">
            <div class="col-md-8">
                <label class="form-label" for="name">Batch Name *</label>
                <input type="text" id="name" name="name" class="form-control" maxlength="255" value="{{ old('name', $batch->name) }}" required>
                @error('name') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
            </div>
        </div>
        <div class="mt-4 d-flex gap-2">
            <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Update</button>
            <a href="{{ route('training.batches.index') }}" class="btn btn-outline-secondary">Cancel</a>
        </div>
    </form>
</div>
@endsection

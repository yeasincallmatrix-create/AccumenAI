@extends('layouts.institute')

@section('title', 'Edit Role — AccumenAI')

@section('content')
<nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="{{ route('staff.roles.index') }}" class="text-decoration-none">Roles</a></li>
        <li class="breadcrumb-item active" aria-current="page">{{ mawa_role_label($role) }}</li>
    </ol>
</nav>

<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div class="page-header-text">
        <h4 class="page-header-title">Edit Role — {{ mawa_role_label($role) }}</h4>
        <p class="page-header-desc"><code>{{ $role->slug }}</code></p>
    </div>
    <div class="page-header-actions">
        <a class="btn btn-secondary" href="{{ route('staff.roles.index') }}">
            <i class="bi bi-arrow-left me-1"></i>Back
        </a>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <form action="{{ route('staff.roles.update', $role) }}" method="POST">
            @csrf @method('PUT')
            @include('staff.roles._form')
            <div class="mt-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Update Role</button>
                <a href="{{ route('staff.roles.index') }}" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection

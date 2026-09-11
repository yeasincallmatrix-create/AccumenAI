@extends('layouts.institute')

@section('title', 'New Branch — AccumenAI')

@section('content')
<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div class="page-header-text">
        <h4 class="page-header-title">New Branch</h4>
    </div>
    <div class="page-header-actions">
        <a class="btn btn-secondary" href="{{ route('medical.branches.index') }}">
            <i class="bi bi-arrow-left me-1"></i>Back
        </a>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <form action="{{ route('medical.branches.store') }}" method="POST" class="row g-3">
            @csrf
            <div class="col-md-6">
                <label class="form-label" for="name">Name <span class="text-danger">*</span></label>
                <input type="text" id="name" name="name" maxlength="120" required
                       class="form-control @error('name') is-invalid @enderror" value="{{ old('name') }}">
                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6">
                <label class="form-label" for="manager_user_id">Manager (optional)</label>
                <select id="manager_user_id" name="manager_user_id" class="form-select">
                    <option value="">— None —</option>
                    @foreach($managers as $manager)
                        <option value="{{ $manager->id }}" {{ (string) old('manager_user_id') === (string) $manager->id ? 'selected' : '' }}>
                            {{ $manager->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="phone">Phone</label>
                <input type="text" id="phone" name="phone" maxlength="30" class="form-control" value="{{ old('phone') }}">
            </div>
            <div class="col-md-4">
                <label class="form-label" for="email">Email</label>
                <input type="email" id="email" name="email" maxlength="120" class="form-control" value="{{ old('email') }}">
            </div>
            <div class="col-md-4">
                <label class="form-label" for="address">Address</label>
                <input type="text" id="address" name="address" maxlength="500" class="form-control" value="{{ old('address') }}">
            </div>
            <div class="col-12">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save me-1"></i>Create Branch
                </button>
            </div>
        </form>
    </div>
</div>
@endsection

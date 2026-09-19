@extends('layouts.admin')

@section('title', 'Edit Scope — AccumenAI')

@section('content')
<nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none">Dashboard</a></li>
        <li class="breadcrumb-item"><a href="{{ route('admin.packages.scopes.index', $scope->package) }}" class="text-decoration-none">Scoped Packages</a></li>
        <li class="breadcrumb-item"><a href="{{ route('admin.scopes.show', $scope) }}" class="text-decoration-none">Scope #{{ $scope->id }}</a></li>
        <li class="breadcrumb-item active" aria-current="page">Edit</li>
    </ol>
</nav>

<div class="page-header">
    <div class="page-header-text">
        <h4 class="page-header-title">Edit Scope #{{ $scope->id }}</h4>
        <p class="page-header-desc">Package is read-only and cannot be changed after creation.</p>
    </div>
</div>

@if ($errors->any())
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <ul class="mb-0">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

@if (session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

<div class="admin-card mb-4">
    <form method="POST" action="{{ route('admin.scopes.update', $scope) }}">
        @csrf
        @method('PUT')
        <div class="p-3">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Package (read-only)</label>
                    <input type="text" class="form-control" value="{{ $scope->package->name ?? '—' }} ({{ $scope->package->slug ?? '' }})" readonly disabled>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Country</label>
                    <select class="form-select" name="country_id">
                        <option value="">Global (no country)</option>
                        @foreach ($countries as $country)
                            <option value="{{ $country->id }}" @selected((int) old('country_id', $scope->country_id ?? '') === (int) $country->id)>{{ $country->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Industry</label>
                    <select class="form-select" name="industry_id" id="industry-select">
                        <option value="">Any industry</option>
                        @foreach ($industries as $industry)
                            <option value="{{ $industry->id }}" @selected((int) old('industry_id', $scope->industry_id ?? '') === (int) $industry->id)>{{ $industry->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Sub-industry</label>
                    <select class="form-select" name="sub_industry_id" id="sub-industry-select">
                        <option value="">Any sub-industry</option>
                        @foreach ($subIndustries as $sub)
                            <option value="{{ $sub->id }}" data-industry="{{ $sub->industry_id }}" @selected((int) old('sub_industry_id', $scope->sub_industry_id ?? '') === (int) $sub->id)>{{ $sub->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Inherit from parent</label>
                    <select class="form-select" name="inherit_from_parent" required>
                        <option value="1" @selected((bool) old('inherit_from_parent', $scope->inherit_from_parent))>Yes</option>
                        <option value="0" @selected(! (bool) old('inherit_from_parent', $scope->inherit_from_parent))>No</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Status</label>
                    <select class="form-select" name="status">
                        <option value="active" @selected(old('status', $scope->status) === 'active')>Active</option>
                        <option value="inactive" @selected(old('status', $scope->status) === 'inactive')>Inactive</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Price monthly</label>
                    <input type="number" step="0.01" min="0" class="form-control" name="price_monthly" value="{{ old('price_monthly', $scope->price_monthly) }}" placeholder="Leave empty to inherit">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Price yearly</label>
                    <input type="number" step="0.01" min="0" class="form-control" name="price_yearly" value="{{ old('price_yearly', $scope->price_yearly) }}" placeholder="Leave empty to inherit">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Currency (3-char)</label>
                    <input type="text" maxlength="3" class="form-control" name="currency" value="{{ old('currency', $scope->currency) }}" placeholder="BDT">
                </div>
            </div>
        </div>
        <div class="p-3 border-top d-flex justify-content-end gap-2">
            <a href="{{ route('admin.scopes.show', $scope) }}" class="btn btn-outline-secondary btn-sm">Cancel</a>
            <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-save"></i> Save Changes</button>
        </div>
    </form>
</div>

<script>
(function () {
    var industry = document.getElementById('industry-select');
    var sub = document.getElementById('sub-industry-select');
    if (!industry || !sub) return;
    function filterSubs() {
        var selected = industry.value;
        Array.prototype.forEach.call(sub.options, function (opt) {
            if (!opt.value) return;
            opt.hidden = selected !== '' && opt.getAttribute('data-industry') !== selected;
        });
    }
    industry.addEventListener('change', filterSubs);
    filterSubs();
})();
</script>
@endsection

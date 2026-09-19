@extends('layouts.admin')

@section('title', 'Create Scope — AccumenAI')

@section('content')
<nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none">Dashboard</a></li>
        <li class="breadcrumb-item"><a href="{{ route('admin.packages.scopes.index', $package) }}" class="text-decoration-none">Scoped Packages</a></li>
        <li class="breadcrumb-item active" aria-current="page">Create Scope</li>
    </ol>
</nav>

<div class="page-header">
    <div class="page-header-text">
        <h4 class="page-header-title">Create Scope</h4>
        <p class="page-header-desc">Add a new country / industry scope for package <strong>{{ $package->name }}</strong>.</p>
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

<div class="admin-card mb-4">
    <form method="POST" action="{{ route('admin.packages.scopes.store', $package) }}">
        @csrf
        <div class="p-3">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Package <span class="text-danger">*</span></label>
                    <select class="form-select" name="package_id" required>
                        @foreach ($packages as $pkg)
                            <option value="{{ $pkg->id }}" @selected((int) old('package_id', $package->id) === (int) $pkg->id)>{{ $pkg->name }} ({{ $pkg->slug }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Country</label>
                    <select class="form-select" name="country_id">
                        <option value="">Global (no country)</option>
                        @foreach ($countries as $country)
                            <option value="{{ $country->id }}" @selected(old('country_id') == $country->id)>{{ $country->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Industry</label>
                    <select class="form-select" name="industry_id" id="industry-select">
                        <option value="">Any industry</option>
                        @foreach ($industries as $industry)
                            <option value="{{ $industry->id }}" @selected(old('industry_id') == $industry->id)>{{ $industry->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Sub-industry</label>
                    <select class="form-select" name="sub_industry_id" id="sub-industry-select">
                        <option value="">Any sub-industry</option>
                        @foreach ($subIndustries as $sub)
                            <option value="{{ $sub->id }}" data-industry="{{ $sub->industry_id }}" @selected(old('sub_industry_id') == $sub->id)>{{ $sub->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Inherit from parent</label>
                    <select class="form-select" name="inherit_from_parent" required>
                        <option value="1" @selected(old('inherit_from_parent', '1') === '1')>Yes — copy parent features</option>
                        <option value="0" @selected(old('inherit_from_parent') === '0')>No — start empty</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Status</label>
                    <select class="form-select" name="status">
                        <option value="active" @selected(old('status', 'active') === 'active')>Active</option>
                        <option value="inactive" @selected(old('status') === 'inactive')>Inactive</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Price monthly</label>
                    <input type="number" step="0.01" min="0" class="form-control" name="price_monthly" value="{{ old('price_monthly') }}" placeholder="Leave empty to inherit">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Price yearly</label>
                    <input type="number" step="0.01" min="0" class="form-control" name="price_yearly" value="{{ old('price_yearly') }}" placeholder="Leave empty to inherit">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Currency (3-char)</label>
                    <input type="text" maxlength="3" class="form-control" name="currency" value="{{ old('currency') }}" placeholder="BDT">
                </div>
            </div>
        </div>
        <div class="p-3 border-top d-flex justify-content-end gap-2">
            <a href="{{ route('admin.packages.scopes.index', $package) }}" class="btn btn-outline-secondary btn-sm">Cancel</a>
            <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-save"></i> Save Scope</button>
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

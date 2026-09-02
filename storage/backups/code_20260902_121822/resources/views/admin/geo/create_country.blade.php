@extends('layouts.admin')

@section('title', 'Add Country — AccumenAI')

@section('content')
<div class="page-header">
    <div class="page-header-text">
        <h4 class="page-header-title">{{ mawa_e('admin_geo.add_country') }}</h4>
        <p class="page-header-desc">{{ mawa_e('admin_geo.subtitle') }}</p>
    </div>
    <div class="page-header-actions">
        <a class="btn btn-outline-secondary" href="{{ route('admin.geo.index') }}">
            <i class="bi bi-arrow-left"></i> {{ mawa_e('actions.back') }}
        </a>
    </div>
</div>

<div class="admin-card" style="max-width:640px;">
    <form method="POST" action="{{ route('admin.geo.countries.store') }}">
        @csrf
        <div class="row g-3">
            <div class="col-12">
                <label class="form-label" for="name">{{ mawa_e('admin_geo.country_name') }} <span class="text-danger">*</span></label>
                <input type="text" id="name" name="name" class="form-control"
                       value="{{ old('name') }}" maxlength="120" required>
                @error('name')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6">
                <label class="form-label" for="iso2">{{ mawa_e('admin_geo.iso2') }} <span class="text-danger">*</span></label>
                <input type="text" id="iso2" name="iso2" class="form-control text-uppercase"
                       value="{{ old('iso2') }}" maxlength="2" required>
                <div class="form-text">e.g. BD, US, IN</div>
                @error('iso2')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6">
                <label class="form-label" for="iso3">ISO 3</label>
                <input type="text" id="iso3" name="iso3" class="form-control text-uppercase"
                       value="{{ old('iso3') }}" maxlength="3">
                <div class="form-text">e.g. BGD, USA, IND</div>
                @error('iso3')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6">
                <label class="form-label" for="phone_code">Phone Code</label>
                <input type="text" id="phone_code" name="phone_code" class="form-control"
                       value="{{ old('phone_code') }}" maxlength="10">
                <div class="form-text">e.g. 880</div>
            </div>
        </div>
        <div class="d-flex justify-content-end gap-2 mt-4">
            <a class="btn btn-outline-secondary" href="{{ route('admin.geo.index') }}">{{ mawa_e('actions.cancel') }}</a>
            <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Save Country</button>
        </div>
    </form>
</div>
@endsection

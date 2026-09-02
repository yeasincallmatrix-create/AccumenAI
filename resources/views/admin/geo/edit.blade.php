@extends('layouts.admin')

@section('title', 'Administrative Levels — ' . $country->name . ' — AccumenAI')

@section('content')
@php
    $levelsByNumber = $levels->keyBy('level_number');
@endphp
<div class="page-header">
    <div class="page-header-text">
        <h4 class="page-header-title">
            {{ $country->name }}
            @if ($country->status)
                <span class="badge text-bg-success ms-1">{{ mawa_e('admin_geo.enabled') }}</span>
            @else
                <span class="badge text-bg-secondary ms-1">{{ mawa_e('admin_geo.disabled') }}</span>
            @endif
        </h4>
        <p class="page-header-desc">
            {{ $country->iso2 }} &middot; {{ number_format($unitCount) }} {{ mawa_e('admin_geo.unit_count') }}
        </p>
    </div>
    <div class="page-header-actions d-flex gap-2">
        <form method="POST" action="{{ route('admin.geo.toggle', $country) }}" data-ajax-action="1"
              data-confirm="{{ $country->status ? 'Disable this country\'s configuration?' : 'Enable this country\'s configuration?' }}">
            @csrf
            <button type="submit" class="btn {{ $country->status ? 'btn-outline-danger' : 'btn-outline-success' }}">
                <i class="bi bi-power"></i>
                {{ $country->status ? mawa_e('admin_geo.disabled') : mawa_e('admin_geo.enabled') }}
            </button>
        </form>
        <a class="btn btn-outline-secondary" href="{{ route('admin.geo.index') }}">
            <i class="bi bi-arrow-left"></i> {{ mawa_e('actions.back') }}
        </a>
    </div>
</div>

<div class="admin-card" style="max-width:720px;">
    <form method="POST" action="{{ route('admin.geo.update', $country) }}">
        @csrf
        @method('PUT')

        <div class="table-toolbar">
            <div class="toolbar-info"><i class="bi bi-info-circle-fill"></i> {{ mawa_e('admin_geo.subtitle') }}</div>
        </div>

        <div class="row g-3">
            @foreach ([1, 2, 3] as $levelNumber)
                @php
                    $label = $levelsByNumber->get($levelNumber);
                @endphp
                <div class="col-md-4">
                    <label class="form-label" for="level_{{ $levelNumber }}">
                        {{ mawa_lang('admin_geo.level_'.$levelNumber) }}
                        <span class="text-muted fw-normal small">({{ $label?->status ? $label->name : '—' }})</span>
                    </label>
                    <input type="text" id="level_{{ $levelNumber }}" name="level_{{ $levelNumber }}"
                           class="form-control" value="{{ old('level_'.$levelNumber, $label?->name) }}"
                           maxlength="80" placeholder="{{ mawa_lang('admin_geo.level_'.$levelNumber.'_hint') }}">
                    <div class="form-text">{{ mawa_e('admin_geo.level_'.$levelNumber.'_hint') }}</div>
                    @error('level_'.$levelNumber)<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                </div>
            @endforeach
        </div>

        <div class="alert alert-light border mt-4 mb-0 small text-muted">
            <i class="bi bi-info-circle"></i>
            Leave a level blank to disable it for this country. A blank level will not appear in the address form.
        </div>

        <div class="d-flex justify-content-end gap-2 mt-4">
            <a class="btn btn-outline-secondary" href="{{ route('admin.geo.index') }}">{{ mawa_e('actions.cancel') }}</a>
            <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> {{ mawa_e('admin_geo.save') }}</button>
        </div>
    </form>
</div>
@endsection

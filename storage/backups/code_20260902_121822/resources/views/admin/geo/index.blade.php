@extends('layouts.admin')

@section('title', 'Administrative Levels — AccumenAI')

@section('content')
<div class="page-header">
    <div class="page-header-text">
        <h4 class="page-header-title">{{ mawa_e('admin_geo.title') }}</h4>
        <p class="page-header-desc">{{ mawa_e('admin_geo.subtitle') }}</p>
    </div>
    <div class="page-header-actions">
        <a class="btn btn-primary" href="{{ route('admin.geo.countries.create') }}">
            <i class="bi bi-plus-lg"></i> {{ mawa_e('admin_geo.add_country') }}
        </a>
    </div>
</div>

<div class="filter-card">
    <form class="filter-layout" method="GET" action="{{ route('admin.geo.index') }}">
        <div class="filter-search-row align-items-end">
            <div class="filter-search" style="flex:1 1 0; min-width:220px;">
                <i class="bi bi-search"></i>
                <input type="text" class="form-control form-control-sm" name="q"
                       placeholder="Search by country name..." value="{{ $q ?? '' }}">
            </div>
            <div class="filter-span flex-shrink-0">
                <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-search"></i> Search</button>
            </div>
        </div>
    </form>
</div>

<div class="admin-card mt-3">
    <div class="table-responsive">
        <table class="table align-middle">
            <thead>
                <tr>
                    <th>{{ mawa_e('admin_geo.country') }}</th>
                    <th>{{ mawa_e('admin_geo.level_1') }}</th>
                    <th>{{ mawa_e('admin_geo.level_2') }}</th>
                    <th>{{ mawa_e('admin_geo.level_3') }}</th>
                    <th>{{ mawa_e('admin_geo.status') }}</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($countries as $country)
                    @php
                        $levels = $country->levels->keyBy('level_number');
                    @endphp
                    <tr>
                        <td>
                            <span class="fw-semibold">{{ $country->name }}</span>
                            <div class="text-muted small">{{ $country->iso2 }}</div>
                        </td>
                        <td>
                            @if ($levels->get(1)?->status)
                                {{ $levels->get(1)->name }}
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td>
                            @if ($levels->get(2)?->status)
                                {{ $levels->get(2)->name }}
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td>
                            @if ($levels->get(3)?->status)
                                {{ $levels->get(3)->name }}
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td>
                            @if ($country->status)
                                <span class="badge text-bg-success">{{ mawa_e('admin_geo.active') }}</span>
                            @else
                                <span class="badge text-bg-secondary">{{ mawa_e('admin_geo.inactive') }}</span>
                            @endif
                        </td>
                        <td class="text-end">
                            <a class="btn btn-sm btn-outline-primary" href="{{ route('admin.geo.edit', $country) }}">
                                <i class="bi bi-pencil"></i> Configure
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">
                            {{ mawa_e('admin_geo.no_countries') }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection

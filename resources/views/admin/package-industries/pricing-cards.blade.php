@extends('layouts.admin')

@section('title', 'Package pricing card - AccumenAI')

@section('content')
<nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none">Dashboard</a></li>
        <li class="breadcrumb-item active" aria-current="page">Package pricing card</li>
    </ol>
</nav>

<div class="page-header">
    <div class="page-header-text">
        <h4 class="page-header-title">Package pricing card</h4>
        <p class="page-header-desc">Industry: <strong>{{ $industryName }}</strong>@if ($country !== '') | Country: <strong>{{ $country }} ({{ $currency }})</strong>@endif</p>
    </div>
    <div class="page-header-actions">
        @if (($view ?? 'admin') === 'customer')
            <a class="btn btn-outline-secondary btn-sm" href="{{ route('admin.package-industries.pricing-cards', array_filter(['industry' => $industry, 'country' => $country])) }}">
                <i class="bi bi-gear"></i> Admin view
            </a>
        @else
            <a class="btn btn-outline-primary btn-sm" href="{{ route('admin.package-industries.pricing-cards', array_filter(['industry' => $industry, 'country' => $country, 'view' => 'customer'])) }}">
                <i class="bi bi-eye"></i> Customer view
            </a>
        @endif
    </div>
</div>

<form method="GET" action="{{ route('admin.package-industries.pricing-cards') }}">
    @if (($view ?? 'admin') === 'customer')
        <input type="hidden" name="view" value="customer">
    @endif
    <div class="admin-card mb-4 p-3 d-flex gap-3 align-items-end flex-wrap">
        <div>
            <label class="form-label small text-muted mb-1" for="pcIndustry"><i class="bi bi-diagram-3-fill"></i> Industry</label>
            <select class="form-select form-select-sm w-auto" id="pcIndustry" name="industry" onchange="this.form.submit()">
                @foreach ($industries as $ind)
                    <option value="{{ $ind->slug }}" {{ $ind->slug === $industry ? 'selected' : '' }}>{{ $ind->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="form-label small text-muted mb-1" for="pcCountry"><i class="bi bi-globe2"></i> Country</label>
            <select class="form-select form-select-sm w-auto" id="pcCountry" name="country" onchange="this.form.submit()">
                <option value="" {{ $country === '' ? 'selected' : '' }}>Industry default (BDT)</option>
                @foreach ($countries as $region => $group)
                    <optgroup label="{{ $region }}">
                        @foreach ($group as $code => $meta)
                            <option value="{{ $code }}" {{ $code === $country ? 'selected' : '' }}>{{ $meta['name'] }} ({{ $meta['currency'] }})</option>
                        @endforeach
                    </optgroup>
                @endforeach
            </select>
        </div>
        @if ($country !== '' || $industry !== ($industries->first()->slug ?? ''))
            <a class="btn btn-outline-secondary btn-sm" href="{{ route('admin.package-industries.pricing-cards') }}">Reset</a>
        @endif
    </div>
</form>

@if (empty($cards))
    <div class="alert alert-info">No packages offered for <strong>{{ $industryName }}</strong> yet. Configure them under Package by Industry.</div>
@else
    <div class="row g-3">
        @foreach ($cards as $card)
            @php $pkg = $card['package']; @endphp
            <div class="col-md-6 col-xl-3">
                <div class="admin-card p-3 h-100 d-flex flex-column {{ $card['tier'] === 3 ? 'border-primary' : '' }}">
                    @if ($card['tier'] === 3)
                        <span class="badge bg-primary align-self-start mb-2">Most popular</span>
                    @endif
                    <h5 class="mb-0">{{ $pkg->name }}</h5>
                    <div class="text-muted small mb-2"><code>{{ $pkg->slug }}</code></div>

                    @if ($card['discount_active'] && $card['discount_percent'] > 0)
                        <div class="mb-1">
                            <span class="text-muted text-decoration-line-through">{{ number_format($card['base_monthly'], 2) }} {{ $currency }}</span>
                            <span class="badge text-bg-success badge-soft ms-1">-{{ rtrim(rtrim((string) $card['discount_percent'], '0'), '.') }}%</span>
                        </div>
                    @endif
                    <div class="display-6 fw-bold">{{ number_format($card['monthly'], 2) }} <small class="fs-6 fw-normal text-muted">{{ $currency }}/mo</small></div>
                    <div class="text-muted small mb-2">{{ number_format($card['yearly'], 2) }} {{ $currency }}/yr</div>

                    <div class="mb-2">
                        @if (empty($card['discount_percent']) || (float) $card['discount_percent'] <= 0)
                            <span class="badge text-bg-light text-secondary border">No discount</span>
                        @elseif (! $card['discount_active'])
                            <span class="badge text-bg-danger badge-soft"><i class="bi bi-clock"></i> Discount expired</span>
                        @elseif ($card['discount_days_left'] === null)
                            <span class="badge text-bg-info badge-soft"><i class="bi bi-clock"></i> Discount ongoing</span>
                        @elseif ($card['discount_days_left'] === 0)
                            <span class="badge text-bg-warning badge-soft"><i class="bi bi-clock"></i> Ends today</span>
                        @else
                            <span class="badge text-bg-success badge-soft"><i class="bi bi-clock"></i> {{ $card['discount_days_left'] }} {{ $card['discount_days_left'] === 1 ? 'day' : 'days' }} left</span>
                        @endif
                        @if (! empty($card['discount_ends_at']))
                            <div class="text-muted small mt-1">Offer ends {{ $card['discount_ends_at'] }}</div>
                        @endif
                    </div>

                    <ul class="list-unstyled small text-muted mb-3">
                        <li><i class="bi bi-puzzle me-1"></i>{{ $card['module_count'] }} modules</li>
                        <li><i class="bi bi-stars me-1"></i>{{ $card['feature_count'] }} features</li>
                    </ul>

                    @if (! empty($card['trial_days']) && (int) $card['trial_days'] > 0 && $card['tier'] !== 0)
                        <div class="mb-2"><span class="badge text-bg-primary badge-soft"><i class="bi bi-gift"></i> {{ (int) $card['trial_days'] }}-day free trial</span></div>
                    @endif

                    @if (($view ?? 'admin') === 'customer')
                        @if ($card['tier'] === 0)
                            <span class="btn btn-secondary btn-sm mt-auto disabled">Current plan</span>
                        @elseif (! empty($card['trial_days']) && (int) $card['trial_days'] > 0)
                            <span class="btn btn-success btn-sm mt-auto disabled">Start {{ (int) $card['trial_days'] }}-day free trial</span>
                        @else
                            <span class="btn btn-primary btn-sm mt-auto disabled">Choose {{ $pkg->name }}</span>
                        @endif
                    @else
                        <a class="btn btn-outline-primary btn-sm mt-auto"
                           href="{{ route('admin.package-industries.show-modules', ['package' => $pkg->id, 'industry' => $industry]) }}">
                            <i class="bi bi-puzzle"></i> Configure Modules
                        </a>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
@endif
@endsection

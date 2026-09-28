@extends('layouts.admin')

@section('title', 'Packages by Industry - AccumenAI')

@section('content')
<nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none">Dashboard</a></li>
        <li class="breadcrumb-item active" aria-current="page">Packages by Industry</li>
    </ol>
</nav>

<div class="page-header">
    <div class="page-header-text">
        <h4 class="page-header-title">Package Configuration by Industry</h4>
        <p class="page-header-desc">Choose which subscription packages are offered in each industry, their price and their module set.</p>
    </div>
    <div class="page-header-actions">
        <a class="btn btn-outline-secondary btn-sm" href="{{ route('admin.modules.index') }}">
            <i class="bi bi-puzzle-fill"></i> Modules &amp; Packages
        </a>
    </div>
</div>

@if (session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif
@if ($errors->any())
    <div class="alert alert-danger" role="alert">
        <ul class="mb-0 ps-3">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="admin-card mb-4">
    <ul class="nav nav-tabs" role="tablist">
        @foreach ($industries as $ind)
            <li class="nav-item" role="presentation">
                <a class="nav-link {{ $ind->slug === $industry ? 'active' : '' }}"
                   href="{{ route('admin.package-industries.index', array_filter(['industry' => $ind->slug, 'country' => $country])) }}">
                    {{ $ind->name }}
                </a>
            </li>
        @endforeach
    </ul>
</div>

<form method="POST" action="{{ route('admin.package-industries.update') }}">
    @csrf
    @method('PUT')
    <input type="hidden" name="industry" value="{{ $industry }}">

@if (! $totals['configured'])
    <div class="alert alert-info" role="alert">
        <i class="bi bi-info-circle me-1"></i>
        <strong>{{ $industryName }}</strong> has no package configuration yet: every active package stays available.
        Tick the packages you want to offer, then save.
    </div>
@endif

<div class="admin-card mb-4">

        <div class="table-toolbar">
            <div class="toolbar-info">
                <i class="bi bi-box-seam"></i>
                INDUSTRY: <strong>{{ $industryName }}</strong>
                <span class="badge text-bg-primary badge-soft ms-2">{{ $totals['enabled'] }} / {{ $totals['packages'] }} packages enabled</span>
            </div>
            <div class="d-flex gap-2 align-items-center flex-wrap">
                <label class="text-muted small mb-0" for="priceCountry">
                    <i class="bi bi-globe2"></i> Country
                </label>
                <select class="form-select form-select-sm w-auto" id="priceCountry" aria-label="Pricing country">
                    <option value="" {{ $country === '' ? 'selected' : '' }}>Industry default (BDT)</option>
                    @foreach ($countries as $region => $group)
                        <optgroup label="{{ $region }}">
                            @foreach ($group as $code => $meta)
                                <option value="{{ $code }}" {{ $code === $country ? 'selected' : '' }}>
                                    {{ $meta['name'] }} ({{ $meta['currency'] }})
                                </option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>
                @if ($country !== '')
                    <span class="badge text-bg-secondary badge-soft">{{ $countryCurrency }}</span>
                    <button type="button" class="btn btn-primary btn-sm" id="saveCountryPrices">
                        <i class="bi bi-currency-exchange"></i> Save {{ $countryCurrency }} prices
                    </button>
                @endif
                <button type="submit" class="btn btn-outline-primary btn-sm">
                    <i class="bi bi-check-lg"></i> Save Configuration
                </button>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th style="width:70px">Offer</th>
                        <th>Package</th>
                        <th style="width:140px">Modules</th>
                        <th style="width:170px">Price / month <span class="text-muted small">({{ $country !== '' ? $countryCurrency : 'BDT' }})</span></th>
                        <th style="width:170px">Price / year <span class="text-muted small">({{ $country !== '' ? $countryCurrency : 'BDT' }})</span></th>
                        <th style="width:200px">Discount</th>
                        <th style="width:100px">Trial days</th>
                        <th style="width:110px">Sort</th>
                        <th style="width:190px"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        @php $pkg = $row['package']; @endphp
                        <tr class="{{ $row['enabled'] ? '' : 'table-secondary' }}">
                            <td>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox"
                                           name="packages[]"
                                           value="{{ $pkg->id }}"
                                           id="pkg_{{ $pkg->id }}"
                                           {{ $row['enabled'] ? 'checked' : '' }}>
                                </div>
                            </td>
                            <td>
                                <label for="pkg_{{ $pkg->id }}" class="form-check-label fw-semibold">
                                    {{ $pkg->name }}
                                    @if ($pkg->is_default)
                                        <span class="badge text-bg-info badge-soft">default</span>
                                    @endif
                                </label>
                                <div class="text-muted small"><code>{{ $pkg->slug }}</code></div>
                            </td>
                            <td>
                                <span class="badge {{ $row['has_industry_modules'] ? 'text-bg-success' : 'text-bg-light text-secondary border' }}">
                                    {{ $row['module_count'] }} modules
                                </span>
                                <div class="text-muted small">
                                    {{ $row['has_industry_modules'] ? 'industry set' : 'package default' }}
                                </div>
                            </td>
                            <td>
                                @if ($country !== '')
                                    <div class="input-group input-group-sm mb-1">
                                        <span class="input-group-text">{{ $countryCurrency }}</span>
                                        <input type="number" step="0.01" min="0" class="form-control js-country-price"
                                               data-package="{{ $pkg->id }}"
                                               data-field="monthly"
                                               value="{{ $countryPrices->get($pkg->id)?->price_monthly ?? '' }}"
                                               aria-label="{{ $pkg->name }} {{ $country }} price per month">
                                    </div>
                                    <div class="text-muted small js-price-status" data-for="{{ $pkg->id }}">country price</div>
                                @endif
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text">BDT</span>
                                    <input type="number" step="0.01" min="0" class="form-control"
                                           name="price_monthly[{{ $pkg->id }}]"
                                           value="{{ $row['price_monthly'] }}"
                                           placeholder="{{ $pkg->price_monthly }}">
                                </div>
                                @if ($row['has_price_override'])
                                    <div class="text-muted small">override active</div>
                                @else
                                    <div class="text-muted small">package default</div>
                                @endif
                            </td>
                            <td>
                                @if ($country !== '')
                                    <div class="input-group input-group-sm mb-1">
                                        <span class="input-group-text">{{ $countryCurrency }}</span>
                                        <input type="number" step="0.01" min="0" class="form-control js-country-price"
                                               data-package="{{ $pkg->id }}"
                                               data-field="yearly"
                                               value="{{ $countryPrices->get($pkg->id)?->price_yearly ?? '' }}"
                                               aria-label="{{ $pkg->name }} {{ $country }} price per year">
                                    </div>
                                    <div class="text-muted small js-price-status" data-for="{{ $pkg->id }}">country price</div>
                                @endif
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text">BDT</span>
                                    <input type="number" step="0.01" min="0" class="form-control"
                                           name="price_yearly[{{ $pkg->id }}]"
                                           value="{{ $row['price_yearly'] }}"
                                           placeholder="{{ $pkg->price_yearly }}">
                                </div>
                            </td>
                            <td>
                                <div class="input-group input-group-sm mb-1">
                                    <input type="number" step="0.01" min="0" max="100" class="form-control"
                                           name="discount_percent[{{ $pkg->id }}]"
                                           value="{{ $row['discount_percent'] }}"
                                           placeholder="0"
                                           aria-label="{{ $pkg->name }} discount percent">
                                    <span class="input-group-text">%</span>
                                </div>
                                <input type="date" class="form-control form-control-sm mb-1"
                                       name="discount_ends_at[{{ $pkg->id }}]"
                                       value="{{ $row['discount_ends_at'] }}"
                                       aria-label="{{ $pkg->name }} discount end date">
                                @if ($country !== '')
                                    @php $cp = $countryPrices->get($pkg->id); @endphp
                                    <div class="input-group input-group-sm mb-1">
                                        <input type="number" step="0.01" min="0" max="100" class="form-control js-country-price"
                                               data-package="{{ $pkg->id }}"
                                               data-field="discount_percent"
                                               value="{{ $cp?->discount_percent ?? '' }}"
                                               placeholder="0"
                                               aria-label="{{ $pkg->name }} {{ $country }} discount percent">
                                        <span class="input-group-text">% {{ $country }}</span>
                                    </div>
                                    <div class="d-flex gap-1 mb-1">
                                        <input type="date" class="form-control form-control-sm js-country-price"
                                               data-package="{{ $pkg->id }}"
                                               data-field="discount_ends_at"
                                               value="{{ $cp?->discount_ends_at ?? '' }}"
                                               aria-label="{{ $pkg->name }} {{ $country }} discount end date">
                                        <input type="number" min="0" max="365" class="form-control form-control-sm js-country-price"
                                               data-package="{{ $pkg->id }}"
                                               data-field="trial_days"
                                               value="{{ $cp?->trial_days ?? '' }}"
                                               placeholder="Trial"
                                               title="{{ $country }} trial days (empty = inherit industry, 0 = blocked)"
                                               aria-label="{{ $pkg->name }} {{ $country }} trial days">
                                    </div>
                                @endif
                                @if (empty($row['discount_percent']) || (float) $row['discount_percent'] <= 0)
                                    <div class="text-muted small">no discount</div>
                                @elseif (! $row['discount_active'])
                                    <div><span class="badge text-bg-danger badge-soft">Expired</span></div>
                                @elseif ($row['discount_days_left'] === null)
                                    <div><span class="badge text-bg-info badge-soft">{{ rtrim(rtrim((string) $row['discount_percent'], '0'), '.') }}% off · no end date</span></div>
                                    <div class="text-success small fw-semibold">{{ number_format($row['price_monthly_effective'], 2) }} / {{ number_format($row['price_yearly_effective'], 2) }}</div>
                                @elseif ($row['discount_days_left'] === 0)
                                    <div><span class="badge text-bg-warning badge-soft">Ends today</span></div>
                                    <div class="text-success small fw-semibold">{{ number_format($row['price_monthly_effective'], 2) }} / {{ number_format($row['price_yearly_effective'], 2) }}</div>
                                @else
                                    <div><span class="badge text-bg-success badge-soft">{{ $row['discount_days_left'] }} {{ $row['discount_days_left'] === 1 ? 'day' : 'days' }} left</span></div>
                                    <div class="text-success small fw-semibold">{{ number_format($row['price_monthly_effective'], 2) }} / {{ number_format($row['price_yearly_effective'], 2) }}</div>
                                @endif
                            </td>
                            <td>
                                <input type="number" min="0" max="365" class="form-control form-control-sm"
                                       name="trial_days[{{ $pkg->id }}]"
                                       value="{{ $row['trial_days'] }}"
                                       placeholder="0"
                                       title="Trial days for all countries (empty = none, 0 = blocked)"
                                       aria-label="{{ $pkg->name }} trial days">
                                @if ($row['trial_days'] === null)
                                    <div class="text-muted small">no trial</div>
                                @elseif ((int) $row['trial_days'] === 0)
                                    <div><span class="badge text-bg-danger badge-soft">Blocked{{ $country !== '' ? ' in '.$country : '' }}</span></div>
                                @else
                                    <div class="text-muted small">{{ $row['trial_days'] }}-day trial</div>
                                @endif
                            </td>
                            <td>
                                <input type="number" min="0" max="9999" class="form-control form-control-sm"
                                       name="sort_order[{{ $pkg->id }}]"
                                       value="{{ $row['sort_order'] }}">
                            </td>
                            <td class="text-end">
                                <a class="btn btn-outline-primary btn-sm"
                                   href="{{ route('admin.package-industries.show-modules', ['package' => $pkg->id, 'industry' => $industry]) }}">
                                    <i class="bi bi-puzzle"></i> Configure Modules
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="text-center text-muted py-4">No active packages found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="p-3 border-top d-flex justify-content-between align-items-center">
            <span class="text-muted small">
                Unchecked packages are not offered to institutes in <strong>{{ $industryName }}</strong>.
                Tenants holding a non-offered package fall back to <strong>FREE</strong>.
            </span>
            <button type="submit" class="btn btn-primary btn-sm">
                <i class="bi bi-check-lg"></i> Save Configuration
            </button>
        </div>
    </div>
</form>
@endsection

@push('scripts')
<script>
(function () {
    var country = @json($country);
    var endpoint = @json(route('admin.package-industries.country-prices.save'));
    var csrf = @json(csrf_token());
    var select = document.getElementById('priceCountry');

    if (select) {
        select.addEventListener('change', function () {
            var url = new URL(window.location.href);
            if (this.value) {
                url.searchParams.set('country', this.value);
            } else {
                url.searchParams.delete('country');
            }
            window.location.href = url.toString();
        });
    }

    var saveBtn = document.getElementById('saveCountryPrices');
    if (!saveBtn || !country) {
        return;
    }

    saveBtn.addEventListener('click', function () {
        var byPackage = {};

        document.querySelectorAll('.js-country-price').forEach(function (input) {
            var packageId = input.getAttribute('data-package');
            if (!byPackage[packageId]) {
                byPackage[packageId] = { package_id: parseInt(packageId, 10), monthly: null, yearly: null, discount_percent: null, discount_ends_at: null, trial_days: null };
            }
            var value = input.value === '' ? null : input.value;
            var field = input.getAttribute('data-field');
            if (field === 'monthly' || field === 'yearly' || field === 'discount_percent' || field === 'discount_ends_at' || field === 'trial_days') {
                byPackage[packageId][field] = value;
            }
        });

        var prices = Object.keys(byPackage).map(function (key) { return byPackage[key]; });
        if (!prices.length) {
            return;
        }

        saveBtn.disabled = true;

        fetch(endpoint, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrf
            },
            body: JSON.stringify({ country: country, prices: prices })
        })
            .then(function (res) {
                return res.json().then(function (data) { return { ok: res.ok, data: data }; });
            })
            .then(function (result) {
                if (!result.ok) {
                    var message = result.data && result.data.errors
                        ? Object.values(result.data.errors).join(' ')
                        : ((result.data && result.data.message) || 'Save failed');
                    throw new Error(message);
                }
                document.querySelectorAll('.js-price-status').forEach(function (el) {
                    el.innerHTML = '<span class="text-success">saved</span>';
                });
            })
            .catch(function (err) {
                document.querySelectorAll('.js-price-status').forEach(function (el) {
                    el.innerHTML = '<span class="text-danger">' + err.message + '</span>';
                });
            })
            .finally(function () {
                saveBtn.disabled = false;
            });
    });
})();
</script>
@endpush

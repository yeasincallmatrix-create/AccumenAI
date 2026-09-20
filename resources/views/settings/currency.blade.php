@extends('layouts.institute')

@section('title', 'Currency Settings — AccumenAI')

@push('styles')
<style>
  /* Match settings index: hide navbar/sidebar on settings sub-pages */
  .topbar, .sidebar, .sidebar-backdrop { display: none !important; }
  .layout { display: block !important; }
  .content { margin-left: 0 !important; padding-top: 1.25rem !important; max-width: 100% !important; }
</style>
@endpush

@section('content')

<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div class="page-header-text">
        <h4 class="page-header-title"><i class="bi bi-cash-coin me-2"></i>Currency Settings</h4>
        <p class="page-header-desc mb-0">Configure your institute's base currency, display format, and multi-currency options.</p>
    </div>
    <a href="{{ route('settings.index') }}" class="btn btn-outline-secondary rounded-pill px-3">
        <i class="bi bi-arrow-left me-1"></i>Back to Settings
    </a>
</div>

@if(session('status'))
    <div class="alert alert-success py-2">{{ session('status') }}</div>
@endif

@if($errors->any())
    <div class="alert alert-danger py-2">
        <ul class="mb-0">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="row g-4">
    {{-- Main Settings Card --}}
    <div class="col-lg-8">
        <div class="admin-card">
            <div class="table-toolbar">
                <div class="toolbar-info"><i class="bi bi-gear me-1"></i> Currency Configuration</div>
            </div>

            <form method="POST" action="{{ route('settings.currency.update') }}">
                @csrf
                @method('PUT')

                {{-- Country Selection --}}
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold" for="country_code">Country (Auto-detect)</label>
                        <select id="country_code" name="country_code" class="form-select form-select-sm" data-country-select>
                            <option value="">— Select Country —</option>
                            @foreach($countryMap as $map)
                                <option value="{{ $map->country_code }}"
                                    data-currency="{{ $map->currency_code }}"
                                    @selected($setting->country_code === $map->country_code)>
                                    {{ $map->country_name }} ({{ $map->country_code }})
                                </option>
                            @endforeach
                        </select>
                        <div class="form-text">Selecting a country auto-fills the base currency.</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold" for="base_currency">Base Currency *</label>
                        <select id="base_currency" name="base_currency" class="form-select form-select-sm" required>
                            @foreach($currencies as $currency)
                                <option value="{{ $currency->code }}"
                                    data-symbol="{{ $currency->symbol }}"
                                    @selected($setting->base_currency === $currency->code)>
                                    {{ $currency->code }} — {{ $currency->name }} ({{ $currency->symbol }})
                                </option>
                            @endforeach
                        </select>
                        @error('base_currency')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                </div>

                <hr>

                {{-- Multi-Currency Toggle --}}
                <div class="mb-4">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <label class="form-label fw-semibold mb-0">Multi-Currency Mode</label>
                            <div class="form-text">Enable to use multiple currencies across transactions and invoices.</div>
                        </div>
                        <div class="form-check form-switch">
                            <input type="hidden" name="multi_currency_enabled" value="0">
                            <input class="form-check-input" type="checkbox" name="multi_currency_enabled" value="1"
                                   id="multiCurrencyToggle"
                                   @checked($setting->multi_currency_enabled)
                                   data-toggle-multi>
                        </div>
                    </div>
                </div>

                {{-- Available Currencies (shown only when multi-currency enabled) --}}
                <div class="mb-4" id="availableCurrenciesSection" style="{{ $setting->multi_currency_enabled ? '' : 'display:none' }}">
                    <label class="form-label fw-semibold">Available Currencies</label>
                    <div class="row g-2">
                        @foreach($currencies as $currency)
                            <div class="col-md-4 col-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox"
                                           name="available_currencies[]"
                                           value="{{ $currency->code }}"
                                           id="curr_{{ $currency->code }}"
                                           {{ in_array($currency->code, $setting->available_currencies ?? []) ? 'checked' : '' }}
                                           {{ $currency->code === $setting->base_currency ? 'disabled' : '' }}>
                                    <label class="form-check-label" for="curr_{{ $currency->code }}">
                                        {{ $currency->symbol }} {{ $currency->code }}
                                        <span class="text-muted small">— {{ $currency->name }}</span>
                                    </label>
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <input type="hidden" name="available_currencies[]" value="{{ $setting->base_currency }}">
                    @error('available_currencies')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                </div>

                <hr>

                {{-- Display Format --}}
                <div class="mb-4">
                    <div class="table-toolbar mb-3">
                        <div class="toolbar-info"><i class="bi bi-eye me-1"></i> Display Format</div>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label fw-semibold" for="currency_position">Symbol Position *</label>
                            <select id="currency_position" name="currency_position" class="form-select form-select-sm" required>
                                <option value="before" @selected($setting->currency_position === 'before')>Before amount (৳1,000)</option>
                                <option value="after" @selected($setting->currency_position === 'after')>After amount (1,000৳)</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold" for="thousand_separator">Thousand Separator *</label>
                            <select id="thousand_separator" name="thousand_separator" class="form-select form-select-sm" required>
                                <option value="," @selected($setting->thousand_separator === ',')>Comma (,)</option>
                                <option value="." @selected($setting->thousand_separator === '.')>Period (.)</option>
                                <option value=" " @selected($setting->thousand_separator === ' ')>Space</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold" for="decimal_separator">Decimal Separator *</label>
                            <select id="decimal_separator" name="decimal_separator" class="form-select form-select-sm" required>
                                <option value="." @selected($setting->decimal_separator === '.')>Period (.)</option>
                                <option value="," @selected($setting->decimal_separator === ',')>Comma (,)</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold" for="decimal_places">Decimal Places *</label>
                            <select id="decimal_places" name="decimal_places" class="form-select form-select-sm" required>
                                @for($i = 0; $i <= 4; $i++)
                                    <option value="{{ $i }}" @selected($setting->decimal_places === $i)>{{ $i }}</option>
                                @endfor
                            </select>
                        </div>
                    </div>
                </div>

                {{-- Preview --}}
                <div class="mb-4 p-3 bg-light rounded">
                    <label class="form-label fw-semibold mb-2">Preview</label>
                    <div class="d-flex gap-4 flex-wrap">
                        <div>
                            <span class="text-muted small">Amount:</span>
                            <span class="fw-bold fs-5" id="currencyPreview">৳1,234.56</span>
                        </div>
                        <div>
                            <span class="text-muted small">Zero:</span>
                            <span class="fw-bold fs-5" id="currencyPreviewZero">৳0.00</span>
                        </div>
                        <div>
                            <span class="text-muted small">Large:</span>
                            <span class="fw-bold fs-5" id="currencyPreviewLarge">৳1,234,567.89</span>
                        </div>
                    </div>
                </div>

                <button class="btn btn-primary" type="submit">
                    <i class="bi bi-check-lg me-1"></i>Save Currency Settings
                </button>
            </form>
        </div>
    </div>

    {{-- Sidebar --}}
    <div class="col-lg-4">
        <div class="admin-card mb-4">
            <div class="table-toolbar">
                <div class="toolbar-info"><i class="bi bi-info-circle me-1"></i> Quick Info</div>
            </div>
            <dl class="row mb-0 small">
                <dt class="col-sm-5">Base Currency</dt>
                <dd class="col-sm-7 fw-bold">{{ $setting->base_currency }}</dd>
                <dt class="col-sm-5">Multi-Currency</dt>
                <dd class="col-sm-7">
                    @if($setting->multi_currency_enabled)
                        <span class="badge bg-success">Enabled</span>
                    @else
                        <span class="badge bg-secondary">Disabled</span>
                    @endif
                </dd>
                <dt class="col-sm-5">Available</dt>
                <dd class="col-sm-7">{{ count($setting->available_currencies ?? []) }} currency(ies)</dd>
                <dt class="col-sm-5">Format</dt>
                <dd class="col-sm-7">{{ $setting->currency_position === 'before' ? 'Before' : 'After' }} · {{ $setting->thousand_separator }} · {{ $setting->decimal_separator }} · {{ $setting->decimal_places }}dp</dd>
            </dl>
        </div>

        @if($setting->multi_currency_enabled)
            <div class="admin-card">
                <div class="table-toolbar">
                    <div class="toolbar-info"><i class="bi bi-arrow-left-right me-1"></i> Exchange Rates</div>
                    <a href="{{ route('settings.currency.rates') }}" class="btn btn-sm btn-outline-primary">Manage</a>
                </div>
                <p class="text-muted small mb-0">Manage exchange rates between your available currencies.</p>
            </div>
        @endif
    </div>
</div>

@endsection

@push('scripts')
<script>
(function() {
    // Country auto-detect
    var countrySelect = document.querySelector('[data-country-select]');
    var baseCurrency = document.getElementById('base_currency');

    if (countrySelect && baseCurrency) {
        countrySelect.addEventListener('change', function() {
            var selected = countrySelect.options[countrySelect.selectedIndex];
            var currency = selected.getAttribute('data-currency');
            if (currency) {
                baseCurrency.value = currency;
                baseCurrency.dispatchEvent(new Event('change'));
            }
        });
    }

    // Multi-currency toggle
    var toggle = document.querySelector('[data-toggle-multi]');
    var section = document.getElementById('availableCurrenciesSection');

    if (toggle && section) {
        toggle.addEventListener('change', function() {
            section.style.display = toggle.checked ? '' : 'none';
        });
    }

    // Live preview
    var pos = document.getElementById('currency_position');
    var thousand = document.getElementById('thousand_separator');
    var decimal = document.getElementById('decimal_separator');
    var places = document.getElementById('decimal_places');

    function updatePreview() {
        if (!baseCurrency) return;
        var symbol = baseCurrency.options[baseCurrency.selectedIndex]?.getAttribute('data-symbol') || '';
        var posVal = pos?.value || 'before';
        var sep = thousand?.value || ',';
        var dec = decimal?.value || '.';
        var dp = parseInt(places?.value || '2', 10);

        function fmt(num) {
            var parts = num.toFixed(dp).split('.');
            parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, sep);
            var formatted = parts.join(dec);
            return posVal === 'before' ? symbol + formatted : formatted + symbol;
        }

        var prev = document.getElementById('currencyPreview');
        var prevZero = document.getElementById('currencyPreviewZero');
        var prevLarge = document.getElementById('currencyPreviewLarge');
        if (prev) prev.textContent = fmt(1234.56);
        if (prevZero) prevZero.textContent = fmt(0);
        if (prevLarge) prevLarge.textContent = fmt(1234567.89);
    }

    [pos, thousand, decimal, places, baseCurrency].forEach(function(el) {
        if (el) el.addEventListener('change', updatePreview);
    });
    updatePreview();
})();
</script>
@endpush

{{--
    Global Address Selector — one reusable, country-neutral component.

    Shows Country → Level 1 → Level 2 → Level 3. The level labels come from the
    per-country administrative-level configuration (administrative_levels.name),
    so the same component renders Division/District/Upazila for Bangladesh and
    State/County/City for the United States without hard-coding any country.

    Actual location records are rendered server-side from the supplied
    $levelOptions (administrative_units). Countries without records simply show
    empty selects until their location data is provided.

    Usage (student form, prefix e.g. "present_" / "permanent_"):

        <x-address :prefix="'present_'"
                   :country-id="old('present_country_id', $student->present_country_id)"
                   :level-labels="$presentLabels"
                   :level-1-options="$level1Options"
                   :level-2-options="$level2Options"
                   :level-3-options="$level3Options" />

    Props:
        @prop string $prefix        field-name prefix ('' for bare names)
        @prop mixed  $countryId     selected country id
        @prop mixed  $level1Id/$level2Id/$level3Id  selected unit ids
        @prop array  $levelLabels   [1 => label, 2 => label, 3 => label]
        @prop array  $level1Options [id => name] ... options per level
        @prop mixed  $postalCode, $address
--}}
@props([
    'prefix' => '',
    'countryId' => null,
    'level1Id' => null,
    'level2Id' => null,
    'level3Id' => null,
    'levelLabels' => [],
    'level1Options' => [],
    'level2Options' => [],
    'level3Options' => [],
    'postalCode' => null,
    'address' => null,
    'locked' => false,
    'addressLabel' => null,
    'zipFirst' => false,
])

@php
    $labels = $levelLabels + [1 => 'Level 1', 2 => 'Level 2', 3 => 'Level 3'];
    $p = $prefix;
@endphp

<div class="address-component" data-address-component
     data-prefix="{{ $p }}"
     data-country-id="{{ $countryId ?? '' }}">

    <div class="grid">
        <div class="field field-fill" @if ($locked) hidden @endif>
            <label for="{{ $p }}country_id">{{ mawa_lang('geo.country') }}</label>
            <select id="{{ $p }}country_id" name="{{ $p }}country_id"
                    class="address-country-select" data-address-country
                    data-label-endpoint="{{ route('geo.levels', ['country' => '__ID__']) }}"
                    data-units-endpoint="{{ route('geo.units') }}">
                <option value="">-- {{ mawa_lang('geo.select_country') }} --</option>
                @foreach (\App\Models\Country::query()->where('status', true)->orderBy('name')->get() as $country)
                    @php
                        $__cLabels = \App\Support\GeoHierarchy::levelLabels($country);
                    @endphp
                    <option value="{{ $country->id }}"
                            data-iso2="{{ $country->iso2 }}"
                            data-label-1="{{ $__cLabels[1] ?? '' }}"
                            data-label-2="{{ $__cLabels[2] ?? '' }}"
                            data-label-3="{{ $__cLabels[3] ?? '' }}"
                            @selected((string) old($p.'country_id', $countryId) === (string) $country->id)>
                        {{ $country->name }}
                    </option>
                @endforeach
            </select>
        </div>

        @foreach ([1, 2, 3] as $level)
            @php
                $idAttr = ['1' => $level1Id, '2' => $level2Id, '3' => $level3Id][(string) $level];
                $options = ['1' => $level1Options, '2' => $level2Options, '3' => $level3Options][(string) $level];
            @endphp
            <div class="field" data-address-level data-level="{{ $level }}">
                <label for="{{ $p }}admin_{{ $level }}_id" data-address-label="{{ $level }}">
                    {{ $labels[$level] ?? ('Level '.$level) }}
                </label>
                <select id="{{ $p }}admin_{{ $level }}_id" name="{{ $p }}admin_{{ $level }}_id"
                        class="address-level-select" data-address-unit data-level="{{ $level }}">
                    <option value="">-- {{ $labels[$level] ?? ('Level '.$level) }} --</option>
                    @foreach ($options as $value => $name)
                        <option value="{{ $value }}" @selected((string) old($p.'admin_'.$level.'_id', $idAttr) === (string) $value)>{{ $name }}</option>
                    @endforeach
                </select>
            </div>
        @endforeach
    </div>

    <div class="grid mt-2">
        @if ($zipFirst)
            <div class="field">
                <label for="{{ $p }}zip_code">{{ mawa_lang('geo.postal_code') }}</label>
                <input id="{{ $p }}zip_code" name="{{ $p }}zip_code" class="form-control"
                       value="{{ $postalCode }}" maxlength="20" placeholder="{{ mawa_lang('geo.postal_hint') }}">
            </div>
            <div class="field">
                <label for="{{ $p }}address">{{ $addressLabel ?? mawa_lang('geo.street') }}</label>
                <input id="{{ $p }}address" name="{{ $p }}address" class="form-control"
                       value="{{ $address }}" maxlength="255" placeholder="{{ mawa_lang('geo.street_hint') }}">
            </div>
        @else
            <div class="field">
                <label for="{{ $p }}address">{{ $addressLabel ?? mawa_lang('geo.street') }}</label>
                <input id="{{ $p }}address" name="{{ $p }}address" class="form-control"
                       value="{{ $address }}" maxlength="255" placeholder="{{ mawa_lang('geo.street_hint') }}">
            </div>
            <div class="field">
                <label for="{{ $p }}zip_code">{{ mawa_lang('geo.postal_code') }}</label>
                <input id="{{ $p }}zip_code" name="{{ $p }}zip_code" class="form-control"
                       value="{{ $postalCode }}" maxlength="20" placeholder="{{ mawa_lang('geo.postal_hint') }}">
            </div>
        @endif
    </div>
</div>

{{-- Tenant-aware date input driven by Settings → General → Date Format
     (dmy → DD/MM/YYYY, mdy → MM/DD/YYYY, ymd → YYYY/MM/DD).
     Always pairs a visible text input with a hidden ISO (server receives Y-m-d),
     plus a calendar button that opens a native picker and converts the picked
     date back into the tenant display format.
     Extra attributes (class, required, min, max…) land on the visible input. --}}
@props(['name', 'value' => null, 'id' => null])
@php
    $id = $id ?? $name;
    $iso = $value !== null && $value !== '' ? (mawa_parse_date($value) ?? '') : '';
    $order = mawa_date_format_key();
    $placeholder = mawa_date_placeholder();
    $isSm = str_contains((string) ($attributes->get('class') ?? ''), 'form-control-sm');
@endphp
<input type="hidden" id="{{ $id }}" name="{{ $name }}" value="{{ $iso }}" data-tdate-hidden>
<div class="input-group position-relative{{ $isSm ? ' input-group-sm' : '' }}">
    <input type="text" id="{{ $id }}_display" data-tdate-display="{{ $id }}" data-date-order="{{ $order }}"
           value="{{ $iso !== '' ? mawa_format_date($iso) : '' }}"
           placeholder="{{ $placeholder }}" inputmode="numeric" autocomplete="off" {{ $attributes }}>
    <button type="button" class="btn btn-outline-secondary" data-tdate-picker="{{ $id }}"
            title="Pick a date ({{ $placeholder }})" aria-label="Pick a date">
        <i class="bi bi-calendar3"></i>
    </button>
</div>

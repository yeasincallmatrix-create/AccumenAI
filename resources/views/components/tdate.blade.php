{{-- Tenant-aware date display. Bangladesh → DD/MM/YYYY, others → $fallback format.
     $empty is shown when the value is missing (mirrors `?? '—'` patterns). --}}
@props(['value' => null, 'fallback' => 'Y-m-d', 'datetime' => false, 'empty' => ''])
@php
    $out = $datetime
        ? mawa_format_datetime($value, null, $fallback)
        : mawa_format_date($value, null, $fallback);
@endphp
{{ $out !== '' ? $out : $empty }}

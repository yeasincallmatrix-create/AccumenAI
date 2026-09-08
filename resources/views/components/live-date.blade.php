{{-- Livewire-aware tenant date input driven by Settings → General → Date Format
     (dmy → DD/MM/YYYY, mdy → MM/DD/YYYY, ymd → YYYY/MM/DD).
     Shows a masked text input + calendar button; syncs ISO (Y-m-d) to Livewire
     through a hidden wire:model.live input. The text input carries no name, so
     plain form submits are unaffected. JS pairing lives in layouts/partials/tenant_dates. --}}
@props(['model', 'value' => ''])
@php
    $iso = $value !== null && $value !== '' ? (mawa_parse_date($value) ?? '') : '';
    $order = mawa_date_format_key();
    $isSm = str_contains((string) ($attributes->get('class') ?? ''), 'form-control-sm');
@endphp
<span data-live-date-wrap>
    <input type="hidden" wire:model.live="{{ $model }}" value="{{ $iso }}" data-live-date-hidden>
    <div class="input-group position-relative{{ $isSm ? ' input-group-sm' : '' }}">
        <input type="text" data-live-date-display data-date-order="{{ $order }}"
               value="{{ $iso !== '' ? mawa_format_date($iso) : '' }}"
               placeholder="{{ mawa_date_placeholder() }}" inputmode="numeric" autocomplete="off" {{ $attributes }}>
        <button type="button" class="btn btn-outline-secondary" data-live-date-picker
                title="Pick a date ({{ mawa_date_placeholder() }})" aria-label="Pick a date">
            <i class="bi bi-calendar3"></i>
        </button>
    </div>
</span>

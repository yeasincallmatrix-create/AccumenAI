@php
    $primary = $themePrimary ?? '#0D6EFD';
    $secondary = $themeSecondary ?? '#FFC107';
    $primaryRgb = mawa_hex_to_rgb($primary);
    $primaryDark = mawa_darken_hex($primary, 0.88);
    $primaryDarker = mawa_darken_hex($primary, 0.76);
@endphp
@if ($themePrimary || $themeSecondary)
    <style>
        :root{
            --primary: {{ $primary }};
            --primary-soft: rgba({{ $primaryRgb }}, .1);
            --secondary: {{ $secondary }};
            --bs-primary: {{ $primary }};
            --bs-primary-rgb: {{ $primaryRgb }};
            --bs-primary-hover: {{ $primaryDark }};
            --bs-primary-active: {{ $primaryDarker }};
            --bs-link-color: {{ $primary }};
            --bs-link-color-rgb: {{ $primaryRgb }};
            --bs-link-hover-color: {{ $primaryDark }};
            --bs-link-hover-color-rgb: {{ mawa_hex_to_rgb($primaryDark) }};
            --bs-emphasis-color-primary: {{ $primary }};
            --bs-border-color-primary: {{ $primary }};
            --bs-btn-primary-bg: {{ $primary }};
            --bs-btn-primary-border-color: {{ $primary }};
            --bs-btn-primary-hover-bg: {{ $primaryDark }};
            --bs-btn-primary-hover-border-color: {{ $primaryDark }};
            --bs-btn-primary-active-bg: {{ $primaryDarker }};
            --bs-btn-primary-active-border-color: {{ $primaryDarker }};
            --bs-btn-primary-disabled-bg: {{ $primary }};
            --bs-btn-primary-disabled-border-color: {{ $primary }};
            --bs-pagination-active-bg: {{ $primary }};
            --bs-pagination-active-border-color: {{ $primary }};
            --bs-focus-ring-color: rgba({{ $primaryRgb }}, .25);
        }
    </style>
@endif

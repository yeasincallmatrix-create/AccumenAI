@props([
    'name' => 'Halumoni Computer Training Center',
    'subtitle' => null,
    'logo' => null,
    'verified' => false,
    'size' => 'md', // sm | md | lg
])

@php
    $initials = strtoupper(mb_substr($name, 0, 1));
    if (str_contains($name, ' ')) {
        $parts = explode(' ', trim($name));
        $initials = strtoupper(mb_substr($parts[0],0,1) . mb_substr(end($parts),0,1));
        $initials = mb_substr($initials,0,2);
    }
    $sizeClass = match($size) {
        'sm' => 'name-holder-sm',
        'lg' => 'name-holder-lg',
        default => '',
    };
@endphp

<div class="name-holder-card {{ $sizeClass }} {{ $attributes->get('class') }}" {{ $attributes->except('class') }}>
    <div class="name-holder-clip">
        <div class="name-holder-pin"></div>
        <div class="name-holder-strip"></div>
    </div>
    <div class="name-holder-body">
        <div class="name-holder-avatar">
            @if ($logo)
                <img src="{{ $logo }}" alt="{{ $name }}">
            @else
                <span class="name-holder-initials">{{ $initials }}</span>
            @endif
        </div>
        <div class="name-holder-name">
            {{ $name }}
            @if ($verified)
                <i class="bi bi-patch-check-fill text-primary ms-1" title="Verified"></i>
            @endif
        </div>
        @if ($subtitle)
            <div class="name-holder-subtitle">{{ $subtitle }}</div>
        @endif
        <div class="name-holder-line"></div>
        <div class="name-holder-footer">
            <span class="name-holder-badge"><i class="bi bi-building me-1"></i>Business</span>
            <span class="name-holder-id">ID: {{ strtoupper(substr(md5($name),0,6)) }}</span>
        </div>
    </div>
</div>

@once
@push('styles')
<style>
.name-holder-card {
    width: 100%;
    max-width: 340px;
    margin: 0 auto;
    background: #fff;
    border-radius: 16px;
    box-shadow: 0 8px 30px rgba(15,23,42,.12), 0 1px 3px rgba(15,23,42,.06);
    overflow: hidden;
    border: 1px solid #e9ecef;
    font-family: 'Poppins','Hind Siliguri',system-ui,sans-serif;
}
.name-holder-clip {
    height: 28px;
    background: linear-gradient(135deg, #0D6EFD 0%, #6f42c1 100%);
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;
}
.name-holder-pin {
    width: 48px;
    height: 10px;
    background: #e9ecef;
    border-radius: 999px;
    box-shadow: inset 0 1px 2px rgba(0,0,0,.15), 0 1px 3px rgba(0,0,0,.2);
    position: absolute;
    top: -5px;
    left: 50%;
    transform: translateX(-50%);
    border: 1px solid #dee2e6;
}
.name-holder-pin::after {
    content: '';
    position: absolute;
    left: 50%;
    top: 50%;
    width: 14px;
    height: 14px;
    background: #fff;
    border: 2px solid #adb5bd;
    border-radius: 50%;
    transform: translate(-50%,-50%);
}
.name-holder-strip {
    width: 70px;
    height: 4px;
    background: rgba(255,255,255,.35);
    border-radius: 999px;
    margin-top: 8px;
}
.name-holder-body {
    padding: 22px 20px 18px;
    text-align: center;
}
.name-holder-avatar {
    width: 72px;
    height: 72px;
    border-radius: 50%;
    margin: 0 auto 12px;
    background: linear-gradient(135deg, #0D6EFD, #6f42c1);
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    font-weight: 800;
    font-size: 1.6rem;
    border: 3px solid #fff;
    box-shadow: 0 4px 14px rgba(13,110,253,.25);
    overflow: hidden;
}
.name-holder-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.name-holder-initials { letter-spacing: .5px; }
.name-holder-name {
    font-weight: 800;
    font-size: 1.05rem;
    color: #1a1d27;
    line-height: 1.3;
    word-break: break-word;
}
.name-holder-subtitle {
    font-size: .85rem;
    color: #6c757d;
    margin-top: 4px;
}
.name-holder-line {
    height: 1px;
    background: #e9ecef;
    margin: 14px 0 12px;
}
.name-holder-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    font-size: .75rem;
    color: #6c757d;
}
.name-holder-badge {
    background: #f8f9fa;
    border: 1px solid #e9ecef;
    padding: 4px 10px;
    border-radius: 999px;
    font-weight: 600;
}
.name-holder-id {
    font-family: monospace;
    letter-spacing: .5px;
    opacity: .8;
}
.name-holder-sm { max-width: 260px; }
.name-holder-sm .name-holder-avatar { width: 56px; height: 56px; font-size: 1.2rem; }
.name-holder-sm .name-holder-name { font-size: .95rem; }
.name-holder-lg { max-width: 420px; }
.name-holder-lg .name-holder-avatar { width: 88px; height: 88px; font-size: 1.9rem; }
.name-holder-lg .name-holder-name { font-size: 1.25rem; }
html.monetix-dark .name-holder-card { background: #1a1d27; border-color: #2a2f3a; }
html.monetix-dark .name-holder-name { color: #e9ecef; }
html.monetix-dark .name-holder-line { background: #2a2f3a; }
html.monetix-dark .name-holder-badge { background: #222633; border-color: #2a2f3a; color: #adb5bd; }
</style>
@endpush
@endonce

<!DOCTYPE html>
<html lang="{{ mawa_current_lang() }}" data-user-theme="{{ $userTheme }}" @class([
    'monetix-dark' => $userTheme === 'dark',
]) data-bs-theme="{{ $userTheme === 'dark' ? 'dark' : 'light' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="login-url" content="{{ route('login') }}">
    <title>@yield('title', 'AccumenAI')</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@400;500;600;700&family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="{{ asset('css/base.css') }}" rel="stylesheet">
    <link href="{{ asset('css/layout.css') }}" rel="stylesheet">
    <link href="{{ asset('css/components.css') }}" rel="stylesheet">
    @include('layouts.partials.theme_colors')
    @stack('styles')
</head>
<body>

@include('partials.page_marker')

<header class="topbar">
    <div class="container-fluid px-4 d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center gap-3">
            <a class="btn btn-sm btn-outline-secondary rounded-pill px-3" href="{{ $backUrl ?? route('dashboard') }}">
                <i class="bi bi-arrow-left me-1"></i>{{ isset($backUrl) ? mawa_e('settings_page.back_to_hub') : mawa_e('settings_page.back') }}
            </a>
            <span class="standalone-page-title">@yield('page_title')</span>
        </div>
        <div class="d-flex align-items-center gap-2">
            <div class="dropdown">
                <button class="icon-btn" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="{{ mawa_e('lang.label') }}">
                    <i class="bi bi-translate"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item {{ mawa_current_lang() === 'en' ? 'active' : '' }}" href="{{ request()->fullUrlWithQuery(['lang' => 'en']) }}">English</a></li>
                    <li><a class="dropdown-item {{ mawa_current_lang() === 'bn' ? 'active' : '' }}" href="{{ request()->fullUrlWithQuery(['lang' => 'bn']) }}">বাংলা</a></li>
                </ul>
            </div>
            <button class="icon-btn" type="button" id="monetixDarkToggle" aria-label="Toggle dark mode">
                <i class="bi bi-moon-stars-fill" id="monetixDarkIcon"></i>
            </button>
        </div>
    </div>
</header>

<main class="standalone-page">
    <div class="standalone-container">

        @if (session('status'))
            <div class="alert alert-success" data-auto-dismiss>
                <i class="bi bi-check-circle-fill"></i> {{ session('status') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="alert alert-danger" data-auto-dismiss>
                <i class="bi bi-exclamation-triangle-fill"></i> {{ $errors->first() }}
            </div>
        @endif

        @yield('content')

    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="{{ asset('js/flash.js') }}?v={{ \Illuminate\Support\Facades\File::lastModified(public_path('js/flash.js')) }}"></script>
<script src="{{ asset('js/password-toggle.js') }}?v={{ \Illuminate\Support\Facades\File::lastModified(public_path('js/password-toggle.js')) }}"></script>
<script src="{{ asset('js/popup-fix.js') }}?v={{ \Illuminate\Support\Facades\File::lastModified(public_path('js/popup-fix.js')) }}"></script>
<script src="{{ asset('js/password-policy.js') }}?v={{ \Illuminate\Support\Facades\File::lastModified(public_path('js/password-policy.js')) }}"></script>
<script>
(function () {
    var root = document.documentElement;
    var btn  = document.getElementById('monetixDarkToggle');
    var icon = document.getElementById('monetixDarkIcon');
    function isDark() {
        return root.classList.contains('monetix-dark') ||
               root.getAttribute('data-bs-theme') === 'dark';
    }
    function apply(enabled) {
        root.classList.toggle('monetix-dark', enabled);
        root.setAttribute('data-bs-theme', enabled ? 'dark' : 'light');
        icon.className = enabled ? 'bi bi-sun-fill' : 'bi bi-moon-stars-fill';
        root.dataset.userTheme = enabled ? 'dark' : 'light';
    }
    function persist(enabled) {
        fetch('{{ route('account.preferences.theme') }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({ theme: enabled ? 'dark' : 'light' })
        });
    }
    var saved = null;
    try { saved = localStorage.getItem('monetix_ui_dark_admin'); } catch (e) {}
    var serverTheme = root.dataset.userTheme || 'default';
    if (saved !== null && serverTheme === 'default') {
        apply(saved === '1');
        persist(saved === '1');
    } else if (serverTheme !== 'default') {
        apply(serverTheme === 'dark');
        icon.className = isDark() ? 'bi bi-sun-fill' : 'bi bi-moon-stars-fill';
    } else {
        icon.className = isDark() ? 'bi bi-sun-fill' : 'bi bi-moon-stars-fill';
    }
    if (btn) {
        btn.addEventListener('click', function () {
            var enabled = !isDark();
            apply(enabled);
            persist(enabled);
        });
    }
})();
</script>
@yield('scripts')
@stack('scripts')
<script>
if (typeof window.copyToClipboard !== 'function') {
window.copyToClipboard = function(text, button) {
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text).then(function(){
            var original = button.innerHTML;
            button.innerHTML = '<i class="bi bi-check-lg text-success"></i>';
            setTimeout(function(){ button.innerHTML = original; }, 2000);
        }).catch(function(){ fallbackCopy(text, button); });
    } else { fallbackCopy(text, button); }
    function fallbackCopy(val, btn){
        var input=document.createElement('input'); input.value=val; input.style.position='fixed'; input.style.opacity='0';
        document.body.appendChild(input); input.select(); try{ document.execCommand('copy'); }catch(e){} document.body.removeChild(input);
        var original=btn.innerHTML; btn.innerHTML='<i class="bi bi-check-lg text-success"></i>'; setTimeout(function(){ btn.innerHTML=original; },2000);
    }
};
}
</script>
</body>
</html>
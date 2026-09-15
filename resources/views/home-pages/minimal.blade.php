@extends('layouts.app')

@section('title', $homePage->hero_title . ' — AccumenAI')

@section('content')
<!-- Navigation -->
<nav class="sticky top-0 z-50 bg-white/80 backdrop-blur-md border-b border-gray-100">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between items-center h-16">
            <div class="flex items-center gap-2">
                <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-gray-700 to-gray-900 flex items-center justify-center text-white font-bold text-lg">A</div>
                <span class="text-xl font-bold text-gray-900">{{ $platformBrandName ?? 'Accumen' }}<span class="text-gray-700">AI</span></span>
            </div>
            <div class="flex items-center gap-3">
                @auth
                    <a href="{{ route('dashboard') }}" class="px-5 py-2.5 bg-gray-900 text-white text-sm font-semibold rounded-full hover:bg-gray-800 transition">Dashboard</a>
                @else
                    @if (Route::has('login'))
                        <a href="{{ route('login') }}" class="text-sm font-medium text-gray-700 hover:text-gray-900 px-4 py-2 transition">Log in</a>
                    @endif
                    @if (Route::has('owner.register'))
                        <a href="{{ $homePage->hero_cta_url ?? route('owner.register') }}" class="px-5 py-2.5 bg-gray-900 text-white text-sm font-semibold rounded-full hover:bg-gray-800 transition">{{ $homePage->hero_cta_text }}</a>
                    @endif
                @endauth
            </div>
        </div>
    </div>
</nav>

<!-- Hero — Minimal -->
<section class="py-24 bg-white">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        @if($homePage->hero_badge)
        <div class="inline-flex items-center gap-2 px-3 py-1.5 bg-gray-100 text-gray-700 rounded-full text-xs font-semibold mb-6">
            {{ $homePage->hero_badge }}
        </div>
        @endif
        <h1 class="text-4xl sm:text-5xl lg:text-6xl font-extrabold text-gray-900 leading-tight">
            {{ $homePage->hero_title }}
        </h1>
        @if($homePage->hero_subtitle)
        <p class="mt-6 text-lg text-gray-600 leading-relaxed max-w-2xl mx-auto">{{ $homePage->hero_subtitle }}</p>
        @endif
        <div class="mt-10 flex flex-col sm:flex-row gap-4 justify-center">
            @if (Route::has('owner.register'))
                <a href="{{ $homePage->hero_cta_url ?? route('owner.register') }}" class="inline-flex items-center justify-center gap-2 px-8 py-4 bg-gray-900 text-white font-semibold rounded-full hover:bg-gray-800 transition">
                    {{ $homePage->hero_cta_text }} <i class="bi bi-arrow-right"></i>
                </a>
            @endif
            <a href="{{ Route::has('login') ? route('login') : '#' }}" class="inline-flex items-center justify-center gap-2 px-8 py-4 bg-white text-gray-900 font-semibold rounded-full border border-gray-300 hover:bg-gray-50 transition">
                Sign In
            </a>
        </div>
    </div>
</section>

<!-- Features — Minimal -->
<section class="py-20 bg-gray-50">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-16">
            <h2 class="text-3xl sm:text-4xl font-extrabold text-gray-900">Simple, powerful, unified</h2>
            <p class="mt-4 text-gray-600">Everything you need in one platform.</p>
        </div>
        <div class="grid md:grid-cols-3 gap-8">
            <div class="text-center p-8">
                <div class="w-14 h-14 bg-gray-900 rounded-2xl flex items-center justify-center text-white text-2xl mx-auto mb-6"><i class="bi bi-layers-fill"></i></div>
                <h3 class="text-lg font-bold text-gray-900 mb-2">Unified Platform</h3>
                <p class="text-gray-600 text-sm">CRM, HR, Finance, Inventory, Sales — all connected.</p>
            </div>
            <div class="text-center p-8">
                <div class="w-14 h-14 bg-gray-900 rounded-2xl flex items-center justify-center text-white text-2xl mx-auto mb-6"><i class="bi bi-robot"></i></div>
                <h3 class="text-lg font-bold text-gray-900 mb-2">AI-Powered</h3>
                <p class="text-gray-600 text-sm">Smart insights, automation, and recommendations.</p>
            </div>
            <div class="text-center p-8">
                <div class="w-14 h-14 bg-gray-900 rounded-2xl flex items-center justify-center text-white text-2xl mx-auto mb-6"><i class="bi bi-shield-lock-fill"></i></div>
                <h3 class="text-lg font-bold text-gray-900 mb-2">Secure</h3>
                <p class="text-gray-600 text-sm">Role-based access, encryption, and audit logs.</p>
            </div>
        </div>
    </div>
</section>

<!-- CTA -->
<section class="py-20 bg-white">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <h2 class="text-3xl sm:text-4xl font-extrabold text-gray-900">Get started today</h2>
        <p class="mt-4 text-gray-600 text-lg">Free for small teams. No credit card required.</p>
        <div class="mt-8">
            @if (Route::has('owner.register'))
                <a href="{{ $homePage->hero_cta_url ?? route('owner.register') }}" class="inline-flex items-center justify-center gap-2 px-8 py-4 bg-gray-900 text-white font-bold rounded-full hover:bg-gray-800 transition">
                    {{ $homePage->hero_cta_text }} <i class="bi bi-arrow-right"></i>
                </a>
            @endif
        </div>
    </div>
</section>

<footer class="border-t border-gray-200 py-8">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex justify-between items-center">
        <p class="text-sm text-gray-500">&copy; {{ date('Y') }} {{ $platformBrandName ?? 'AccumenAI' }}.</p>
        <p class="text-sm text-gray-500">Made with <i class="bi bi-heart-fill text-red-500"></i></p>
    </div>
</footer>
@endsection

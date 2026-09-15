@extends('layouts.app')

@section('title', $homePage->hero_title . ' — AccumenAI Education')

@section('content')
<!-- Navigation -->
<nav class="sticky top-0 z-50 bg-white/80 backdrop-blur-md border-b border-gray-100">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between items-center h-16">
            <div class="flex items-center gap-2">
                <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-blue-600 to-indigo-600 flex items-center justify-center text-white font-bold text-lg">A</div>
                <span class="text-xl font-bold text-gray-900">{{ $platformBrandName ?? 'Accumen' }}<span class="text-blue-600">AI</span></span>
            </div>
            <div class="hidden md:flex items-center gap-8">
                <a href="#features" class="text-sm font-medium text-gray-600 hover:text-blue-600 transition">Features</a>
                <a href="#modules" class="text-sm font-medium text-gray-600 hover:text-blue-600 transition">Modules</a>
                <a href="#testimonials" class="text-sm font-medium text-gray-600 hover:text-blue-600 transition">Testimonials</a>
            </div>
            <div class="flex items-center gap-3">
                @auth
                    <a href="{{ route('dashboard') }}" class="hidden sm:inline-flex items-center gap-2 px-5 py-2.5 bg-blue-600 text-white text-sm font-semibold rounded-full hover:bg-blue-700 transition">
                        <i class="bi bi-speedometer2"></i> Dashboard
                    </a>
                @else
                    @if (Route::has('login'))
                        <a href="{{ route('login') }}" class="text-sm font-medium text-gray-700 hover:text-blue-600 px-4 py-2 transition">Log in</a>
                    @endif
                    @if (Route::has('owner.register'))
                        <a href="{{ $homePage->hero_cta_url ?? route('owner.register') }}" class="inline-flex items-center gap-2 px-5 py-2.5 bg-blue-600 text-white text-sm font-semibold rounded-full hover:bg-blue-700 shadow-lg shadow-blue-600/20 transition">
                            {{ $homePage->hero_cta_text }} <i class="bi bi-arrow-right"></i>
                        </a>
                    @endif
                @endauth
            </div>
        </div>
    </div>
</nav>

<!-- Hero — Education -->
<section class="relative overflow-hidden bg-gradient-to-br from-blue-50 via-indigo-50 to-white">
    <div class="absolute inset-0 bg-grid-slate-100 [mask-image:linear-gradient(0deg,white,rgba(255,255,255,0.6))]"></div>
    <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20 lg:py-28">
        <div class="grid lg:grid-cols-2 gap-12 items-center">
            <div>
                @if($homePage->hero_badge)
                <div class="inline-flex items-center gap-2 px-3 py-1.5 bg-blue-100 text-blue-700 rounded-full text-xs font-semibold mb-6">
                    <span class="w-2 h-2 bg-blue-600 rounded-full animate-pulse"></span>
                    {{ $homePage->hero_badge }}
                </div>
                @endif
                <h1 class="text-4xl sm:text-5xl lg:text-6xl font-extrabold text-gray-900 leading-tight">
                    {{ $homePage->hero_title }}
                </h1>
                @if($homePage->hero_subtitle)
                <p class="mt-6 text-lg text-gray-600 leading-relaxed">{{ $homePage->hero_subtitle }}</p>
                @endif
                <div class="mt-8 flex flex-col sm:flex-row gap-4">
                    @if (Route::has('owner.register'))
                        <a href="{{ $homePage->hero_cta_url ?? route('owner.register') }}" class="inline-flex items-center justify-center gap-2 px-8 py-4 bg-blue-600 text-white font-semibold rounded-full hover:bg-blue-700 shadow-xl shadow-blue-600/20 transition">
                            {{ $homePage->hero_cta_text }} <i class="bi bi-arrow-right"></i>
                        </a>
                    @endif
                    <a href="#features" class="inline-flex items-center justify-center gap-2 px-8 py-4 bg-white text-gray-900 font-semibold rounded-full border border-gray-200 hover:border-gray-300 hover:bg-gray-50 transition">
                        <i class="bi bi-play-circle"></i> Explore Features
                    </a>
                </div>
                <div class="mt-8 flex items-center gap-6 text-sm text-gray-500">
                    <span class="flex items-center gap-2"><i class="bi bi-check-circle-fill text-green-500"></i> Free for 50 students</span>
                    <span class="flex items-center gap-2"><i class="bi bi-check-circle-fill text-green-500"></i> No credit card</span>
                </div>
            </div>
            <div class="relative">
                <div class="absolute -inset-4 bg-gradient-to-r from-blue-600/20 to-indigo-600/20 rounded-3xl blur-2xl"></div>
                <div class="relative bg-white rounded-2xl shadow-2xl border border-gray-100 p-6">
                    <div class="flex items-center gap-3 mb-6">
                        <div class="w-3 h-3 rounded-full bg-red-400"></div>
                        <div class="w-3 h-3 rounded-full bg-yellow-400"></div>
                        <div class="w-3 h-3 rounded-full bg-green-400"></div>
                        <span class="ml-auto text-xs text-gray-400">accumen.ai/academic</span>
                    </div>
                    <div class="space-y-3">
                        <div class="flex items-center gap-3 p-3 bg-blue-50 rounded-xl">
                            <div class="w-8 h-8 bg-blue-600 rounded-lg flex items-center justify-center text-white text-sm"><i class="bi bi-mortarboard-fill"></i></div>
                            <div class="flex-1"><div class="text-sm font-medium">Class X — Section A</div><div class="text-xs text-gray-500">42 students enrolled</div></div>
                            <span class="text-xs bg-green-100 text-green-700 px-2 py-1 rounded-full">Active</span>
                        </div>
                        <div class="flex items-center gap-3 p-3 bg-indigo-50 rounded-xl">
                            <div class="w-8 h-8 bg-indigo-600 rounded-lg flex items-center justify-center text-white text-sm"><i class="bi bi-clipboard-check"></i></div>
                            <div class="flex-1"><div class="text-sm font-medium">Mid-term Exam</div><div class="text-xs text-gray-500">Results published</div></div>
                            <span class="text-xs bg-blue-100 text-blue-700 px-2 py-1 rounded-full">Done</span>
                        </div>
                        <div class="flex items-center gap-3 p-3 bg-amber-50 rounded-xl">
                            <div class="w-8 h-8 bg-amber-500 rounded-lg flex items-center justify-center text-white text-sm"><i class="bi bi-receipt"></i></div>
                            <div class="flex-1"><div class="text-sm font-medium">Fee Collection</div><div class="text-xs text-gray-500">$12,400 this month</div></div>
                            <span class="text-xs bg-amber-100 text-amber-700 px-2 py-1 rounded-full">85%</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Education-Specific Features -->
<section id="features" class="py-20 bg-white">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center max-w-3xl mx-auto mb-16">
            <span class="inline-block px-3 py-1 bg-blue-100 text-blue-700 rounded-full text-xs font-bold tracking-widest uppercase mb-3">Education Features</span>
            <h2 class="text-3xl sm:text-4xl font-extrabold text-gray-900">Built for schools, colleges & universities</h2>
            <p class="mt-4 text-gray-600">Complete academic management from admissions to graduation.</p>
        </div>
        <div class="grid md:grid-cols-3 gap-8">
            <div class="group bg-white rounded-2xl border border-gray-100 p-8 hover:shadow-xl hover:border-blue-100 transition">
                <div class="w-12 h-12 bg-blue-600 rounded-xl flex items-center justify-center text-white text-xl mb-6 group-hover:scale-110 transition"><i class="bi bi-clipboard-data"></i></div>
                <h3 class="text-xl font-bold text-gray-900 mb-3">Academic Management</h3>
                <p class="text-gray-600 text-sm leading-relaxed">Classes, subjects, attendance, exams, grading, promotions — all automated with configurable policies.</p>
            </div>
            <div class="group bg-white rounded-2xl border border-gray-100 p-8 hover:shadow-xl hover:border-indigo-100 transition">
                <div class="w-12 h-12 bg-indigo-600 rounded-xl flex items-center justify-center text-white text-xl mb-6 group-hover:scale-110 transition"><i class="bi bi-cash-stack"></i></div>
                <h3 class="text-xl font-bold text-gray-900 mb-3">Fee Management</h3>
                <p class="text-gray-600 text-sm leading-relaxed">Flexible fee structures, online payments, receipts, reminders, and collection reports — all in one place.</p>
            </div>
            <div class="group bg-white rounded-2xl border border-gray-100 p-8 hover:shadow-xl hover:border-green-100 transition">
                <div class="w-12 h-12 bg-green-600 rounded-xl flex items-center justify-center text-white text-xl mb-6 group-hover:scale-110 transition"><i class="bi bi-person-badge"></i></div>
                <h3 class="text-xl font-bold text-gray-900 mb-3">Student Portal</h3>
                <p class="text-gray-600 text-sm leading-relaxed">Students can view results, attendance, fees, and certificates — all from a single dashboard.</p>
            </div>
        </div>
    </div>
</section>

<!-- Academic Modules -->
<section id="modules" class="py-20 bg-gray-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center max-w-3xl mx-auto mb-12">
            <span class="inline-block px-3 py-1 bg-indigo-100 text-indigo-700 rounded-full text-xs font-bold tracking-widest uppercase mb-3">Modules</span>
            <h2 class="text-3xl sm:text-4xl font-extrabold text-gray-900">Complete education toolkit</h2>
        </div>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
            @php
                $modules = [
                    ['Admissions', 'bi-person-plus-fill', 'from-blue-500 to-blue-600'],
                    ['Attendance', 'bi-clipboard-check', 'from-green-500 to-emerald-600'],
                    ['Examinations', 'bi-file-earmark-text', 'from-amber-500 to-orange-600'],
                    ['Gradebook', 'bi-bar-chart-line', 'from-violet-500 to-purple-600'],
                    ['Timetable', 'bi-calendar-week', 'from-cyan-500 to-blue-600'],
                    ['Certificates', 'bi-award', 'from-rose-500 to-pink-600'],
                    ['Parent Portal', 'bi-people', 'from-teal-500 to-green-600'],
                    ['Reports', 'bi-graph-up', 'from-indigo-500 to-blue-600'],
                ];
            @endphp
            @foreach($modules as $m)
            <div class="group bg-white rounded-2xl border border-gray-100 p-6 text-center hover:shadow-lg transition">
                <div class="w-14 h-14 rounded-xl bg-gradient-to-br {{ $m[2] }} flex items-center justify-center text-white text-2xl mx-auto mb-4 group-hover:scale-110 transition"><i class="bi {{ $m[1] }}"></i></div>
                <h3 class="font-bold text-gray-900">{{ $m[0] }}</h3>
            </div>
            @endforeach
        </div>
    </div>
</section>

<!-- Testimonials -->
<section id="testimonials" class="py-20 bg-white">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-12">
            <span class="inline-block px-3 py-1 bg-amber-100 text-amber-700 rounded-full text-xs font-bold tracking-widest uppercase mb-3">Testimonials</span>
            <h2 class="text-3xl sm:text-4xl font-extrabold text-gray-900">Trusted by educators worldwide</h2>
        </div>
        <div class="grid md:grid-cols-3 gap-8">
            @php
                $testimonials = $homePage->sections_json['testimonials'] ?? [
                    ['Rahman Khan', 'Principal, Mawa Academy', 'AccumenAI transformed our college operations. From admissions to final results, everything is automated and audit-ready.', '0D6EFD', '5'],
                    ['Fatima Begum', 'Director, Dhaka International School', 'The student portal and parent communication features are outstanding. Parents love the real-time updates.', '6F42C1', '5'],
                    ['Imran Hossain', 'Director, SkillUp Institute', 'Certificates with QR verification and training batches — perfect for our training center.', '198754', '4.5'],
                ];
            @endphp
            @foreach($testimonials as $t)
            <div class="bg-gray-50 rounded-2xl p-8 border border-gray-100">
                <div class="flex gap-1 text-amber-400 mb-4">
                    @for($i = 1; $i <= 5; $i++)
                        @if($i <= floor($t[3])) <i class="bi bi-star-fill"></i>
                        @elseif($t[3] - floor($t[3]) >= 0.5 && $i == ceil($t[3])) <i class="bi bi-star-half"></i>
                        @endif
                    @endfor
                </div>
                <p class="text-gray-700 leading-relaxed">"{{ $t[2] }}"</p>
                <div class="flex items-center gap-3 mt-6">
                    <img src="https://ui-avatars.com/api/?name={{ urlencode($t[0]) }}&background={{ $t[3] }}&color=fff" class="w-10 h-10 rounded-full" alt="">
                    <div><div class="font-semibold text-gray-900 text-sm">{{ $t[0] }}</div><div class="text-xs text-gray-500">{{ $t[1] }}</div></div>
                </div>
            </div>
            @endforeach
        </div>
    </div>
</section>

<!-- CTA -->
<section id="cta" class="py-16 bg-gradient-to-r from-blue-600 to-indigo-600">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <h2 class="text-3xl sm:text-4xl font-extrabold text-white">Ready to modernize your institution?</h2>
        <p class="mt-4 text-blue-100 text-lg">Join 500+ schools and colleges already using AccumenAI.</p>
        <div class="mt-8 flex flex-col sm:flex-row gap-4 justify-center">
            @if (Route::has('owner.register'))
                <a href="{{ $homePage->hero_cta_url ?? route('owner.register') }}" class="inline-flex items-center justify-center gap-2 px-8 py-4 bg-white text-blue-600 font-bold rounded-full hover:bg-blue-50 transition">{{ $homePage->hero_cta_text }} <i class="bi bi-arrow-right"></i></a>
            @endif
            <a href="{{ Route::has('login') ? route('login') : '#' }}" class="inline-flex items-center justify-center gap-2 px-8 py-4 bg-blue-700 text-white font-semibold rounded-full border border-blue-500 hover:bg-blue-800 transition">Sign In</a>
        </div>
    </div>
</section>

<!-- Footer -->
<footer class="bg-gray-900 text-gray-300 py-12">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="border-t border-gray-800 pt-8 flex flex-col md:flex-row justify-between items-center gap-4">
            <p class="text-sm text-gray-400">&copy; {{ date('Y') }} {{ $platformBrandName ?? 'AccumenAI' }}. All rights reserved.</p>
            <p class="text-sm text-gray-400">Crafted with <i class="bi bi-heart-fill text-red-500"></i> for educators</p>
        </div>
    </div>
</footer>
@endsection

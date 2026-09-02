@extends('layouts.app')

@section('title', 'AccumenAI — All-in-One Business & Education Platform')

@section('content')
<!-- Navigation -->
<nav class="sticky top-0 z-50 bg-white/80 backdrop-blur-md border-b border-gray-100">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between items-center h-16">
            <div class="flex items-center gap-2">
                <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-blue-600 to-violet-600 flex items-center justify-center text-white font-bold text-lg">A</div>
                <span class="text-xl font-bold text-gray-900">Accumen<span class="text-blue-600">AI</span></span>
            </div>
            <div class="hidden md:flex items-center gap-8">
                <a href="#features" class="text-sm font-medium text-gray-600 hover:text-blue-600 transition">Features</a>
                <a href="#industries" class="text-sm font-medium text-gray-600 hover:text-blue-600 transition">Solutions</a>
                <a href="#pricing" class="text-sm font-medium text-gray-600 hover:text-blue-600 transition">Pricing</a>
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
                        <a href="{{ route('owner.register') }}" class="inline-flex items-center gap-2 px-5 py-2.5 bg-blue-600 text-white text-sm font-semibold rounded-full hover:bg-blue-700 shadow-lg shadow-blue-600/20 transition">
                            Get Started <i class="bi bi-arrow-right"></i>
                        </a>
                    @endif
                @endauth
            </div>
        </div>
    </div>
</nav>

<!-- Hero Section -->
<section class="relative overflow-hidden bg-gradient-to-br from-blue-50 via-white to-violet-50">
    <div class="absolute inset-0 bg-grid-slate-100 [mask-image:linear-gradient(0deg,white,rgba(255,255,255,0.6))]"></div>
    <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20 lg:py-28">
        <div class="grid lg:grid-cols-2 gap-12 items-center">
            <div>
                <div class="inline-flex items-center gap-2 px-3 py-1.5 bg-blue-100 text-blue-700 rounded-full text-xs font-semibold mb-6">
                    <span class="w-2 h-2 bg-blue-600 rounded-full animate-pulse"></span>
                    Trusted by 500+ Institutes Worldwide
                </div>
                <h1 class="text-4xl sm:text-5xl lg:text-6xl font-extrabold text-gray-900 leading-tight">
                    The Future of <span class="bg-gradient-to-r from-blue-600 to-violet-600 bg-clip-text text-transparent">Business & Education</span> Management
                </h1>
                <p class="mt-6 text-lg text-gray-600 leading-relaxed">
                    AccumenAI unifies CRM, HR, Finance, Inventory, Academics and AI assistance into one powerful, tenant-isolated platform. Built for every industry, from schools to hospitals.
                </p>
                <div class="mt-8 flex flex-col sm:flex-row gap-4">
                    @if (Route::has('owner.register'))
                        <a href="{{ route('owner.register') }}" class="inline-flex items-center justify-center gap-2 px-8 py-4 bg-blue-600 text-white font-semibold rounded-full hover:bg-blue-700 shadow-xl shadow-blue-600/20 transition">
                            Get Started Free <i class="bi bi-arrow-right"></i>
                        </a>
                    @endif
                    <a href="#features" class="inline-flex items-center justify-center gap-2 px-8 py-4 bg-white text-gray-900 font-semibold rounded-full border border-gray-200 hover:border-gray-300 hover:bg-gray-50 transition">
                        <i class="bi bi-play-circle"></i> Explore Features
                    </a>
                </div>
                <div class="mt-8 flex items-center gap-6 text-sm text-gray-500">
                    <span class="flex items-center gap-2"><i class="bi bi-check-circle-fill text-green-500"></i> No credit card required</span>
                    <span class="flex items-center gap-2"><i class="bi bi-check-circle-fill text-green-500"></i> 14-day free trial</span>
                </div>
            </div>
            <div class="relative">
                <div class="absolute -inset-4 bg-gradient-to-r from-blue-600/20 to-violet-600/20 rounded-3xl blur-2xl"></div>
                <div class="relative bg-white rounded-2xl shadow-2xl border border-gray-100 p-6">
                    <div class="flex items-center gap-3 mb-6">
                        <div class="w-3 h-3 rounded-full bg-red-400"></div>
                        <div class="w-3 h-3 rounded-full bg-yellow-400"></div>
                        <div class="w-3 h-3 rounded-full bg-green-400"></div>
                        <span class="ml-auto text-xs text-gray-400">accumen.ai/dashboard</span>
                    </div>
                    <div class="grid grid-cols-3 gap-4 mb-6">
                        <div class="bg-blue-50 rounded-xl p-4 text-center">
                            <div class="w-10 h-10 bg-blue-600 rounded-lg flex items-center justify-center text-white mx-auto mb-2"><i class="bi bi-people-fill"></i></div>
                            <div class="text-2xl font-bold text-gray-900">12.5K</div>
                            <div class="text-xs text-gray-500">Students</div>
                        </div>
                        <div class="bg-violet-50 rounded-xl p-4 text-center">
                            <div class="w-10 h-10 bg-violet-600 rounded-lg flex items-center justify-center text-white mx-auto mb-2"><i class="bi bi-graph-up-arrow"></i></div>
                            <div class="text-2xl font-bold text-gray-900">98%</div>
                            <div class="text-xs text-gray-500">Satisfaction</div>
                        </div>
                        <div class="bg-green-50 rounded-xl p-4 text-center">
                            <div class="w-10 h-10 bg-green-600 rounded-lg flex items-center justify-center text-white mx-auto mb-2"><i class="bi bi-cash-coin"></i></div>
                            <div class="text-2xl font-bold text-gray-900">$2.4M</div>
                            <div class="text-xs text-gray-500">Revenue</div>
                        </div>
                    </div>
                    <div class="space-y-3">
                        <div class="flex items-center justify-between p-3 bg-gray-50 rounded-xl">
                            <span class="flex items-center gap-3 text-sm font-medium"><span class="w-8 h-8 bg-blue-100 rounded-lg flex items-center justify-center text-blue-600"><i class="bi bi-mortarboard-fill"></i></span> Academic Results Published</span>
                            <span class="text-xs bg-green-100 text-green-700 px-2 py-1 rounded-full">Done</span>
                        </div>
                        <div class="flex items-center justify-between p-3 bg-gray-50 rounded-xl">
                            <span class="flex items-center gap-3 text-sm font-medium"><span class="w-8 h-8 bg-violet-100 rounded-lg flex items-center justify-center text-violet-600"><i class="bi bi-receipt"></i></span> Fee Collection Completed</span>
                            <span class="text-xs bg-blue-100 text-blue-700 px-2 py-1 rounded-full">New</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Features Section -->
<section id="features" class="py-20 bg-white">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center max-w-3xl mx-auto mb-16">
            <span class="inline-block px-3 py-1 bg-blue-100 text-blue-700 rounded-full text-xs font-bold tracking-widest uppercase mb-3">Features</span>
            <h2 class="text-3xl sm:text-4xl font-extrabold text-gray-900">Everything you need to run your institution</h2>
            <p class="mt-4 text-gray-600">Powerful modules that work together seamlessly — or use only what you need.</p>
        </div>
        <div class="grid md:grid-cols-3 gap-8">
            <div class="group bg-white rounded-2xl border border-gray-100 p-8 hover:shadow-xl hover:border-blue-100 transition">
                <div class="w-12 h-12 bg-blue-600 rounded-xl flex items-center justify-center text-white text-xl mb-6 group-hover:scale-110 transition"><i class="bi bi-layers-fill"></i></div>
                <h3 class="text-xl font-bold text-gray-900 mb-3">Unified Platform</h3>
                <p class="text-gray-600 text-sm leading-relaxed">CRM, HR & Payroll, Finance & Accounting, Inventory, Sales, Purchase, Academics and more — all tenant-isolated and permission-controlled.</p>
                <a href="{{ Route::has('owner.register') ? route('owner.register') : '#' }}" class="inline-flex items-center gap-2 mt-6 text-sm font-semibold text-blue-600 hover:gap-3 transition">Learn more <i class="bi bi-arrow-right"></i></a>
            </div>
            <div class="group bg-white rounded-2xl border border-gray-100 p-8 hover:shadow-xl hover:border-violet-100 transition">
                <div class="w-12 h-12 bg-violet-600 rounded-xl flex items-center justify-center text-white text-xl mb-6 group-hover:scale-110 transition"><i class="bi bi-robot"></i></div>
                <h3 class="text-xl font-bold text-gray-900 mb-3">AI-Powered Insights</h3>
                <p class="text-gray-600 text-sm leading-relaxed">Built-in AI assistant for analytics, automated reports, attendance predictions and smart recommendations tailored to your domain.</p>
                <a href="#industries" class="inline-flex items-center gap-2 mt-6 text-sm font-semibold text-violet-600 hover:gap-3 transition">Explore AI <i class="bi bi-arrow-right"></i></a>
            </div>
            <div class="group bg-white rounded-2xl border border-gray-100 p-8 hover:shadow-xl hover:border-green-100 transition">
                <div class="w-12 h-12 bg-green-600 rounded-xl flex items-center justify-center text-white text-xl mb-6 group-hover:scale-110 transition"><i class="bi bi-shield-lock-fill"></i></div>
                <h3 class="text-xl font-bold text-gray-900 mb-3">Enterprise Security</h3>
                <p class="text-gray-600 text-sm leading-relaxed">Role-based access, branch scoping, audit logs, 2FA, encrypted storage and daily backups — your data is always safe.</p>
                <a href="{{ Route::has('login') ? route('login') : '#' }}" class="inline-flex items-center gap-2 mt-6 text-sm font-semibold text-green-600 hover:gap-3 transition">Security details <i class="bi bi-arrow-right"></i></a>
            </div>
        </div>
    </div>
</section>

<!-- Industry Modules -->
<section id="industries" class="py-20 bg-gray-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center max-w-3xl mx-auto mb-12">
            <span class="inline-block px-3 py-1 bg-violet-100 text-violet-700 rounded-full text-xs font-bold tracking-widest uppercase mb-3">Solutions</span>
            <h2 class="text-3xl sm:text-4xl font-extrabold text-gray-900">Built for every industry</h2>
            <p class="mt-4 text-gray-600">One platform, eight tailored solutions — switch domains without losing data.</p>
        </div>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
            @php
                $industries = [
                    ['Education', 'bi-mortarboard-fill', 'from-blue-500 to-blue-600', 'Schools, Colleges & Universities'],
                    ['Healthcare', 'bi-heart-pulse-fill', 'from-red-500 to-pink-600', 'Hospitals & Clinics'],
                    ['Retail', 'bi-shop', 'from-amber-500 to-orange-600', 'Shops & Showrooms'],
                    ['Manufacturing', 'bi-gear-fill', 'from-slate-600 to-slate-700', 'Factories & Plants'],
                    ['Service', 'bi-tools', 'from-emerald-500 to-teal-600', 'Agencies & Services'],
                    ['Transport', 'bi-truck', 'from-indigo-500 to-violet-600', 'Fleet & Logistics'],
                    ['Restaurant', 'bi-cup-hot-fill', 'from-orange-500 to-red-600', 'Restaurants & Cafés'],
                    ['Finance', 'bi-bank', 'from-green-600 to-emerald-700', 'Finance & Accounting'],
                ];
            @endphp
            @foreach ($industries as $ind)
                <div class="group bg-white rounded-2xl border border-gray-100 p-6 text-center hover:shadow-lg hover:border-gray-200 transition">
                    <div class="w-14 h-14 rounded-xl bg-gradient-to-br {{ $ind[2] }} flex items-center justify-center text-white text-2xl mx-auto mb-4 group-hover:scale-110 transition"><i class="bi {{ $ind[1] }}"></i></div>
                    <h3 class="font-bold text-gray-900">{{ $ind[0] }}</h3>
                    <p class="text-xs text-gray-500 mt-1">{{ $ind[3] }}</p>
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
            <h2 class="text-3xl sm:text-4xl font-extrabold text-gray-900">Loved by educators and entrepreneurs</h2>
        </div>
        <div class="grid md:grid-cols-3 gap-8">
            <div class="bg-gray-50 rounded-2xl p-8 border border-gray-100">
                <div class="flex gap-1 text-amber-400 mb-4"><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i></div>
                <p class="text-gray-700 leading-relaxed">“AccumenAI transformed our college operations. From admissions to final results, everything is automated and audit-ready.”</p>
                <div class="flex items-center gap-3 mt-6">
                    <img src="https://ui-avatars.com/api/?name=Rahman+Khan&background=0D6EFD&color=fff" class="w-10 h-10 rounded-full" alt="">
                    <div><div class="font-semibold text-gray-900 text-sm">Rahman Khan</div><div class="text-xs text-gray-500">Principal, Mawa Academy</div></div>
                </div>
            </div>
            <div class="bg-gray-50 rounded-2xl p-8 border border-gray-100">
                <div class="flex gap-1 text-amber-400 mb-4"><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i></div>
                <p class="text-gray-700 leading-relaxed">“We manage 3 branches, inventory and payroll in one place. The finance reports alone saved us 20 hours a month.”</p>
                <div class="flex items-center gap-3 mt-6">
                    <img src="https://ui-avatars.com/api/?name=Ayesha+Karim&background=6F42C1&color=fff" class="w-10 h-10 rounded-full" alt="">
                    <div><div class="font-semibold text-gray-900 text-sm">Ayesha Karim</div><div class="text-xs text-gray-500">Owner, Retail Hub</div></div>
                </div>
            </div>
            <div class="bg-gray-50 rounded-2xl p-8 border border-gray-100">
                <div class="flex gap-1 text-amber-400 mb-4"><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-half"></i></div>
                <p class="text-gray-700 leading-relaxed">“Certificates with QR verification and training batches — perfect for our training center. Super easy to use!”</p>
                <div class="flex items-center gap-3 mt-6">
                    <img src="https://ui-avatars.com/api/?name=Imran+Hossain&background=198754&color=fff" class="w-10 h-10 rounded-full" alt="">
                    <div><div class="font-semibold text-gray-900 text-sm">Imran Hossain</div><div class="text-xs text-gray-500">Director, SkillUp Institute</div></div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Pricing -->
<section id="pricing" class="py-20 bg-gray-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center max-w-3xl mx-auto mb-12">
            <span class="inline-block px-3 py-1 bg-green-100 text-green-700 rounded-full text-xs font-bold tracking-widest uppercase mb-3">Pricing</span>
            <h2 class="text-3xl sm:text-4xl font-extrabold text-gray-900">Simple, transparent pricing</h2>
            <p class="mt-4 text-gray-600">Start free, scale as you grow. All plans include tenant isolation and support.</p>
        </div>
        <div class="grid md:grid-cols-3 gap-8 max-w-5xl mx-auto">
            <div class="bg-white rounded-2xl border border-gray-200 p-8">
                <h3 class="font-bold text-gray-900">Starter</h3>
                <p class="text-sm text-gray-500 mt-1">For small institutes</p>
                <div class="mt-6 flex items-baseline gap-1"><span class="text-4xl font-extrabold text-gray-900">$29</span><span class="text-gray-500">/month</span></div>
                <ul class="mt-8 space-y-3 text-sm">
                    <li class="flex items-center gap-2"><i class="bi bi-check-circle-fill text-green-500"></i> Up to 500 students</li>
                    <li class="flex items-center gap-2"><i class="bi bi-check-circle-fill text-green-500"></i> 3 branches</li>
                    <li class="flex items-center gap-2"><i class="bi bi-check-circle-fill text-green-500"></i> Basic reports</li>
                    <li class="flex items-center gap-2 text-gray-400"><i class="bi bi-x-circle"></i> AI assistant</li>
                </ul>
                <a href="{{ Route::has('owner.register') ? route('owner.register') : '#' }}" class="mt-8 block text-center w-full py-3 border border-gray-300 rounded-full font-semibold hover:bg-gray-50 transition">Get Started</a>
            </div>
            <div class="bg-blue-600 rounded-2xl p-8 text-white relative shadow-xl shadow-blue-600/20 scale-105">
                <span class="absolute -top-3 left-1/2 -translate-x-1/2 bg-amber-400 text-gray-900 text-xs font-bold px-3 py-1 rounded-full">Most Popular</span>
                <h3 class="font-bold">Professional</h3>
                <p class="text-sm text-blue-100 mt-1">For growing businesses</p>
                <div class="mt-6 flex items-baseline gap-1"><span class="text-4xl font-extrabold">$79</span><span class="text-blue-100">/month</span></div>
                <ul class="mt-8 space-y-3 text-sm">
                    <li class="flex items-center gap-2"><i class="bi bi-check-circle-fill text-blue-200"></i> Unlimited students</li>
                    <li class="flex items-center gap-2"><i class="bi bi-check-circle-fill text-blue-200"></i> Unlimited branches</li>
                    <li class="flex items-center gap-2"><i class="bi bi-check-circle-fill text-blue-200"></i> Advanced analytics</li>
                    <li class="flex items-center gap-2"><i class="bi bi-check-circle-fill text-blue-200"></i> AI assistant</li>
                </ul>
                <a href="{{ Route::has('owner.register') ? route('owner.register') : '#' }}" class="mt-8 block text-center w-full py-3 bg-white text-blue-600 rounded-full font-bold hover:bg-blue-50 transition">Get Started</a>
            </div>
            <div class="bg-white rounded-2xl border border-gray-200 p-8">
                <h3 class="font-bold text-gray-900">Enterprise</h3>
                <p class="text-sm text-gray-500 mt-1">For large organizations</p>
                <div class="mt-6 flex items-baseline gap-1"><span class="text-4xl font-extrabold text-gray-900">$199</span><span class="text-gray-500">/month</span></div>
                <ul class="mt-8 space-y-3 text-sm">
                    <li class="flex items-center gap-2"><i class="bi bi-check-circle-fill text-green-500"></i> Everything in Pro</li>
                    <li class="flex items-center gap-2"><i class="bi bi-check-circle-fill text-green-500"></i> Dedicated support</li>
                    <li class="flex items-center gap-2"><i class="bi bi-check-circle-fill text-green-500"></i> Custom integrations</li>
                    <li class="flex items-center gap-2"><i class="bi bi-check-circle-fill text-green-500"></i> SLA guarantee</li>
                </ul>
                <a href="#cta" class="mt-8 block text-center w-full py-3 border border-gray-300 rounded-full font-semibold hover:bg-gray-50 transition">Contact Sales</a>
            </div>
        </div>
    </div>
</section>

<!-- CTA -->
<section id="cta" class="py-16 bg-gradient-to-r from-blue-600 to-violet-600">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <h2 class="text-3xl sm:text-4xl font-extrabold text-white">Ready to transform your institution?</h2>
        <p class="mt-4 text-blue-100 text-lg">Join 500+ institutes already growing with AccumenAI.</p>
        <div class="mt-8 flex flex-col sm:flex-row gap-4 justify-center">
            @if (Route::has('owner.register'))
                <a href="{{ route('owner.register') }}" class="inline-flex items-center justify-center gap-2 px-8 py-4 bg-white text-blue-600 font-bold rounded-full hover:bg-blue-50 transition">Start Free Trial <i class="bi bi-arrow-right"></i></a>
            @endif
            <a href="{{ Route::has('login') ? route('login') : '#' }}" class="inline-flex items-center justify-center gap-2 px-8 py-4 bg-blue-700 text-white font-semibold rounded-full border border-blue-500 hover:bg-blue-800 transition">Sign In</a>
        </div>
    </div>
</section>

<!-- Footer -->
<footer class="bg-gray-900 text-gray-300 py-12">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid md:grid-cols-4 gap-8 mb-8">
            <div>
                <div class="flex items-center gap-2 mb-4"><div class="w-8 h-8 rounded-lg bg-blue-600 flex items-center justify-center text-white font-bold">A</div><span class="text-white font-bold text-lg">AccumenAI</span></div>
                <p class="text-sm text-gray-400 leading-relaxed">All-in-one platform for education and business — CRM, HR, Finance, Academics and AI.</p>
                <div class="flex gap-3 mt-4">
                    <a href="#" class="w-8 h-8 bg-gray-800 rounded-lg flex items-center justify-center hover:bg-gray-700 transition"><i class="bi bi-facebook"></i></a>
                    <a href="#" class="w-8 h-8 bg-gray-800 rounded-lg flex items-center justify-center hover:bg-gray-700 transition"><i class="bi bi-twitter-x"></i></a>
                    <a href="#" class="w-8 h-8 bg-gray-800 rounded-lg flex items-center justify-center hover:bg-gray-700 transition"><i class="bi bi-linkedin"></i></a>
                    <a href="#" class="w-8 h-8 bg-gray-800 rounded-lg flex items-center justify-center hover:bg-gray-700 transition"><i class="bi bi-youtube"></i></a>
                </div>
            </div>
            <div>
                <h4 class="text-white font-semibold mb-4">Product</h4>
                <ul class="space-y-2 text-sm">
                    <li><a href="#features" class="hover:text-white transition">Features</a></li>
                    <li><a href="#industries" class="hover:text-white transition">Solutions</a></li>
                    <li><a href="#pricing" class="hover:text-white transition">Pricing</a></li>
                    <li><a href="{{ Route::has('verify.certificate.index') ? route('verify.certificate.index') : '#' }}" class="hover:text-white transition">Verify Certificate</a></li>
                </ul>
            </div>
            <div>
                <h4 class="text-white font-semibold mb-4">Company</h4>
                <ul class="space-y-2 text-sm">
                    <li><a href="#" class="hover:text-white transition">About Us</a></li>
                    <li><a href="#" class="hover:text-white transition">Contact</a></li>
                    <li><a href="#" class="hover:text-white transition">Privacy Policy</a></li>
                    <li><a href="#" class="hover:text-white transition">Terms of Service</a></li>
                </ul>
            </div>
            <div>
                <h4 class="text-white font-semibold mb-4">Get in touch</h4>
                <ul class="space-y-2 text-sm">
                    <li class="flex items-center gap-2"><i class="bi bi-envelope"></i> support@accumen.ai</li>
                    <li class="flex items-center gap-2"><i class="bi bi-telephone"></i> +880 1XXX-XXXXXX</li>
                    <li class="flex items-center gap-2"><i class="bi bi-geo-alt"></i> Dhaka, Bangladesh</li>
                </ul>
            </div>
        </div>
        <div class="border-t border-gray-800 pt-8 flex flex-col md:flex-row justify-between items-center gap-4">
            <p class="text-sm text-gray-400">© {{ date('Y') }} AccumenAI. All rights reserved.</p>
            <p class="text-sm text-gray-400">Crafted with <i class="bi bi-heart-fill text-red-500"></i> for educators & entrepreneurs</p>
        </div>
    </div>
</footer>
@endsection

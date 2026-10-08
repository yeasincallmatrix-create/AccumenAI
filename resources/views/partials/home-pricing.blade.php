@php
    $homePricing = $homePricing ?? app(\App\Services\Pricing\HomePricingService::class)->data();
@endphp
@if ($homePricing['enabled'])
<!-- Pricing -->
<section id="pricing" class="py-20 bg-gray-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center max-w-3xl mx-auto mb-8">
            <span class="inline-block px-3 py-1 bg-green-100 text-green-700 rounded-full text-xs font-bold tracking-widest uppercase mb-3">Pricing</span>
            <h2 class="text-3xl sm:text-4xl font-extrabold text-gray-900">Simple, transparent pricing</h2>
            <p class="mt-4 text-gray-600">Plans that scale as you grow — all prices shown in {{ $homePricing['currency'] }} for {{ $homePricing['market_label'] }}.</p>
        </div>

        <div class="grid sm:grid-cols-2 md:grid-cols-3 gap-8 max-w-6xl mx-auto items-start">
            @foreach ($homePricing['cards'] as $card)
                @php
                    $popular = (int) $card['tier'] === 3;
                    $pkg = $card['package'];
                @endphp
                <div class="bg-white rounded-2xl border {{ $popular ? 'border-blue-600 shadow-xl shadow-blue-600/15 md:-translate-y-2' : 'border-gray-200' }} p-8 relative">
                    @if ($popular)
                        <span class="absolute -top-3 left-1/2 -translate-x-1/2 bg-amber-400 text-gray-900 text-xs font-bold px-3 py-1 rounded-full">Most Popular</span>
                    @endif
                    <h3 class="font-bold text-gray-900">{{ $pkg->name }}</h3>

                    @if ($card['discount_active'] && (float) $card['discount_percent'] > 0)
                        <div class="mt-4 flex items-center gap-2">
                            <span class="text-sm text-gray-400 line-through">{{ $card['base_monthly_label'] }}</span>
                            <span class="text-xs font-bold text-green-700 bg-green-100 rounded-full px-2 py-0.5">-{{ (float) $card['discount_percent'] }}%</span>
                        </div>
                    @endif

                    <div class="mt-4 flex items-baseline gap-1">
                        <span class="text-4xl font-extrabold text-gray-900">{{ $card['monthly_label'] }}</span>
                        <span class="text-gray-500">/month</span>
                    </div>
                    <p class="text-sm text-gray-500 mt-1">{{ $card['yearly_label'] }} /year</p>

                    <ul class="mt-6 space-y-3 text-sm text-gray-600">
                        <li class="flex items-center gap-2"><i class="bi bi-puzzle-fill text-blue-600"></i> {{ $card['module_count'] }} modules</li>
                        <li class="flex items-center gap-2"><i class="bi bi-stars text-blue-600"></i> {{ $card['feature_count'] }} features</li>
                        @if ($card['discount_active'] && ! empty($card['discount_ends_at']))
                            <li class="flex items-center gap-2"><i class="bi bi-clock-fill text-blue-600"></i> Offer ends {{ $card['discount_ends_at'] }}</li>
                        @endif
                    </ul>

                    @if (! empty($card['trial_days']) && (int) $card['trial_days'] > 0)
                        <div class="mt-4"><span class="inline-flex items-center gap-1 text-xs font-semibold text-blue-700 bg-blue-100 rounded-full px-3 py-1"><i class="bi bi-gift"></i> {{ (int) $card['trial_days'] }}-day free trial</span></div>
                    @endif

                    <a href="{{ Route::has('owner.register') ? route('owner.register') : '#' }}" class="mt-6 block text-center w-full py-3 rounded-full transition {{ $popular ? 'bg-blue-600 text-white font-bold hover:bg-blue-700' : 'border border-gray-300 font-semibold hover:bg-gray-50' }}">
                        Get Started
                    </a>
                </div>
            @endforeach
        </div>
    </div>
</section>
@endif

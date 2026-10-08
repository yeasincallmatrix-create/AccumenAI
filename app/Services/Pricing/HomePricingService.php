<?php

namespace App\Services\Pricing;

use App\Models\Industry;
use App\Services\Geo\VisitorCountryResolver;

/**
 * Country-aware pricing block for the public landing page.
 *
 * The market follows the visitor's geolocation — there is no manual
 * BD/Global switch:
 *   detected country = BD → Bangladesh prices in BDT (৳)
 *   anything else        → Global prices in USD ($)
 *
 * Cards come from the same IndustryPricingCardsService the admin
 * "Package pricing card" page uses, minus the FREE card.
 */
class HomePricingService
{
    /** Enabled markets (ISO label => package_country_prices.country_code). */
    public const MARKETS = [
        'BD' => ['country' => 'BD', 'currency' => 'BDT', 'label' => 'Bangladesh'],
        'GLOBAL' => ['country' => 'US', 'currency' => 'USD', 'label' => 'Global'],
    ];

    /** Fallback industry when no ?industry= is given (admin showcase default). */
    private const DEFAULT_INDUSTRY = 'healthcare';

    public function __construct(
        private readonly IndustryPricingCardsService $cards,
        private readonly VisitorCountryResolver $geo,
    ) {}

    /**
     * Active market, chosen from the visitor's detected country.
     */
    public function market(): string
    {
        return $this->geo->resolve() === 'BD' ? 'BD' : 'GLOBAL';
    }

    /**
     * Data for the landing-page pricing partial.
     *
     * @return array{enabled: bool, market: string, market_label: string, industry: ?string, country: string, currency: string, symbol: string, cards: array<int, array<string, mixed>>}
     */
    public function data(): array
    {
        $market = $this->market();
        $meta = self::MARKETS[$market];
        $industry = $this->industry();
        $cards = $industry === null ? [] : $this->cards->cards($industry, $meta['country'], false);

        $symbol = $meta['currency'] === 'BDT' ? '৳' : '$';
        foreach ($cards as $i => $card) {
            $cards[$i]['monthly_label'] = $this->label($card['monthly'], $symbol);
            $cards[$i]['yearly_label'] = $this->label($card['yearly'], $symbol);
            $cards[$i]['base_monthly_label'] = $this->label($card['base_monthly'], $symbol);
        }

        return [
            'enabled' => $cards !== [],
            'market' => $market,
            'market_label' => $meta['label'],
            'industry' => $industry,
            'country' => $meta['country'],
            'currency' => $meta['currency'],
            'symbol' => $symbol,
            'cards' => $cards,
        ];
    }

    /**
     * Industry: validated ?industry= query → configured default → first active.
     */
    private function industry(): ?string
    {
        $slug = (string) (request()->query('industry') ?? config('home_pricing.industry') ?? self::DEFAULT_INDUSTRY);

        $active = Industry::where('status', 'active')->pluck('slug');
        if ($active->contains($slug)) {
            return $slug;
        }

        return $active->first();
    }

    private function label(float $amount, string $symbol): string
    {
        $decimals = $amount == (int) $amount ? 0 : 2;

        return $symbol.number_format($amount, $decimals);
    }
}

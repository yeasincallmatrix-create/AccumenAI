<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class HomePage extends Model
{
    protected $table = 'home_pages';

    protected $fillable = [
        'name',
        'slug',
        'description',
        'hero_title',
        'hero_subtitle',
        'hero_badge',
        'hero_cta_text',
        'hero_cta_url',
        'hero_image_url',
        'sections_json',
        'is_active',
        'is_global',
    ];

    protected $casts = [
        'sections_json' => 'array',
        'is_active'     => 'boolean',
        'is_global'     => 'boolean',
    ];

    // ── Relationships ──

    public function countries(): BelongsToMany
    {
        return $this->belongsToMany(Country::class, 'home_page_countries')
            ->withTimestamps();
    }

    // ── Scopes ──

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // ── Helpers ──

    /**
     * Resolve which home page to show for a given country ISO-2 code.
     * Priority: 1) Country-assigned active page  2) Global active page  3) First active page  4) null
     */
    public static function resolveForCountry(?string $countryIso2): ?self
    {
        if ($countryIso2) {
            $page = static::active()
                ->whereHas('countries', fn ($q) => $q->where('iso2', $countryIso2))
                ->with('countries')
                ->first();

            if ($page) {
                return $page;
            }
        }

        $global = static::active()->where('is_global', true)->with('countries')->first();
        if ($global) {
            return $global;
        }

        return static::active()->with('countries')->first();
    }

    /**
     * Available pre-built template slugs → display names.
     */
    public static function availableTemplates(): array
    {
        return [
            'default'    => 'Default — General Business & Education',
            'education'  => 'Education — Schools, Colleges & Universities',
            'healthcare' => 'Healthcare — Hospitals, Clinics & Medical',
            'business'   => 'Business — Corporate & Enterprise',
            'minimal'    => 'Minimal — Clean & Simple',
        ];
    }

    /**
     * Return the Blade view path for this page's slug.
     */
    public function viewPath(): string
    {
        return "home-pages.{$this->slug}";
    }
}

<?php

namespace Tests\Unit;

use App\Models\Country;
use App\Models\CountryCurrencyMap;
use App\Models\Institute;
use App\Support\CountryConfigResolver;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Phase 9b-1: country config infrastructure (foundation only).
 *
 * Chain under test: country FK → CountryCurrencyMap →
 * config('locale.*') → provided default. Zero behavior change
 * elsewhere — no callers of the resolver exist yet.
 */
class CountryConfigResolverTest extends TestCase
{
    use DatabaseTransactions;

    private CountryConfigResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->resolver = app(CountryConfigResolver::class);
    }

    private function bd(): Country
    {
        $country = Country::where('iso2', 'BD')->first() ?? Country::orderBy('id')->first();

        $this->assertNotNull($country, 'countries table must be seeded');

        return $country;
    }

    private function institute(?int $countryId): Institute
    {
        return Institute::create([
            'name' => 'Locale Res '.uniqid(),
            'slug' => 'locale-res-'.uniqid(),
            'status' => 'active',
            'industry' => 'retail',
            'country' => 'Bangladesh',
            'country_id' => $countryId,
        ]);
    }

    private function ensureBdMapRow(): void
    {
        if (! CountryCurrencyMap::where('country_code', 'BD')->exists()) {
            CountryCurrencyMap::create([
                'country_code' => 'BD',
                'country_name' => 'Bangladesh',
                'currency_code' => 'BDT',
            ]);
        }
    }

    private function maplessCountryId(): int
    {
        $mapped = CountryCurrencyMap::query()->pluck('country_code')->all();

        $row = DB::table('countries')
            ->when(! empty($mapped), fn ($q) => $q->whereNotIn('iso2', $mapped))
            ->orderBy('id')
            ->first();

        if ($row) {
            return (int) $row->id;
        }

        return (int) DB::table('countries')->insertGetId([
            'name' => 'Testland',
            'iso2' => 'ZZ',
            'iso3' => 'ZZZ',
            'phone_code' => '999',
            'status' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_resolves_phone_code_from_country_table(): void
    {
        $inst = $this->institute($this->bd()->id);

        $this->assertSame('880', $this->resolver->resolve($inst, 'phone.default_country_code'));
    }

    public function test_resolves_phone_code_fallback_when_no_country(): void
    {
        $inst = $this->institute(null);

        $this->assertSame('880', $this->resolver->resolve($inst, 'phone.default_country_code'));
    }

    public function test_resolves_currency_from_country_currency_map(): void
    {
        $this->ensureBdMapRow();
        $inst = $this->institute($this->bd()->id);

        $this->assertSame('BDT', $this->resolver->resolve($inst, 'currency.default_code'));
    }

    public function test_resolves_currency_fallback_when_map_missing(): void
    {
        $inst = $this->institute($this->maplessCountryId());

        $this->assertSame('BDT', $this->resolver->resolve($inst, 'currency.default_code'));
    }

    public function test_resolves_date_format_from_config(): void
    {
        $inst = $this->institute(null);

        $this->assertSame('dmy', $this->resolver->resolve($inst, 'date.default_format_key'));
    }

    public function test_resolves_timezone_from_config(): void
    {
        $inst = $this->institute(null);

        $this->assertSame('Asia/Dhaka', $this->resolver->resolve($inst, 'date.timezone'));
    }

    public function test_is_country_returns_true_for_matching_iso2(): void
    {
        $inst = $this->institute($this->bd()->id);

        $this->assertTrue($this->resolver->isCountry($inst, 'BD'));
        $this->assertFalse($this->resolver->isCountry($inst, 'IN'));
    }

    public function test_is_country_returns_false_for_null_country(): void
    {
        $inst = $this->institute(null);

        $this->assertFalse($this->resolver->isCountry($inst, 'BD'));
    }

    public function test_config_env_defaults_loaded(): void
    {
        $this->assertSame('880', config('locale.phone.default_country_code'));
        $this->assertSame('BDT', config('locale.currency.default_code'));
    }

    public function test_resolver_is_stateless(): void
    {
        $inst = $this->institute($this->bd()->id);

        $first = $this->resolver->resolve($inst, 'currency.default_code');
        $second = $this->resolver->resolve($inst, 'currency.default_code');

        $this->assertSame($first, $second);
        $this->assertSame($first, (new CountryConfigResolver)->resolve($inst, 'currency.default_code'));
    }
}

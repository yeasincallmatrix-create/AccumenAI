<?php

namespace Database\Seeders;

use App\Models\Currency;
use Illuminate\Database\Seeder;

/**
 * Seed the global currency catalog.
 *
 * The currencies table ships empty in the SQL dump, causing hundreds of
 * cascading failures in accounting / tax / finance tests.
 *
 * Idempotent via firstOrCreate by code — safe to re-run.
 *
 * Run explicitly:
 *
 *   php artisan db:seed --class=CurrencySeeder
 */
class CurrencySeeder extends Seeder
{
    public static function currencies(): array
    {
        return [
            ['code' => 'BDT', 'name' => 'Bangladeshi Taka',        'symbol' => "\u{09F3}",   'decimal_places' => 2, 'is_base' => true,  'is_active' => true],
            ['code' => 'USD', 'name' => 'US Dollar',               'symbol' => '$',         'decimal_places' => 2, 'is_base' => false, 'is_active' => true],
            ['code' => 'MYR', 'name' => 'Malaysian Ringgit',       'symbol' => 'RM',        'decimal_places' => 2, 'is_base' => false, 'is_active' => true],
            ['code' => 'INR', 'name' => 'Indian Rupee',            'symbol' => "\u{20B9}",  'decimal_places' => 2, 'is_base' => false, 'is_active' => true],
            ['code' => 'EUR', 'name' => 'Euro',                    'symbol' => "\u{20AC}",  'decimal_places' => 2, 'is_base' => false, 'is_active' => true],
        ];
    }

    public function run(): void
    {
        $created = 0;

        foreach (self::currencies() as $attrs) {
            Currency::firstOrCreate(
                ['code' => $attrs['code']],
                $attrs,
            );

            if (! $this->command) {
                continue;
            }

            $created++;
        }

        $this->command?->info("Currencies seeded: {$created} ensured.");
    }
}

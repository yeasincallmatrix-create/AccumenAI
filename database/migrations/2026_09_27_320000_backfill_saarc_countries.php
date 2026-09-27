<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $saarc = [
        ['iso2' => 'BD', 'iso3' => 'BGD', 'name' => 'Bangladesh',  'phone_code' => '880'],
        ['iso2' => 'IN', 'iso3' => 'IND', 'name' => 'India',       'phone_code' => '91'],
        ['iso2' => 'PK', 'iso3' => 'PAK', 'name' => 'Pakistan',    'phone_code' => '92'],
        ['iso2' => 'LK', 'iso3' => 'LKA', 'name' => 'Sri Lanka',   'phone_code' => '94'],
        ['iso2' => 'NP', 'iso3' => 'NPL', 'name' => 'Nepal',       'phone_code' => '977'],
        ['iso2' => 'BT', 'iso3' => 'BTN', 'name' => 'Bhutan',      'phone_code' => '975'],
        ['iso2' => 'MV', 'iso3' => 'MDV', 'name' => 'Maldives',    'phone_code' => '960'],
    ];

    public function up(): void
    {
        $expectedCurrency = [
            'BD' => 'BDT', 'IN' => 'INR', 'PK' => 'PKR', 'LK' => 'LKR',
            'NP' => 'NPR', 'BT' => 'BTN', 'MV' => 'MVR',
        ];

        $inserted = 0;

        foreach ($this->saarc as $country) {
            $iso2 = $country['iso2'];

            $mapped = DB::table('country_currency_map')
                ->where('country_code', $iso2)
                ->value('currency_code');

            if ($mapped !== ($expectedCurrency[$iso2] ?? null)) {
                throw new \RuntimeException(
                    "ABORT: country_currency_map for {$iso2} is '" . ($mapped ?? 'NULL')
                    . "', expected '{$expectedCurrency[$iso2]}'."
                );
            }

            $exists = DB::table('countries')->where('iso2', $iso2)->exists();
            if ($exists) {
                continue;
            }

            DB::table('countries')->insert([
                'iso2' => $country['iso2'],
                'iso3' => $country['iso3'],
                'name' => $country['name'],
                'phone_code' => $country['phone_code'],
                'academic_unit_label' => null,
                'status' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $inserted++;
        }

        echo "BackfillSaarcCountries: {$inserted} inserted into countries.\n";
    }

    public function down(): void
    {
        // Only removes rows this migration backfilled into the main DB.
        // BD/IN/PK/MV pre-existed and are never touched.
        DB::table('countries')
            ->whereIn('iso2', ['LK', 'NP', 'BT'])
            ->delete();
    }
};

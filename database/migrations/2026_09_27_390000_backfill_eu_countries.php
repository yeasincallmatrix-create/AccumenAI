<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Phase 5 — EU cluster countries.
     *
     * up() inserts whatever is missing at runtime:
     *   - prod (accumen_ai): 11 inserted (ES, IT, PT already existed)
     *   - test (monetix_test): 14 inserted (none existed)
     */
    private array $countries = [
        'IT' => ['name' => 'Italy',          'iso3' => 'ITA', 'phone_code' => '39'],
        'ES' => ['name' => 'Spain',          'iso3' => 'ESP', 'phone_code' => '34'],
        'PL' => ['name' => 'Poland',         'iso3' => 'POL', 'phone_code' => '48'],
        'SE' => ['name' => 'Sweden',         'iso3' => 'SWE', 'phone_code' => '46'],
        'BE' => ['name' => 'Belgium',        'iso3' => 'BEL', 'phone_code' => '32'],
        'AT' => ['name' => 'Austria',        'iso3' => 'AUT', 'phone_code' => '43'],
        'DK' => ['name' => 'Denmark',        'iso3' => 'DNK', 'phone_code' => '45'],
        'FI' => ['name' => 'Finland',        'iso3' => 'FIN', 'phone_code' => '358'],
        'IE' => ['name' => 'Ireland',        'iso3' => 'IRL', 'phone_code' => '353'],
        'PT' => ['name' => 'Portugal',       'iso3' => 'PRT', 'phone_code' => '351'],
        'GR' => ['name' => 'Greece',         'iso3' => 'GRC', 'phone_code' => '30'],
        'CZ' => ['name' => 'Czech Republic', 'iso3' => 'CZE', 'phone_code' => '420'],
        'RO' => ['name' => 'Romania',        'iso3' => 'ROU', 'phone_code' => '40'],
        'HU' => ['name' => 'Hungary',        'iso3' => 'HUN', 'phone_code' => '36'],
    ];

    /**
     * Absent from `countries` at audit time (2026-09-27, production).
     * ES, IT, PT pre-date this migration (created_at 2026-09-05) and are
     * never deleted by down().
     */
    private array $missingAtAudit = ['PL', 'SE', 'BE', 'AT', 'DK', 'FI', 'IE', 'GR', 'CZ', 'RO', 'HU'];

    public function up(): void
    {
        $inserted = [];

        foreach ($this->countries as $iso2 => $data) {
            if (! DB::table('countries')->where('iso2', $iso2)->exists()) {
                DB::table('countries')->insert([
                    'iso2' => $iso2,
                    'iso3' => $data['iso3'],
                    'name' => $data['name'],
                    'phone_code' => $data['phone_code'],
                    // countries.status is tinyint(1): 1 = active.
                    'status' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $inserted[] = $iso2;
            }
        }

        echo 'Inserted '.count($inserted).' EU countries: '.implode(',', $inserted)."\n";
    }

    public function down(): void
    {
        // Only remove rows this migration actually created (audited subset),
        // so pre-existing ES/IT/PT rows can never be deleted.
        DB::table('countries')
            ->whereIn('iso2', $this->missingAtAudit)
            ->delete();

        echo 'EU country backfill reverted ('.count($this->missingAtAudit)." rows removed).\n";
    }
};

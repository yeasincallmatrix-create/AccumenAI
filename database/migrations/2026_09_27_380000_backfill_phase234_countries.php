<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Phase 2-4 target countries (Gulf, Southeast Asia, First World).
     *
     * up() inserts whatever is missing at runtime — the count depends on the
     * environment, because $missingAtAudit was audited against production:
     *   - prod (accumen_ai): 9 inserted (AU, CA, DE, FR, KW, MY, QA, SA, SG, VN
     *     already existed from earlier seeds)
     *   - test (monetix_test): 19 inserted (only BD + the 6 SAARC rows existed)
     */
    private array $countries = [
        'AE' => ['name' => 'United Arab Emirates', 'iso3' => 'ARE', 'phone_code' => '971'],
        'SA' => ['name' => 'Saudi Arabia',         'iso3' => 'SAU', 'phone_code' => '966'],
        'QA' => ['name' => 'Qatar',                'iso3' => 'QAT', 'phone_code' => '974'],
        'KW' => ['name' => 'Kuwait',               'iso3' => 'KWT', 'phone_code' => '965'],
        'BH' => ['name' => 'Bahrain',              'iso3' => 'BHR', 'phone_code' => '973'],
        'OM' => ['name' => 'Oman',                 'iso3' => 'OMN', 'phone_code' => '968'],
        'MY' => ['name' => 'Malaysia',             'iso3' => 'MYS', 'phone_code' => '60'],
        'TH' => ['name' => 'Thailand',             'iso3' => 'THA', 'phone_code' => '66'],
        'ID' => ['name' => 'Indonesia',            'iso3' => 'IDN', 'phone_code' => '62'],
        'PH' => ['name' => 'Philippines',          'iso3' => 'PHL', 'phone_code' => '63'],
        'VN' => ['name' => 'Vietnam',              'iso3' => 'VNM', 'phone_code' => '84'],
        'US' => ['name' => 'United States',        'iso3' => 'USA', 'phone_code' => '1'],
        'GB' => ['name' => 'United Kingdom',       'iso3' => 'GBR', 'phone_code' => '44'],
        'CA' => ['name' => 'Canada',               'iso3' => 'CAN', 'phone_code' => '1'],
        'AU' => ['name' => 'Australia',            'iso3' => 'AUS', 'phone_code' => '61'],
        'DE' => ['name' => 'Germany',              'iso3' => 'DEU', 'phone_code' => '49'],
        'FR' => ['name' => 'France',               'iso3' => 'FRA', 'phone_code' => '33'],
        'SG' => ['name' => 'Singapore',            'iso3' => 'SGP', 'phone_code' => '65'],
        'NL' => ['name' => 'Netherlands',          'iso3' => 'NLD', 'phone_code' => '31'],
    ];

    /**
     * Absent from `countries` at audit time (2026-09-27, production).
     *
     * up() inserts every missing target at runtime (9 on prod, 19 on the test
     * DB — see class docblock), but down() only removes this audited subset so
     * it can never delete rows that pre-date the migration on production.
     */
    private array $missingAtAudit = ['AE', 'BH', 'GB', 'ID', 'NL', 'OM', 'PH', 'TH', 'US'];

    public function up(): void
    {
        $inserted = 0;

        foreach ($this->countries as $iso2 => $data) {
            $exists = DB::table('countries')->where('iso2', $iso2)->exists();
            if (! $exists) {
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
                $inserted++;
            }
        }

        echo "Phase 2-4 countries backfilled. ({$inserted} inserted of 19 targets)\n";
    }

    public function down(): void
    {
        // Only remove the rows this migration actually created. The other ten
        // targets pre-date this phase and may be referenced by institutes.
        DB::table('countries')
            ->whereIn('iso2', $this->missingAtAudit)
            ->delete();

        echo 'Phase 2-4 country backfill reverted ('.count($this->missingAtAudit)." rows removed).\n";
    }
};

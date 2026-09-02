<?php

namespace App\Console\Commands;

use App\Models\AdministrativeLevel;
use App\Models\AdministrativeUnit;
use App\Models\Country;
use App\Support\BdGeo;
use Illuminate\Console\Command;

/**
 * Seeds the Bangladesh hierarchy (8 divisions / 64 districts / 494 upazilas)
 * into the global geo tables using the authoritative BdGeo constants.
 *
 * This keeps the existing Bangladesh address data working out of the box and
 * identical to the pre-global system. Idempotent: re-running upserts by
 * country+code and never duplicates rows.
 */
class SeedBangladeshGeo extends Command
{
    protected $signature = 'geo:seed-bangladesh {--pretend : Print the planned inserts without writing.}';

    protected $description = 'Seed the Bangladesh hierarchy into the global geo tables from BdGeo constants.';

    public function handle(): int
    {
        $pretend = $this->option('pretend');

        $country = Country::where('iso2', 'BD')->first();
        if ($pretend) {
            $this->line('  [pretend] country BD Bangladesh (id would be created/resolved)');
        } elseif (! $country) {
            $country = Country::create([
                'name' => 'Bangladesh',
                'iso2' => 'BD',
                'iso3' => 'BGD',
                'phone_code' => '880',
                'status' => true,
            ]);
        }

        $levelIds = [];
        foreach ([1 => 'Division', 2 => 'District', 3 => 'Upazila'] as $num => $label) {
            if ($pretend) {
                $this->line("  [pretend] level BD {$num} {$label}");
                $levelIds[$num] = null;

                continue;
            }
            $levelIds[$num] = AdministrativeLevel::updateOrCreate(
                ['country_id' => $country->id, 'level_number' => $num],
                ['name' => $label, 'slug' => "bd_level_{$num}", 'status' => true]
            )->id;
        }

        $divisions = BdGeo::divisions();
        $districts = BdGeo::districts();
        $upazilas = BdGeo::upazilas();

        $unitIdByBdId = []; // legacy BdGeo id → new unit id

        foreach ($divisions as $bdId => $division) {
            if ($pretend) {
                $this->line("  [pretend] unit BD L1 {$bdId} {$division['en']}");

                continue;
            }
            $unitIdByBdId['d'.$bdId] = AdministrativeUnit::updateOrCreate(
                ['country_id' => $country->id, 'code' => 'BD.D'.$bdId],
                ['administrative_level_id' => $levelIds[1], 'parent_id' => null, 'name' => $division['en'], 'status' => true]
            )->id;
        }

        foreach ($districts as $bdId => $district) {
            if ($pretend) {
                $this->line("  [pretend] unit BD L2 {$bdId} {$district['en']}");

                continue;
            }
            $unitIdByBdId['t'.$bdId] = AdministrativeUnit::updateOrCreate(
                ['country_id' => $country->id, 'code' => 'BD.T'.$bdId],
                [
                    'administrative_level_id' => $levelIds[2],
                    'parent_id' => $unitIdByBdId['d'.$district['division_id']] ?? null,
                    'name' => $district['en'],
                    'status' => true,
                ]
            )->id;
        }

        $withZip = 0;
        foreach ($upazilas as $bdId => $upazila) {
            if ($pretend) {
                $this->line("  [pretend] unit BD L3 {$bdId} {$upazila['en']}");

                continue;
            }
            $latitude = isset($upazila['latitude']) ? (float) $upazila['latitude'] : null;
            $longitude = isset($upazila['longitude']) ? (float) $upazila['longitude'] : null;
            AdministrativeUnit::updateOrCreate(
                ['country_id' => $country->id, 'code' => 'BD.U'.$bdId],
                [
                    'administrative_level_id' => $levelIds[3],
                    'parent_id' => $unitIdByBdId['t'.$upazila['district_id']] ?? null,
                    'name' => $upazila['en'],
                    'postal_code' => ! empty($upazila['zip']) ? (string) $upazila['zip'] : null,
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                    'status' => true,
                ]
            );
            if (! empty($upazila['zip'])) {
                $withZip++;
            }
        }

        $this->info('Bangladesh geo seeded: '.count($divisions).' divisions / '.count($districts).' districts / '.count($upazilas).' upazilas ('.$withZip.' with zip codes).');

        return self::SUCCESS;
    }
}

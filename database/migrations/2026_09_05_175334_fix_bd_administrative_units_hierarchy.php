<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $countryId = DB::table('countries')->where('iso2', 'BD')->value('id');
        if (!$countryId) {
            return;
        }

        $level1 = DB::table('administrative_levels')
            ->where('country_id', $countryId)
            ->where('level_number', 1)
            ->value('id');
        $level2 = DB::table('administrative_levels')
            ->where('country_id', $countryId)
            ->where('level_number', 2)
            ->value('id');

        // Division IDs (BD.D1..BD.D8)
        $divisions = DB::table('administrative_units')
            ->where('country_id', $countryId)
            ->where('administrative_level_id', $level1)
            ->where('code', 'like', 'BD.D%')
            ->pluck('id', 'code');

        // Delete legacy duplicate Dhaka (id=1, code='6')
        $legacyDhaka = DB::table('administrative_units')
            ->where('country_id', $countryId)
            ->where('code', '6')
            ->first();
        if ($legacyDhaka) {
            // Reassign orphaned Faridpur child (id=2) to Dhaka BD.D6
            DB::table('administrative_units')->where('id', 2)->update(['parent_id' => $divisions['BD.D6'] ?? null]);
            DB::table('administrative_units')->where('id', $legacyDhaka->id)->delete();
        }

        // Correct district-to-division mapping
        $mapping = [
            'BD.D1' => ['BD.T1','BD.T2','BD.T3','BD.T4','BD.T5','BD.T6','BD.T7','BD.T8','BD.T9','BD.T10'],
            'BD.D2' => ['BD.T39','BD.T40','BD.T41','BD.T42','BD.T43','BD.T44','BD.T45','BD.T54'],
            'BD.D3' => ['BD.T33','BD.T55','BD.T56','BD.T57','BD.T58','BD.T59','BD.T60','BD.T61','BD.T62','BD.T63','BD.T64'],
            'BD.D4' => ['BD.T34','BD.T35','BD.T36','BD.T37','BD.T38'],
            'BD.D5' => ['BD.T15','BD.T16','BD.T17','BD.T18'],
            'BD.D6' => ['BD.T19','BD.T20','BD.T21','BD.T22','BD.T23','BD.T24','BD.T25','BD.T26','BD.T27','BD.T28','BD.T29','BD.T30','BD.T31','BD.T32'],
            'BD.D7' => ['BD.T46','BD.T47','BD.T48','BD.T49','BD.T50','BD.T51','BD.T52','BD.T53'],
            'BD.D8' => ['BD.T11','BD.T12','BD.T13','BD.T14'],
        ];

        foreach ($mapping as $divCode => $distCodes) {
            $divId = $divisions[$divCode] ?? null;
            if (!$divId) {
                continue;
            }
            DB::table('administrative_units')
                ->where('country_id', $countryId)
                ->where('administrative_level_id', $level2)
                ->whereIn('code', $distCodes)
                ->update(['parent_id' => $divId]);
        }

        // Delete Faridpur duplicate (id=31 under Rajshahi should be removed, keep id=2 under Dhaka)
        $faridpurDuplicates = DB::table('administrative_units')
            ->where('country_id', $countryId)
            ->where('name', 'Faridpur')
            ->where('administrative_level_id', $level2)
            ->get();
        if ($faridpurDuplicates->count() > 1) {
            // Keep the one under Dhaka (BD.D6), delete others
            $keepId = $faridpurDuplicates->firstWhere('parent_id', $divisions['BD.D6'])?->id
                ?? $faridpurDuplicates->first()->id;
            $faridpurDuplicates->filter(fn ($r) => $r->id !== $keepId)->each(function ($row) {
                DB::table('administrative_units')->where('parent_id', $row->id)->update(['parent_id' => null]);
                DB::table('administrative_units')->where('id', $row->id)->delete();
            });
        }
    }

    public function down(): void
    {
        // Irreversible data fix
    }
};

<?php

namespace Database\Seeders;

use App\Models\Institute;
use Illuminate\Database\Seeder;

/**
 * TEST-ONLY fixture: institutes hardcoded by name in feature tests
 * (e.g. Institute::where('name', 'Tutu Center')->firstOrFail()).
 *
 * SAFETY: runs ONLY when app()->environment('testing'). Production
 * must NEVER get these rows back — the user explicitly deleted
 * "Tutu Center" from production. The guard below + the TestCase
 * caller guard enforce that.
 *
 * Uses Institute::withoutEvents() to bypass the testing-only B17
 * auto-assign-PREMIUM hook (AppServiceProvider) so package_id stays
 * NULL, and firstOrCreate() for idempotency.
 */
class TestInstituteFixtureSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('testing')) {
            return;
        }

        foreach ([
            ['name' => 'Tutu Center', 'slug' => 'tutu-center'],
            ['name' => 'Mawa Academy', 'slug' => 'mawa-academy'],
        ] as $row) {
            Institute::withoutEvents(function () use ($row) {
                try {
                    Institute::firstOrCreate(
                        ['name' => $row['name']],
                        [
                            'slug' => $row['slug'],
                            'status' => 'active',
                            'country' => 'Bangladesh',
                            'package_id' => null,
                        ],
                    );
                } catch (\Illuminate\Database\QueryException $e) {
                    // Lost a concurrent identical-seed race (paratest workers
                    // share one test DB and can SELECT-miss then INSERT the
                    // same unique slug simultaneously → 1213 deadlock / 1062
                    // duplicate). If the row exists now, another worker won —
                    // continue. Otherwise rethrow so real errors stay visible.
                    if (Institute::where('name', $row['name'])->doesntExist()) {
                        throw $e;
                    }
                }
            });
        }
    }
}

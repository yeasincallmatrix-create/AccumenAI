<?php

namespace Database\Seeders;

use App\Models\Institute;
use Illuminate\Database\QueryException;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

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
 * auto-assign-PREMIUM hook (AppServiceProvider) and pins the FREE
 * package instead — the same backfill the production migration
 * (2026_10_02_000001_backfill_package_for_existing_institutes) does,
 * so fixtures are never caught by the package-selection dashboard gate
 * while still resolving to FREE entitlements (package_id = NULL resolves
 * to FREE anyway, so module access is unchanged).
 */
class TestInstituteFixtureSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('testing')) {
            return;
        }

        $freeId = (int) DB::table('subscription_packages')
            ->whereRaw('LOWER(slug) = ?', ['free'])
            ->value('id');

        foreach ([
            ['name' => 'Tutu Center', 'slug' => 'tutu-center'],
            ['name' => 'Mawa Academy', 'slug' => 'mawa-academy'],
            ['name' => 'Halumoni Computer training center', 'slug' => 'halumoni-computer-training-center'],
        ] as $row) {
            Institute::withoutEvents(function () use ($row, $freeId) {
                try {
                    $institute = Institute::firstOrCreate(
                        ['name' => $row['name']],
                        [
                            'slug' => $row['slug'],
                            'status' => 'active',
                            'country' => 'Bangladesh',
                            'package_id' => $freeId ?: null,
                        ],
                    );

                    // Fixtures created by older runs (or a shared test DB) may
                    // still hold package_id = NULL — align them with the
                    // production backfill so the dashboard gate never trips.
                    if ($freeId && $institute->package_id === null) {
                        $institute->forceFill(['package_id' => $freeId])->save();
                    }
                } catch (QueryException $e) {
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

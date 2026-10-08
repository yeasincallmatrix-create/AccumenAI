<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Reconcile package_industry_modules for plan-mode tiers.
     *
     * 1. Delete orphan rows whose (package, industry) has no active
     *    package_industries mapping (e.g. Manufacturing Growth rows saved
     *    under healthcare) — they never resolve to any tenant.
     * 2. Fill missing active registry keys as off for every mapped pair,
     *    so each tier has the full row shape. Resolution-neutral: missing
     *    keys contribute nothing to the resolver today.
     * 3. Enforce tier nesting (higher tiers are supersets): any module ON
     *    in a lower tier is turned ON in every higher tier of the same
     *    industry. Tier order comes from the package slug suffix
     *    (starter < growth < enterprise), same rule as
     *    ModuleAccessService::packageTierRank().
     *
     * Tier *contents* are otherwise untouched — existing ON sets stand.
     */
    public function up(): void
    {
        if (! Schema::hasTable('package_industry_modules')) {
            return;
        }

        $hasCategory = Schema::hasColumn('package_industry_modules', 'category');

        DB::transaction(function () use ($hasCategory) {
            // 1. Orphans.
            $mapped = DB::table('package_industries')
                ->where('is_active', true)
                ->get(['package_id', 'industry_key'])
                ->map(fn ($r) => ((int) $r->package_id).'|'.$r->industry_key)
                ->flip();

            $orphanIds = [];
            foreach (DB::table('package_industry_modules')->get(['id', 'package_id', 'industry_key']) as $row) {
                if (! isset($mapped[((int) $row->package_id).'|'.$row->industry_key])) {
                    $orphanIds[] = $row->id;
                }
            }
            foreach (array_chunk($orphanIds, 500) as $chunk) {
                DB::table('package_industry_modules')->whereIn('id', $chunk)->delete();
            }

            // 2. Full shape per mapped pair.
            $activeKeys = DB::table('module_registry')
                ->where('status', 'active')
                ->pluck('key')
                ->all();

            $now = now();
            foreach ($mapped as $pair => $_) {
                [$packageId, $industryKey] = explode('|', $pair, 2);
                $have = DB::table('package_industry_modules')
                    ->where('package_id', (int) $packageId)
                    ->where('industry_key', $industryKey)
                    ->pluck('module_key')
                    ->flip();

                $rows = [];
                foreach ($activeKeys as $key) {
                    if (isset($have[$key])) {
                        continue;
                    }
                    $row = [
                        'package_id' => (int) $packageId,
                        'industry_key' => $industryKey,
                        'module_key' => $key,
                        'enabled' => false,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                    if ($hasCategory) {
                        $row['category'] = 'hidden';
                    }
                    $rows[] = $row;
                }
                foreach (array_chunk($rows, 500) as $chunk) {
                    DB::table('package_industry_modules')->insert($chunk);
                }
            }

            // 3. Nesting: ON in a lower tier ⇒ ON in every higher tier.
            $slugs = DB::table('subscription_packages')->pluck('slug', 'id')->all();
            $byIndustry = [];
            foreach ($mapped as $pair => $_) {
                [$packageId, $industryKey] = explode('|', $pair, 2);
                $byIndustry[$industryKey][] = (int) $packageId;
            }

            foreach ($byIndustry as $industryKey => $packageIds) {
                usort($packageIds, fn ($a, $b) => [$this->tierRank($slugs[$a] ?? ''), $a] <=> [$this->tierRank($slugs[$b] ?? ''), $b]);

                $onSets = [];
                foreach ($packageIds as $packageId) {
                    $onSets[$packageId] = DB::table('package_industry_modules')
                        ->where('package_id', $packageId)
                        ->where('industry_key', $industryKey)
                        ->where('enabled', true)
                        ->pluck('module_key')
                        ->flip();
                }

                $cumulative = [];
                foreach ($packageIds as $packageId) {
                    foreach (array_keys($cumulative) as $key) {
                        if (isset($onSets[$packageId][$key])) {
                            continue;
                        }
                        $update = ['enabled' => true, 'updated_at' => $now];
                        if ($hasCategory) {
                            $update['category'] = 'default';
                        }
                        DB::table('package_industry_modules')
                            ->where('package_id', $packageId)
                            ->where('industry_key', $industryKey)
                            ->where('module_key', $key)
                            ->update($update);
                        $onSets[$packageId][$key] = true;
                    }
                    foreach ($onSets[$packageId] as $key => $_) {
                        $cumulative[$key] = true;
                    }
                }
            }
        });
    }

    public function down(): void
    {
        // Shape/nesting backfill is not reversible (deleted orphans and
        // previous OFF states are unrecoverable) — no-op by design.
    }

    private function tierRank(string $slug): int
    {
        $s = strtolower($slug);
        if (str_ends_with($s, 'enterprise') || str_ends_with($s, 'premium')) {
            return 3;
        }
        if (str_ends_with($s, 'growth') || str_ends_with($s, 'advanced')) {
            return 2;
        }
        if (str_ends_with($s, 'starter') || str_ends_with($s, 'basic')) {
            return 1;
        }

        return 0;
    }
};

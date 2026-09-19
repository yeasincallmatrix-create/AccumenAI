<?php

namespace App\Console\Commands;

use App\Models\Institute;
use App\Models\PackageFeature;
use App\Models\PackageModule;
use App\Models\PackageScope;
use App\Models\PackageScopedFeature;
use App\Models\PackageScopedModule;
use App\Models\SubscriptionPackage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PackagesGenerateScopes extends Command
{
    protected $signature = 'packages:generate-scopes
        {--backfill : Generate scope rows for all existing institutes}
        {--dry-run : Show what would be created without writing}';

    protected $description = 'Generate package_scopes rows from existing institutes and package_features';

    public function handle(): int
    {
        $backfill = (bool) $this->option('backfill');
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->info('=== DRY RUN — no changes will be written ===');
        }

        $counters = [
            'global_scopes_created' => 0,
            'institute_scopes_created' => 0,
            'features_copied' => 0,
            'modules_copied' => 0,
            'skipped' => 0,
        ];

        DB::transaction(function () use ($backfill, $dryRun, &$counters) {
            $packages = SubscriptionPackage::all();
            $this->ensureGlobalScopes($packages, $dryRun, $counters);

            if ($backfill) {
                $this->backfillInstitutes($packages, $dryRun, $counters);
            }
        });

        $this->newLine();
        $this->info("Global scopes created: {$counters['global_scopes_created']}");
        $this->info("Institute-specific scopes created: {$counters['institute_scopes_created']}");
        $this->info("Feature rows copied: {$counters['features_copied']}");
        $this->info("Module rows copied: {$counters['modules_copied']}");
        $this->info("Skipped (already exist): {$counters['skipped']}");

        return self::SUCCESS;
    }

    private function ensureGlobalScopes($packages, bool $dryRun, array &$counters): void
    {
        foreach ($packages as $package) {
            $hash = implode('-', [$package->id, 'G', 'G', 'G']);
            $exists = PackageScope::where('scope_hash', $hash)->exists();

            if ($exists) {
                $counters['skipped']++;
                continue;
            }

            $this->line("Creating GLOBAL scope for package: {$package->name}");

            if (! $dryRun) {
                try {
                    $scope = PackageScope::create([
                        'package_id' => $package->id,
                        'country_id' => null,
                        'industry_id' => null,
                        'sub_industry_id' => null,
                        'inherit_from_parent' => true,
                        'status' => 'active',
                    ]);

                    $counters['features_copied'] += $this->copyFeatures($package->id, $scope->id);
                    $counters['modules_copied'] += $this->copyModules($package->id, $scope->id);
                } catch (\Illuminate\Database\QueryException $e) {
                    $counters['skipped']++;
                    continue;
                }
            }

            $counters['global_scopes_created']++;
        }
    }

    private function backfillInstitutes($packages, bool $dryRun, array &$counters): void
    {
        Institute::withTrashed()->orderBy('id')->chunk(100, function ($institutes) use ($packages, $dryRun, &$counters) {
            foreach ($institutes as $institute) {
                $packageId = $institute->package_id ?? SubscriptionPackage::where('slug', 'free')->value('id');

                if (! $packageId) {
                    $this->warn("Institute {$institute->id} has no package and no FREE package found — skipping");
                    continue;
                }

                $hash = implode('-', [
                    $packageId,
                    $institute->country_id ?? 'G',
                    $institute->industry_id ?? 'G',
                    $institute->sub_industry_id ?? 'G',
                ]);

                $exists = PackageScope::where('scope_hash', $hash)->exists();

                if ($exists) {
                    $counters['skipped']++;
                    continue;
                }

                $this->line("Creating scope for institute {$institute->id} ({$institute->name}): package_id={$packageId}, c={$institute->country_id}, i={$institute->industry_id}, s={$institute->sub_industry_id}");

                if (! $dryRun) {
                    try {
                        $scope = PackageScope::create([
                            'package_id' => $packageId,
                            'country_id' => $institute->country_id,
                            'industry_id' => $institute->industry_id,
                            'sub_industry_id' => $institute->sub_industry_id,
                            'inherit_from_parent' => true,
                            'status' => 'active',
                        ]);

                        $counters['features_copied'] += $this->copyFeatures($packageId, $scope->id);
                        $counters['modules_copied'] += $this->copyModules($packageId, $scope->id);
                    } catch (\Illuminate\Database\QueryException $e) {
                        $counters['skipped']++;
                        continue;
                    }
                }

                $counters['institute_scopes_created']++;
            }
        });
    }

    private function copyFeatures(int $packageId, int $scopeId): int
    {
        $features = PackageFeature::where('package_id', $packageId)->get();
        $count = 0;

        foreach ($features as $feature) {
            PackageScopedFeature::create([
                'package_scope_id' => $scopeId,
                'feature_key' => $feature->feature_key,
                'enabled' => $feature->enabled,
            ]);
            $count++;
        }

        return $count;
    }

    /**
     * Copy package_modules → package_scoped_modules for a scope (Phase 6).
     * Idempotent: existing (scope, module_key) rows are skipped.
     */
    private function copyModules(int $packageId, int $scopeId): int
    {
        $modules = PackageModule::where('package_id', $packageId)->get();
        $count = 0;

        foreach ($modules as $module) {
            $row = PackageScopedModule::firstOrCreate(
                ['package_scope_id' => $scopeId, 'module_key' => $module->module_key],
                ['enabled' => $module->enabled]
            );
            if ($row->wasRecentlyCreated) {
                $count++;
            }
        }

        return $count;
    }
}

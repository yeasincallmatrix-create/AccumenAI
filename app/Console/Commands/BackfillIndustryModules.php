<?php

namespace App\Console\Commands;

use App\Models\Institute;
use App\Models\InstituteModuleOverride;
use App\Services\ModuleAccessService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class BackfillIndustryModules extends Command
{
    protected $signature = 'modules:backfill-industry {--dry-run : Preview changes without applying}';

    protected $description = 'Ensure industry module overrides match the institute\'s industry field';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $moduleService = app(ModuleAccessService::class);

        $industryToModule = [
            'education' => 'education',
            'healthcare' => 'medical',
            'training_center' => 'training_center',
        ];
        $industryModules = array_values($industryToModule);

        $counters = ['cleaned' => 0, 'ensured' => 0, 'skipped' => 0];

        Institute::query()->orderBy('id')->chunk(100, function ($institutes) use ($industryToModule, $industryModules, $moduleService, $dryRun, &$counters) {
            foreach ($institutes as $institute) {
                $desiredModule = $industryToModule[$institute->industry ?? ''] ?? null;

                if ($desiredModule === null) {
                    // Non-canonical: remove all industry overrides
                    $overrides = InstituteModuleOverride::where('institute_id', $institute->id)
                        ->whereIn('module_key', $industryModules)
                        ->get();

                    if ($overrides->isNotEmpty()) {
                        $this->line("Institute {$institute->id} (" . var_export($institute->industry, true) . ") → removing industry overrides");
                        Log::info('BackfillIndustryModules clean', ['institute_id' => $institute->id, 'industry' => $institute->industry]);
                        if (! $dryRun) {
                            foreach ($overrides as $override) {
                                $override->delete();
                            }
                            $moduleService->flushCache($institute->id);
                        }
                        $counters['cleaned']++;
                    } else {
                        $counters['skipped']++;
                    }
                } else {
                    // Canonical: ensure exactly one enabled override
                    $currentOverride = InstituteModuleOverride::where('institute_id', $institute->id)
                        ->where('module_key', $desiredModule)
                        ->first();

                    if (! $currentOverride || $currentOverride->enabled != true) {
                        $this->line("Institute {$institute->id} ({$institute->industry}) → setting {$desiredModule} = true");
                        Log::info('BackfillIndustryModules ensure', ['institute_id' => $institute->id, 'module' => $desiredModule]);
                        if (! $dryRun) {
                            InstituteModuleOverride::updateOrCreate(
                                ['institute_id' => $institute->id, 'module_key' => $desiredModule],
                                ['enabled' => true]
                            );
                            foreach ($industryModules as $other) {
                                if ($other !== $desiredModule) {
                                    InstituteModuleOverride::updateOrCreate(
                                        ['institute_id' => $institute->id, 'module_key' => $other],
                                        ['enabled' => false]
                                    );
                                }
                            }
                            $moduleService->flushCache($institute->id);
                        }
                        $counters['ensured']++;
                    } else {
                        $counters['skipped']++;
                    }
                }
            }
        });

        $mode = $dryRun ? 'dry-run' : 'applied';
        $this->info("Backfill completed ({$mode}): {$counters['ensured']} ensured, {$counters['cleaned']} cleaned, {$counters['skipped']} already correct.");

        return self::SUCCESS;
    }
}

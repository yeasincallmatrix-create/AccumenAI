<?php

namespace App\Console\Commands;

use App\Models\Institute;
use App\Models\InstituteModuleOverride;
use App\Models\ModuleRegistry;
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

        // Ensure all industry modules are registered in module_registry
        if (! $dryRun) {
            $this->ensureModulesRegistered();
        }

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
                            // Use industry-specific activators to also seed permissions
                            if ($institute->industry === 'healthcare') {
                                app(\App\Services\MedicalModuleActivator::class)->activateForHealthcare($institute);
                            } elseif ($institute->industry === 'training_center') {
                                app(\App\Services\TrainingCenterModuleActivator::class)->activateForTrainingCenter($institute);
                            } else {
                                InstituteModuleOverride::updateOrCreate(
                                    ['institute_id' => $institute->id, 'module_key' => $desiredModule],
                                    ['enabled' => true]
                                );
                            }
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

    /**
     * Ensure all industry modules exist in module_registry so
     * resolveEnabled() and syncIndustryModule() can process them.
     */
    private function ensureModulesRegistered(): void
    {
        $modules = [
            'education' => [
                'name' => 'Education',
                'description' => 'Academic education management: students, classes, batches, exams, results, certificates',
                'sort_order' => 10,
            ],
            'medical' => [
                'name' => 'Medical / Hospital Management',
                'description' => 'Complete Hospital Management System (OPD, IPD, Pharmacy, Lab, Billing)',
                'sort_order' => 50,
            ],
            'training_center' => [
                'name' => 'Training Center',
                'description' => 'Training center management: courses, batches, enrollments, attendance, exams, results, certificates, and fees',
                'sort_order' => 40,
            ],
        ];

        foreach ($modules as $key => $attrs) {
            ModuleRegistry::updateOrCreate(
                ['key' => $key],
                array_merge($attrs, ['type' => 'industry', 'status' => 'active'])
            );
        }

        $this->info('Ensured all industry modules exist in module_registry.');
    }
}

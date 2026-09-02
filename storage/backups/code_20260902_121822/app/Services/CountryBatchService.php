<?php

namespace App\Services;

use App\Models\AcademicGroup;
use App\Models\AcademicLevel;
use App\Models\ClassGrade;
use App\Models\Country;
use App\Models\EducationSystem;
use App\Models\GradeScale;
use App\Models\GradeScaleRow;
use App\Models\ModuleRegistry;
use App\Models\PlatformAuditLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CountryBatchService
{
    public function enableCountries(array $countryIds): array
    {
        return $this->setStatus($countryIds, true);
    }

    public function disableCountries(array $countryIds): array
    {
        return $this->setStatus($countryIds, false);
    }

    public function assignGradeScales(array $countryIds): array
    {
        $summary = $this->newSummary(count($countryIds));
        $definitions = config('grade_scales', []);
        $globalDef = $definitions['global'] ?? null;

        foreach ($countryIds as $id) {
            $country = Country::find($id);
            if ($country === null) {
                $summary['failed']++;
                $summary['details'][] = ['country_id' => $id, 'status' => 'failed', 'message' => 'Country not found'];
                continue;
            }

            try {
                DB::transaction(function () use ($country, $definitions, $globalDef, &$summary) {
                    $existing = GradeScale::whereNull('institute_id')
                        ->where('country_id', $country->id)
                        ->whereNull('education_system_id')
                        ->whereNull('academic_level_id')
                        ->first();

                    if ($existing !== null) {
                        $summary['skipped']++;
                        $summary['details'][] = [
                            'country_id' => $country->id,
                            'country' => $country->name,
                            'iso2' => $country->iso2,
                            'status' => 'skipped',
                            'message' => 'Grade scale already exists',
                            'scale_id' => $existing->id,
                        ];
                        Log::info('[CountryBatch] assign_grade_scale skipped', ['country_id' => $country->id, 'iso2' => $country->iso2]);
                        $this->audit('countries', 'assign_grade_scale', 'skipped', ['country_id' => $country->id, 'iso2' => $country->iso2]);

                        return;
                    }

                    $iso2 = strtoupper((string) $country->iso2);
                    $definition = $definitions[$iso2] ?? $globalDef;

                    if ($definition === null) {
                        throw new \RuntimeException("No grade scale definition found for {$iso2} and no global fallback configured.");
                    }

                    $scale = $this->upsertScale(
                        [
                            'institute_id' => null,
                            'country_id' => $country->id,
                            'education_system_id' => null,
                            'academic_level_id' => null,
                        ],
                        $definition
                    );

                    $summary['success']++;
                    $summary['details'][] = [
                        'country_id' => $country->id,
                        'country' => $country->name,
                        'iso2' => $country->iso2,
                        'status' => 'success',
                        'message' => 'Grade scale created',
                        'scale_id' => $scale->id,
                    ];
                    Log::info('[CountryBatch] assign_grade_scale created', ['country_id' => $country->id, 'iso2' => $country->iso2, 'scale_id' => $scale->id]);
                    $this->audit('countries', 'assign_grade_scale', 'created', ['country_id' => $country->id, 'iso2' => $country->iso2, 'scale_id' => $scale->id]);
                });
            } catch (\Throwable $e) {
                $summary['failed']++;
                $summary['details'][] = [
                    'country_id' => $country->id,
                    'country' => $country->name ?? (string) $id,
                    'status' => 'failed',
                    'message' => $e->getMessage(),
                ];
                Log::error('[CountryBatch] assign_grade_scale failed', ['country_id' => $country->id, 'error' => $e->getMessage()]);
                $this->audit('countries', 'assign_grade_scale', 'failed', ['country_id' => $country->id, 'error' => $e->getMessage()]);
            }
        }

        return $summary;
    }

    public function assignAcademicStructures(array $countryIds): array
    {
        $summary = $this->newSummary(count($countryIds));

        foreach ($countryIds as $id) {
            $country = Country::find($id);
            if ($country === null) {
                $summary['failed']++;
                $summary['details'][] = ['country_id' => $id, 'status' => 'failed', 'message' => 'Country not found'];
                continue;
            }

            try {
                DB::transaction(function () use ($country, &$summary) {
                    $existingSystems = $country->educationSystems()->count();

                    if ($existingSystems > 0) {
                        // Check if structure looks complete (at least one level + class)
                        $hasLevels = AcademicLevel::where('country_id', $country->id)->exists();
                        $hasClasses = ClassGrade::where('country_id', $country->id)->exists();

                        if ($hasLevels && $hasClasses) {
                            $summary['skipped']++;
                            $summary['details'][] = [
                                'country_id' => $country->id,
                                'country' => $country->name,
                                'iso2' => $country->iso2,
                                'status' => 'skipped',
                                'message' => 'Academic structure already exists',
                            ];
                            Log::info('[CountryBatch] assign_academic_structure skipped', ['country_id' => $country->id]);
                            $this->audit('countries', 'assign_academic_structure', 'skipped', ['country_id' => $country->id]);

                            return;
                        }
                    }

                    $created = $this->ensureAcademicStructure($country);

                    $summary['success']++;
                    $summary['details'][] = [
                        'country_id' => $country->id,
                        'country' => $country->name,
                        'iso2' => $country->iso2,
                        'status' => 'success',
                        'message' => 'Academic structure created',
                        'created' => $created,
                    ];
                    Log::info('[CountryBatch] assign_academic_structure created', ['country_id' => $country->id, 'created' => $created]);
                    $this->audit('countries', 'assign_academic_structure', 'created', ['country_id' => $country->id, 'created' => $created]);
                });
            } catch (\Throwable $e) {
                $summary['failed']++;
                $summary['details'][] = [
                    'country_id' => $country->id,
                    'country' => $country->name ?? (string) $id,
                    'status' => 'failed',
                    'message' => $e->getMessage(),
                ];
                Log::error('[CountryBatch] assign_academic_structure failed', ['country_id' => $country->id, 'error' => $e->getMessage()]);
                $this->audit('countries', 'assign_academic_structure', 'failed', ['country_id' => $country->id, 'error' => $e->getMessage()]);
            }
        }

        return $summary;
    }

    public function assignDefaultModules(array $countryIds): array
    {
        $summary = $this->newSummary(count($countryIds));
        $config = config('country_modules', []);
        $defaults = $config['defaults'] ?? ['education', 'crm', 'accounting'];
        $registryKeys = ModuleRegistry::pluck('key')->all();

        foreach ($countryIds as $id) {
            $country = Country::find($id);
            if ($country === null) {
                $summary['failed']++;
                $summary['details'][] = ['country_id' => $id, 'status' => 'failed', 'message' => 'Country not found'];
                continue;
            }

            try {
                $iso2 = strtoupper((string) $country->iso2);
                $modules = $config[$iso2] ?? $defaults;

                // Validate against registry
                $valid = array_values(array_intersect($modules, $registryKeys));
                $invalid = array_values(array_diff($modules, $registryKeys));

                // Country default modules are conceptual (country-level defaults, not per-institute entitlements).
                // We persist the intent via audit log; actual institute entitlements are applied lazily
                // when an institute is created under this country or via ModuleAccessService::grantModule().
                // No data loss: we only record, never delete.
                $this->audit('countries', 'assign_default_modules', 'assigned', [
                    'country_id' => $country->id,
                    'iso2' => $country->iso2,
                    'modules' => $valid,
                    'skipped_invalid' => $invalid,
                ]);

                Log::info('[CountryBatch] assign_default_modules', [
                    'country_id' => $country->id,
                    'iso2' => $country->iso2,
                    'modules' => $valid,
                    'invalid' => $invalid,
                ]);

                $summary['success']++;
                $summary['details'][] = [
                    'country_id' => $country->id,
                    'country' => $country->name,
                    'iso2' => $country->iso2,
                    'status' => 'success',
                    'message' => 'Default modules assigned',
                    'modules' => $valid,
                    'skipped_invalid' => $invalid,
                ];
            } catch (\Throwable $e) {
                $summary['failed']++;
                $summary['details'][] = [
                    'country_id' => $country->id,
                    'country' => $country->name ?? (string) $id,
                    'status' => 'failed',
                    'message' => $e->getMessage(),
                ];
                Log::error('[CountryBatch] assign_default_modules failed', ['country_id' => $country->id, 'error' => $e->getMessage()]);
                $this->audit('countries', 'assign_default_modules', 'failed', ['country_id' => $country->id, 'error' => $e->getMessage()]);
            }
        }

        return $summary;
    }

    public function syncAll(array $countryIds): array
    {
        $summary = $this->newSummary(count($countryIds));

        // Run each sub-operation individually so failures in one do not block others
        $grade = $this->assignGradeScales($countryIds);
        $academic = $this->assignAcademicStructures($countryIds);
        $modules = $this->assignDefaultModules($countryIds);

        // Aggregate
        $summary['details'] = [
            'grade_scales' => $grade,
            'academic_structures' => $academic,
            'default_modules' => $modules,
        ];

        // Overall success if at least one sub-op succeeded per country
        // Count as success if any sub-op was success/skipped; failed only if all three failed
        $summary['success'] = 0;
        $summary['failed'] = 0;
        $summary['skipped'] = 0;

        foreach ($countryIds as $idx => $id) {
            $g = $grade['details'][$idx] ?? null;
            $a = $academic['details'][$idx] ?? null;
            $m = $modules['details'][$idx] ?? null;

            $states = [$g['status'] ?? 'failed', $a['status'] ?? 'failed', $m['status'] ?? 'failed'];

            if (in_array('success', $states, true)) {
                $summary['success']++;
            } elseif (in_array('skipped', $states, true)) {
                $summary['skipped']++;
            } else {
                $summary['failed']++;
            }
        }

        Log::info('[CountryBatch] sync_all completed', ['summary' => $summary]);
        $this->audit('countries', 'sync_all', 'completed', ['summary' => $summary]);

        return $summary;
    }

    // ------------------------------------------------------------------ helpers

    private function setStatus(array $countryIds, bool $status): array
    {
        $summary = $this->newSummary(count($countryIds));

        foreach ($countryIds as $id) {
            $country = Country::find($id);
            if ($country === null) {
                $summary['failed']++;
                $summary['details'][] = ['country_id' => $id, 'status' => 'failed', 'message' => 'Country not found'];
                continue;
            }

            try {
                $country->forceFill(['status' => $status])->save();
                $summary['success']++;
                $summary['details'][] = [
                    'country_id' => $country->id,
                    'country' => $country->name,
                    'iso2' => $country->iso2,
                    'status' => 'success',
                    'message' => $status ? 'Enabled' : 'Disabled',
                ];
                $action = $status ? 'enabled' : 'disabled';
                Log::info("[CountryBatch] country {$action}", ['country_id' => $country->id, 'iso2' => $country->iso2]);
                $this->audit('countries', $action, 'success', ['country_id' => $country->id, 'iso2' => $country->iso2]);
            } catch (\Throwable $e) {
                $summary['failed']++;
                $summary['details'][] = [
                    'country_id' => $country->id,
                    'country' => $country->name ?? (string) $id,
                    'status' => 'failed',
                    'message' => $e->getMessage(),
                ];
                Log::error('[CountryBatch] status change failed', ['country_id' => $country->id, 'error' => $e->getMessage()]);
                $this->audit('countries', $status ? 'enable' : 'disable', 'failed', ['country_id' => $country->id, 'error' => $e->getMessage()]);
            }
        }

        return $summary;
    }

    private function newSummary(int $total): array
    {
        return [
            'total' => $total,
            'success' => 0,
            'failed' => 0,
            'skipped' => 0,
            'details' => [],
        ];
    }

    private function upsertScale(array $scope, array $definition): GradeScale
    {
        $rows = $definition['rows'] ?? [];
        unset($definition['rows']);

        return DB::transaction(function () use ($scope, $definition, $rows) {
            $query = GradeScale::query();
            foreach ($scope as $col => $val) {
                if ($val === null) {
                    $query->whereNull($col);
                } else {
                    $query->where($col, $val);
                }
            }
            $scale = $query->first();

            if ($scale === null) {
                $scale = GradeScale::create(array_merge($scope, $definition));
            } else {
                $scale->forceFill($definition)->save();
                $scale->rows()->delete();
            }

            foreach ($rows as $row) {
                GradeScaleRow::create(array_merge(['grade_scale_id' => $scale->id], $row));
            }

            return $scale;
        });
    }

    /**
     * Ensure a minimal academic structure exists for the country.
     * Uses firstOrCreate with proper scoping to avoid duplicates.
     *
     * @return array{systems:int,levels:int,classes:int,groups:int}
     */
    private function ensureAcademicStructure(Country $country): array
    {
        $counts = ['systems' => 0, 'levels' => 0, 'classes' => 0, 'groups' => 0];

        // If country already has at least one system, we supplement missing levels/classes/groups
        // rather than cloning full Bangladesh/US/UK fixtures. Fallback is a generic structure.
        $system = EducationSystem::firstOrCreate(
            ['country_id' => $country->id, 'code' => 'general'],
            ['name' => 'General Education', 'display_order' => 1, 'status' => true]
        );
        if ($system->wasRecentlyCreated) {
            $counts['systems']++;
        }

        $levelDefs = [
            ['code' => 'primary', 'name' => 'Primary', 'order' => 1, 'classes' => [
                ['code' => '1', 'name' => 'Class 1', 'order' => 1],
                ['code' => '2', 'name' => 'Class 2', 'order' => 2],
                ['code' => '3', 'name' => 'Class 3', 'order' => 3],
                ['code' => '4', 'name' => 'Class 4', 'order' => 4],
                ['code' => '5', 'name' => 'Class 5', 'order' => 5],
            ]],
            ['code' => 'secondary', 'name' => 'Secondary', 'order' => 2, 'classes' => [
                ['code' => '6', 'name' => 'Class 6', 'order' => 1],
                ['code' => '7', 'name' => 'Class 7', 'order' => 2],
                ['code' => '8', 'name' => 'Class 8', 'order' => 3],
                ['code' => '9', 'name' => 'Class 9', 'order' => 4],
                ['code' => '10', 'name' => 'Class 10', 'order' => 5],
            ]],
            ['code' => 'higher_secondary', 'name' => 'Higher Secondary', 'order' => 3, 'classes' => [
                ['code' => '11', 'name' => 'Class 11', 'order' => 1],
                ['code' => '12', 'name' => 'Class 12', 'order' => 2],
            ]],
            ['code' => 'tertiary', 'name' => 'Tertiary', 'order' => 4, 'classes' => [
                ['code' => '1_tertiary', 'name' => 'Year 1', 'order' => 1],
                ['code' => '2_tertiary', 'name' => 'Year 2', 'order' => 2],
                ['code' => '3_tertiary', 'name' => 'Year 3', 'order' => 3],
                ['code' => '4_tertiary', 'name' => 'Year 4', 'order' => 4],
            ]],
        ];

        $groupDefs = [
            ['code' => 'science', 'name' => 'Science', 'order' => 1],
            ['code' => 'humanities', 'name' => 'Humanities', 'order' => 2],
            ['code' => 'business', 'name' => 'Business Studies', 'order' => 3],
        ];

        foreach ($levelDefs as $lvlDef) {
            $level = AcademicLevel::firstOrCreate(
                ['education_system_id' => $system->id, 'code' => $lvlDef['code']],
                ['country_id' => $country->id, 'name' => $lvlDef['name'], 'display_order' => $lvlDef['order'], 'status' => true]
            );
            if ($level->wasRecentlyCreated) {
                $counts['levels']++;
            }

            foreach ($lvlDef['classes'] as $clsDef) {
                $class = ClassGrade::firstOrCreate(
                    ['academic_level_id' => $level->id, 'code' => $clsDef['code']],
                    [
                        'country_id' => $country->id,
                        'education_system_id' => $system->id,
                        'name' => $clsDef['name'],
                        'sequence' => is_numeric($clsDef['code']) ? (int) $clsDef['code'] : null,
                        'display_order' => $clsDef['order'],
                        'status' => true,
                    ]
                );
                if ($class->wasRecentlyCreated) {
                    $counts['classes']++;
                }

                // Groups only for secondary/high secondary levels
                if (in_array($lvlDef['code'], ['secondary', 'higher_secondary'], true)) {
                    foreach ($groupDefs as $gDef) {
                        $group = AcademicGroup::firstOrCreate(
                            ['class_grade_id' => $class->id, 'code' => $gDef['code']],
                            [
                                'country_id' => $country->id,
                                'education_system_id' => $system->id,
                                'academic_level_id' => $level->id,
                                'name' => $gDef['name'],
                                'display_order' => $gDef['order'],
                                'status' => true,
                            ]
                        );
                        if ($group->wasRecentlyCreated) {
                            $counts['groups']++;
                        }
                    }
                }
            }
        }

        return $counts;
    }

    private function audit(string $section, string $action, string $key, ?array $meta): void
    {
        try {
            PlatformAuditLog::record($section, $key, $action, $meta);
        } catch (\Throwable $e) {
            Log::warning('[CountryBatch] audit log failed', ['error' => $e->getMessage()]);
        }
    }
}

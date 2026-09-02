<?php

namespace App\Services;

use App\Models\AcademicGroup;
use App\Models\AcademicLevel;
use App\Models\ClassGrade;
use App\Models\EducationSystem;
use App\Models\Institute;
use App\Models\InstituteAcademicGroup;
use App\Models\InstituteAcademicLevel;
use App\Models\InstituteClassGrade;
use Illuminate\Support\Collection;

/**
 * Resolves the effective academic structure for an institute.
 *
 * Effective = Global Default (country master) + Institute Override, computed
 * without ever modifying — or copying — the global masters. An institute with
 * no overrides simply inherits its country's configured structure.
 *
 * Shape returned by resolve() (plain arrays, ordered):
 *
 *   [
 *     'country'            => Country,
 *     'academic_unit_label'=> string,          // institute > country > 'Class'
 *     'systems'            => [
 *        [
 *          'education_system' => EducationSystem,
 *          'levels'           => [
 *             [
 *               'source'  => 'inherited'|'customized'|'custom',
 *               'level'   => AcademicLevel|null,          // null for custom additions
 *               'override'=> InstituteAcademicLevel|null, // null when inherited
 *               'enabled' => bool,
 *               'name'    => string,
 *               'display_order' => int,
 *               'classes' => [ ... same shape per class ... ],
 *             ],
 *          ],
 *        ],
 *     ],
 *   ]
 *
 * Each class node: source, classGrade, override, enabled, name, display_order,
 * groups. Each group node: source, academicGroup, override, enabled, name,
 * display_order.
 */
class AcademicStructureService
{
    public const SOURCE_INHERITED = 'inherited';

    public const SOURCE_CUSTOMIZED = 'customized';

    public const SOURCE_CUSTOM = 'custom';

    /**
     * The display label for the class/grade concept, honoring the institute's
     * override, then the country's, then the generic default.
     */
    public function academicUnitLabel(Institute $institute): string
    {
        $setting = $institute->settings;

        if ($setting !== null && filled($setting->academic_unit_label)) {
            return (string) $setting->academic_unit_label;
        }

        $country = $institute->country()->first();

        if ($country !== null) {
            return $country->academicUnitLabel();
        }

        return 'Class';
    }

    /**
     * Resolve the full effective academic structure for an institute.
     */
    public function resolve(Institute $institute): array
    {
        $country = $institute->country()->first();

        if ($country === null) {
            return [
                'country' => null,
                'academic_unit_label' => $this->academicUnitLabel($institute),
                'systems' => [],
            ];
        }

        $systems = [];
        foreach ($country->educationSystems()->where('status', true)->get() as $system) {
            $systems[] = [
                'education_system' => $system,
                'levels' => $this->resolveLevels($institute, $system),
            ];
        }

        return [
            'country' => $country,
            'academic_unit_label' => $this->academicUnitLabel($institute),
            'systems' => $systems,
        ];
    }

    /**
     * Resolve the enabled, ordered academic systems for a country (masters
     * only — used by the Super Admin country editor and the institute UI).
     */
    public function systemsForCountry(?int $countryId): Collection
    {
        if ($countryId === null) {
            return collect();
        }

        return EducationSystem::query()
            ->where('country_id', $countryId)
            ->orderBy('display_order')
            ->orderBy('id')
            ->get();
    }

    /**
     * Effective levels of one education system for an institute: inherited
     * global levels (optionally customized) plus institute-created additions.
     */
    private function resolveLevels(Institute $institute, EducationSystem $system): array
    {
        $overrides = InstituteAcademicLevel::query()
            ->where('institute_id', $institute->id)
            ->where('education_system_id', $system->id)
            ->get()
            ->keyBy('academic_level_id');

        $levels = [];

        foreach (AcademicLevel::query()
            ->where('education_system_id', $system->id)
            ->where('status', true)
            ->orderBy('display_order')
            ->orderBy('id')
            ->get() as $globalLevel) {
            $override = $overrides->get($globalLevel->id);

            if ($override !== null && ! $override->status) {
                continue; // disabled for this institute
            }

            $levels[] = [
                'source' => $override !== null ? self::SOURCE_CUSTOMIZED : self::SOURCE_INHERITED,
                'level' => $globalLevel,
                'override' => $override,
                'enabled' => true,
                'name' => $override?->name ?: $globalLevel->name,
                'display_order' => $override?->display_order ?? $globalLevel->display_order,
                'classes' => $this->resolveGlobalClasses($institute, $globalLevel),
            ];
        }

        // Institute-created levels (ordered by their display_order).
        $customLevels = InstituteAcademicLevel::query()
            ->where('institute_id', $institute->id)
            ->where('education_system_id', $system->id)
            ->where('is_custom', true)
            ->where('status', true)
            ->orderBy('display_order')
            ->orderBy('id')
            ->get();

        foreach ($customLevels as $customLevel) {
            $levels[] = [
                'source' => self::SOURCE_CUSTOM,
                'level' => null,
                'override' => $customLevel,
                'enabled' => true,
                'name' => $customLevel->name,
                'display_order' => $customLevel->display_order ?? 0,
                'classes' => $this->resolveCustomClasses($institute, $customLevel),
            ];
        }

        usort($levels, fn ($a, $b) => [$a['display_order'], $a['name']] <=> [$b['display_order'], $b['name']]);

        return $levels;
    }

    /**
     * Effective classes of an inherited (global) level: inherited customized
     * global classes plus custom classes added directly under the global level.
     */
    private function resolveGlobalClasses(Institute $institute, AcademicLevel $globalLevel): array
    {
        $overrides = InstituteClassGrade::query()
            ->where('institute_id', $institute->id)
            ->whereNotNull('class_grade_id')
            ->get()
            ->keyBy('class_grade_id');

        $classes = [];

        foreach (ClassGrade::query()
            ->where('academic_level_id', $globalLevel->id)
            ->where('status', true)
            ->orderBy('display_order')
            ->orderBy('id')
            ->get() as $globalClass) {
            $override = $overrides->get($globalClass->id);

            if ($override !== null && ! $override->status) {
                continue;
            }

            $classes[] = [
                'source' => $override !== null ? self::SOURCE_CUSTOMIZED : self::SOURCE_INHERITED,
                'class_grade' => $globalClass,
                'override' => $override,
                'enabled' => true,
                'name' => $override?->name ?: $globalClass->name,
                'display_order' => $override?->display_order ?? $globalClass->display_order,
                'groups' => $this->resolveGlobalGroups($institute, $globalClass),
            ];
        }

        // Custom classes added by the institute directly under this global level.
        $customClasses = InstituteClassGrade::query()
            ->where('institute_id', $institute->id)
            ->where('academic_level_id', $globalLevel->id)
            ->where('is_custom', true)
            ->where('status', true)
            ->orderBy('display_order')
            ->orderBy('id')
            ->get();

        foreach ($customClasses as $customClass) {
            $classes[] = [
                'source' => self::SOURCE_CUSTOM,
                'class_grade' => null,
                'override' => $customClass,
                'enabled' => true,
                'name' => $customClass->name,
                'display_order' => $customClass->display_order ?? 0,
                'groups' => $this->resolveCustomGroups($institute, $customClass),
            ];
        }

        usort($classes, fn ($a, $b) => [$a['display_order'], $a['name']] <=> [$b['display_order'], $b['name']]);

        return $classes;
    }

    /**
     * Effective classes of an institute-created (custom) level.
     */
    private function resolveCustomClasses(Institute $institute, InstituteAcademicLevel $customLevel): array
    {
        $customClasses = InstituteClassGrade::query()
            ->where('institute_id', $institute->id)
            ->where('institute_academic_level_id', $customLevel->id)
            ->where('status', true)
            ->orderBy('display_order')
            ->orderBy('id')
            ->get();

        $classes = [];
        foreach ($customClasses as $customClass) {
            $classes[] = [
                'source' => self::SOURCE_CUSTOM,
                'class_grade' => null,
                'override' => $customClass,
                'enabled' => true,
                'name' => $customClass->name,
                'display_order' => $customClass->display_order ?? 0,
                'groups' => $this->resolveCustomGroups($institute, $customClass),
            ];
        }

        return $classes;
    }

    /**
     * Effective groups of an inherited (global) class: inherited customized
     * global groups plus custom groups added directly under the global class.
     */
    private function resolveGlobalGroups(Institute $institute, ClassGrade $globalClass): array
    {
        $overrides = InstituteAcademicGroup::query()
            ->where('institute_id', $institute->id)
            ->whereNotNull('academic_group_id')
            ->get()
            ->keyBy('academic_group_id');

        $groups = [];

        foreach (AcademicGroup::query()
            ->where('class_grade_id', $globalClass->id)
            ->where('status', true)
            ->orderBy('display_order')
            ->orderBy('id')
            ->get() as $globalGroup) {
            $override = $overrides->get($globalGroup->id);

            if ($override !== null && ! $override->status) {
                continue;
            }

            $groups[] = [
                'source' => $override !== null ? self::SOURCE_CUSTOMIZED : self::SOURCE_INHERITED,
                'academic_group' => $globalGroup,
                'override' => $override,
                'enabled' => true,
                'name' => $override?->name ?: $globalGroup->name,
                'display_order' => $override?->display_order ?? $globalGroup->display_order,
            ];
        }

        $customGroups = InstituteAcademicGroup::query()
            ->where('institute_id', $institute->id)
            ->where('class_grade_id', $globalClass->id)
            ->where('is_custom', true)
            ->where('status', true)
            ->orderBy('display_order')
            ->orderBy('id')
            ->get();

        foreach ($customGroups as $customGroup) {
            $groups[] = [
                'source' => self::SOURCE_CUSTOM,
                'academic_group' => null,
                'override' => $customGroup,
                'enabled' => true,
                'name' => $customGroup->name,
                'display_order' => $customGroup->display_order ?? 0,
            ];
        }

        usort($groups, fn ($a, $b) => [$a['display_order'], $a['name']] <=> [$b['display_order'], $b['name']]);

        return $groups;
    }

    /**
     * Effective groups of an institute-created (custom) class.
     */
    private function resolveCustomGroups(Institute $institute, InstituteClassGrade $customClass): array
    {
        return InstituteAcademicGroup::query()
            ->where('institute_id', $institute->id)
            ->where('institute_class_grade_id', $customClass->id)
            ->where('status', true)
            ->orderBy('display_order')
            ->orderBy('id')
            ->get()
            ->map(fn ($group) => [
                'source' => self::SOURCE_CUSTOM,
                'academic_group' => null,
                'override' => $group,
                'enabled' => true,
                'name' => $group->name,
                'display_order' => $group->display_order ?? 0,
            ])
            ->all();
    }
}

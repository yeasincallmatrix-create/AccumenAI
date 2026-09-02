<?php

namespace App\Services;

use App\Models\AcademicGroup;
use App\Models\AcademicSelectionGroup;
use App\Models\ClassGrade;
use App\Models\Institute;
use App\Models\InstituteSubject;
use App\Models\Subject;
use App\Models\SubjectAcademicAssignment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Resolves the effective subject curriculum for an institute.
 *
 * Effective = Global assignment (subject ⟷ class/grade, optionally group)
 * applied with the institute's subject overrides — never modifying the global
 * subject assignments.
 *
 * Source resolution for a subject node:
 *   - inherited  → global assignment with no institute override
 *   - customized → global assignment with an institute override row
 *                  (renamed, re-ordered, and/or disabled)
 *   - custom     → subject created by/for this institute (is_custom = 1)
 *
 * Requirement types ('mandatory' | 'optional' | 'elective') are declared at
 * the assignment level and may be overridden per institute. Optional/elective
 * subjects may join a selection group (see AcademicSelectionGroup); mandatory
 * subjects must not be members of a selection group.
 *
 * Node shape returned by resolveForClass() (plain arrays, ordered):
 *
 *   [
 *     'source'    => 'inherited'|'customized'|'custom',
 *     'subject'   => Subject,
 *     'assignment'=> SubjectAcademicAssignment|null,   // null for custom additions
 *     'override'  => InstituteSubject|null,            // null when inherited
 *     'enabled'   => bool,
 *     'name'      => string,      // effective display name
 *     'display_order' => int,     // effective ordering
 *     'requirement_type' => string,                    // effective (global or overridden)
 *     'selection_group_id' => int|null,                // effective membership
 *     'selection_group' => AcademicSelectionGroup|null,
 *     'selection_group_code' => string|null,
 *     'selection_type' => string|null,                 // group selection_type
 *     'minimum_selection' => int|null,                 // institute override (null = none)
 *     'maximum_selection' => int|null,                 // institute override (null = none)
 *     'requirement_overridden' => bool,
 *   ]
 */
class AcademicSubjectService
{
    public const SOURCE_INHERITED = 'inherited';

    public const SOURCE_CUSTOMIZED = 'customized';

    public const SOURCE_CUSTOM = 'custom';

    public const REQUIREMENT_MANDATORY = 'mandatory';

    public const REQUIREMENT_OPTIONAL = 'optional';

    public const REQUIREMENT_ELECTIVE = 'elective';

    public const REQUIREMENT_TYPES = [
        self::REQUIREMENT_MANDATORY,
        self::REQUIREMENT_OPTIONAL,
        self::REQUIREMENT_ELECTIVE,
    ];

    public static function requirementTypeLabel(string $requirementType): string
    {
        return match ($requirementType) {
            self::REQUIREMENT_MANDATORY => 'Mandatory',
            self::REQUIREMENT_OPTIONAL => 'Optional',
            self::REQUIREMENT_ELECTIVE => 'Elective',
            default => ucfirst($requirementType),
        };
    }

    public static function requirementTypeLabelLocalized(string $requirementType): string
    {
        return function_exists('mawa_lang')
            ? mawa_lang('academic.requirement_types.'.$requirementType)
            : self::requirementTypeLabel($requirementType);
    }

    public function __construct(private readonly AcademicStructureService $structure) {}

    /**
     * Effective subject curriculum for one global class/grade of an institute.
     *
     * When $academicGroup is provided, only assignments scoped to that group
     * (plus any class-wide assignments) are considered. When it is null, only
     * class-wide assignments are considered.
     */
    public function resolveForClass(Institute $institute, ClassGrade $classGrade, ?AcademicGroup $academicGroup = null): array
    {
        $assignments = SubjectAcademicAssignment::query()
            ->with(['subject', 'selectionGroup'])
            ->where('class_grade_id', $classGrade->id)
            ->where('status', 'active')
            ->where(function (Builder $query) use ($academicGroup) {
                $query->whereNull('academic_group_id');
                if ($academicGroup !== null) {
                    $query->orWhere('academic_group_id', $academicGroup->id);
                }
            })
            ->orderBy('display_order')
            ->orderBy('id')
            ->get();

        $overrides = InstituteSubject::query()
            ->where('institute_id', $institute->id)
            ->get()
            ->keyBy('subject_id');

        $nodes = [];
        $seen = [];

        foreach ($assignments as $assignment) {
            $subject = $assignment->subject;
            if ($subject === null) {
                continue;
            }

            $override = $overrides->get($subject->id);
            $enabled = $override === null || ! $override->isInactive();

            $requirement = $assignment->requirement_type ?: self::REQUIREMENT_MANDATORY;
            $selectionGroup = $assignment->selectionGroup;
            $requirementOverridden = false;

            if ($override !== null) {
                if (filled($override->requirement_type)) {
                    $requirement = $override->requirement_type;
                    $requirementOverridden = true;
                }
                if ($override->selection_group_id !== null) {
                    $selectionGroup = $override->selectionGroup()->first();
                    $requirementOverridden = true;
                }
            }

            $seen[$subject->id] = true;
            $nodes[] = [
                'source' => $override !== null ? self::SOURCE_CUSTOMIZED : self::SOURCE_INHERITED,
                'subject' => $subject,
                'assignment' => $assignment,
                'override' => $override,
                'enabled' => $enabled,
                'name' => $override?->name ?: $subject->name,
                'display_order' => $override?->display_order ?? $assignment->display_order,
                'requirement_type' => $requirement,
                'selection_group_id' => $selectionGroup?->id,
                'selection_group' => $selectionGroup,
                'selection_group_code' => $selectionGroup?->code,
                'selection_type' => $selectionGroup?->selection_type,
                'minimum_selection' => $override?->minimum_selection,
                'maximum_selection' => $override?->maximum_selection,
                'requirement_overridden' => $requirementOverridden,
            ];
        }

        // Institute-created subjects apply to every class of the institute.
        foreach ($overrides as $override) {
            if (! $override->is_custom) {
                continue;
            }

            $subject = $override->subject()->first();
            if ($subject === null || $subject->status !== 'active' || $subject->deleted_at !== null || isset($seen[$subject->id])) {
                continue;
            }

            $requirement = $override->requirement_type ?: self::REQUIREMENT_MANDATORY;
            $selectionGroup = $override->selection_group_id !== null ? $override->selectionGroup()->first() : null;

            $seen[$subject->id] = true;
            $nodes[] = [
                'source' => self::SOURCE_CUSTOM,
                'subject' => $subject,
                'assignment' => null,
                'override' => $override,
                'enabled' => ! $override->isInactive(),
                'name' => $override->name ?: $subject->name,
                'display_order' => $override->display_order ?? 0,
                'requirement_type' => $requirement,
                'selection_group_id' => $selectionGroup?->id,
                'selection_group' => $selectionGroup,
                'selection_group_code' => $selectionGroup?->code,
                'selection_type' => $selectionGroup?->selection_type,
                'minimum_selection' => $override->minimum_selection,
                'maximum_selection' => $override->maximum_selection,
                'requirement_overridden' => $override->requirement_type !== null || $override->selection_group_id !== null,
            ];
        }

        usort($nodes, fn ($a, $b) => [$a['display_order'], $a['name']] <=> [$b['display_order'], $b['name']]);

        return $nodes;
    }

    public function resolveForInstitute(Institute $institute): array
    {
        $classes = $this->effectiveClasses($institute);

        $nodes = [];
        foreach ($classes as $class) {
            $nodes[(string) $class['class_grade']->id] = $this->resolveForClass($institute, $class['class_grade']);
        }

        return ['classes' => $classes, 'subjects' => $nodes];
    }

    /**
     * Raw global assignments for a class (admin view) — no institute overrides,
     * includes inactive assignments so the admin can re-enable / reorder them.
     */
    public function resolveRawAssignments(ClassGrade $classGrade, ?AcademicGroup $academicGroup = null): array
    {
        $assignments = SubjectAcademicAssignment::query()
            ->with(['subject', 'selectionGroup'])
            ->where('class_grade_id', $classGrade->id)
            ->where(function (Builder $query) use ($academicGroup) {
                $query->whereNull('academic_group_id');
                if ($academicGroup !== null) {
                    $query->orWhere('academic_group_id', $academicGroup->id);
                }
            })
            ->orderBy('display_order')
            ->orderBy('id')
            ->get();

        return $assignments
            ->filter(fn ($assignment) => $assignment->subject !== null)
            ->map(fn ($assignment) => [
                'assignment' => $assignment,
                'subject' => $assignment->subject,
                'enabled' => $assignment->status === 'active',
                'name' => $assignment->subject->name,
                'display_order' => $assignment->display_order,
                'requirement_type' => $assignment->requirement_type ?: self::REQUIREMENT_MANDATORY,
                'selection_group_id' => $assignment->selection_group_id,
                'selection_group' => $assignment->selectionGroup,
                'selection_group_code' => $assignment->selectionGroup?->code,
                'selection_type' => $assignment->selectionGroup?->selection_type,
            ])
            ->values()
            ->all();
    }

    /**
     * Global classes (with effective names) available to the institute, i.e.
     * every enabled class within its country's active education structure.
     */
    public function effectiveClasses(Institute $institute): array
    {
        $structure = $this->structure->resolve($institute);

        $classes = [];
        foreach ($structure['systems'] as $systemData) {
            foreach ($systemData['levels'] as $levelNode) {
                foreach ($levelNode['classes'] as $classNode) {
                    $classGrade = $classNode['class_grade'] ?? null;
                    if ($classGrade === null) {
                        continue; // custom classes cannot carry global assignments
                    }
                    $classes[] = [
                        'class_grade' => $classGrade,
                        'system_name' => $systemData['education_system']->name,
                        'level_name' => $levelNode['name'],
                        'name' => $classNode['name'],
                        'display_order' => $classNode['display_order'],
                    ];
                }
            }
        }

        usort($classes, fn ($a, $b) => [$a['display_order'], $a['name']] <=> [$b['display_order'], $b['name']]);

        return $classes;
    }

    /**
     * Active selection groups for a class (optionally scoped to one academic
     * group/stream), with their member counts — for the admin group manager UI.
     */
    public function selectionGroupsForClass(ClassGrade $classGrade, ?AcademicGroup $academicGroup = null): array
    {
        $groups = AcademicSelectionGroup::query()
            ->withCount(['assignments' => fn ($q) => $q->where('status', 'active')])
            ->where('class_grade_id', $classGrade->id)
            ->where('status', 'active')
            ->where(function (Builder $query) use ($academicGroup) {
                $query->whereNull('academic_group_id');
                if ($academicGroup !== null) {
                    $query->orWhere('academic_group_id', $academicGroup->id);
                }
            })
            ->orderBy('display_order')
            ->orderBy('id')
            ->get();

        return $groups->map(fn (AcademicSelectionGroup $group) => [
            'group' => $group,
            'member_count' => (int) $group->assignments_count,
        ])->all();
    }

    /**
     * Effective, selection-shaped view of the curriculum for one class/group:
     *
     *   [
     *     'mandatory' => [ node ... ],                       // required subjects
     *     'groups'    => [ ['group' => model, 'rules' => ..., 'members' => [ node ... ]] ... ],
     *     'ungrouped' => [ node ... ],                       // free optional/elective
     *     'flat'      => [ subject_id => [ 'subject', 'requirement_type', ... ] ],
     *     'config_errors' => [ 'message' ... ],              // e.g. mandatory in a group
     *   ]
     *
     * Only enabled subjects are included. Rules carry the effective min/max
     * after institute overrides (see groupRules()).
     */
    public function resolveForSelection(Institute $institute, ClassGrade $classGrade, ?AcademicGroup $academicGroup = null): array
    {
        $nodes = array_values(array_filter(
            $this->resolveForClass($institute, $classGrade, $academicGroup),
            fn ($node) => $node['enabled']
        ));

        $mandatory = [];
        $grouped = [];
        $ungrouped = [];
        $configErrors = [];

        foreach ($nodes as $node) {
            $requirement = $node['requirement_type'];
            $groupId = $node['selection_group_id'];

            if ($requirement === self::REQUIREMENT_MANDATORY && $groupId === null) {
                $mandatory[] = $node;

                continue;
            }

            if ($groupId !== null && $node['selection_group'] !== null) {
                if ($requirement === self::REQUIREMENT_MANDATORY) {
                    $configErrors[] = 'Mandatory subject "'.$node['name'].'" cannot be a member of a selection group.';
                }
                if (! isset($grouped[$groupId])) {
                    $grouped[$groupId] = [
                        'group' => $node['selection_group'],
                        'rules' => null,
                        'members' => [],
                    ];
                }
                $grouped[$groupId]['members'][] = $node;

                continue;
            }

            $ungrouped[] = $node;
        }

        foreach ($grouped as $groupId => &$entry) {
            $entry['rules'] = $this->groupRules($entry['group'], $entry['members']);
        }
        unset($entry);

        return [
            'mandatory' => $mandatory,
            'groups' => array_values($grouped),
            'ungrouped' => $ungrouped,
            'flat' => $this->flattenSelection($mandatory, $grouped, $ungrouped),
            'config_errors' => $configErrors,
        ];
    }

    /**
     * Effective selection rules for one group. The group's own min/max are the
     * defaults; when the institute overrides min/max on any member subject, the
     * override values (falling back to the group defaults per field) become the
     * institute's rule for the whole group.
     */
    private function groupRules(AcademicSelectionGroup $group, array $members): array
    {
        $size = count($members);

        $override = null;
        foreach ($members as $node) {
            if ($node['minimum_selection'] !== null || $node['maximum_selection'] !== null) {
                if ($override === null || $node['display_order'] < $override['display_order']) {
                    $override = $node;
                }
            }
        }

        $minimum = $override ? ($override['minimum_selection'] ?? $group->minimum_selection) : $group->minimum_selection;
        $maximum = $override ? ($override['maximum_selection'] ?? $group->maximum_selection) : $group->maximum_selection;

        $minimum ??= 0;
        $maximum ??= $size;

        return [
            'minimum' => (int) $minimum,
            'maximum' => (int) $maximum,
            'size' => $size,
            'selection_type' => $group->selection_type ?: self::REQUIREMENT_OPTIONAL,
            'overridden' => $override !== null,
            'member_ids' => array_map(fn ($node) => (int) $node['subject']->id, $members),
            'valid' => $minimum >= 0 && $maximum >= 0 && $minimum <= $maximum,
        ];
    }

    /**
     * subject_id → quick lookup map for the validator / selection UI.
     */
    private function flattenSelection(array $mandatory, array $grouped, array $ungrouped): array
    {
        $flat = [];

        foreach ($mandatory as $node) {
            $flat[(int) $node['subject']->id] = [
                'subject' => $node['name'],
                'requirement_type' => $node['requirement_type'],
                'selection_group_id' => null,
                'mandatory' => true,
                'in_group' => false,
            ];
        }

        foreach ($grouped as $entry) {
            $groupId = (int) $entry['group']->id;
            foreach ($entry['members'] as $node) {
                $flat[(int) $node['subject']->id] = [
                    'subject' => $node['name'],
                    'requirement_type' => $node['requirement_type'],
                    'selection_group_id' => $groupId,
                    'group_name' => $entry['group']->name,
                    'group_minimum' => $entry['rules']['minimum'],
                    'group_maximum' => $entry['rules']['maximum'],
                    'mandatory' => false,
                    'in_group' => true,
                ];
            }
        }

        foreach ($ungrouped as $node) {
            $flat[(int) $node['subject']->id] = [
                'subject' => $node['name'],
                'requirement_type' => $node['requirement_type'],
                'selection_group_id' => null,
                'mandatory' => false,
                'in_group' => false,
            ];
        }

        return $flat;
    }

    /**
     * Academic subjects available to the admin "add subject" dropdown — every
     * active academic master subject not already assigned to the target class.
     */
    public function addableSubjects(ClassGrade $classGrade, ?AcademicGroup $academicGroup = null): Collection
    {
        $assignedIds = SubjectAcademicAssignment::query()
            ->where('class_grade_id', $classGrade->id)
            ->where(function (Builder $query) use ($academicGroup) {
                $query->whereNull('academic_group_id');
                if ($academicGroup !== null) {
                    $query->orWhere('academic_group_id', $academicGroup->id);
                }
            })
            ->pluck('subject_id');

        return Subject::query()
            ->where('subject_type', 'academic')
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->whereNotIn('id', $assignedIds)
            ->orderBy('name')
            ->get(['id', 'name', 'subject_code', 'short_name']);
    }
}

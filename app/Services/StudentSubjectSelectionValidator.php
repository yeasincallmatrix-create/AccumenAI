<?php

namespace App\Services;

use App\Models\AcademicGroup;
use App\Models\ClassGrade;
use App\Models\Institute;

/**
 * Validates a student's subject selection against the effective curriculum.
 *
 * Pure rules engine — no persistence. Feed it the resolved curriculum for a
 * class/group and the chosen subject ids; it returns structured errors for
 * missing mandatory subjects, selection-group min/max violations, duplicates
 * and out-of-curriculum picks.
 *
 * The student selection UI/persistence is a later step (see the enrollment
 * schema gap): students are currently tied to batches/courses, not to a
 * class_grade/academic_group, so this validator is wired for when a placement
 * (class_grade_id + academic_group_id) exists.
 */
class StudentSubjectSelectionValidator
{
    public function __construct(private readonly AcademicSubjectService $service) {}

    /**
     * @param  array<int, int|string>  $selectedIds
     */
    public function validate(
        Institute $institute,
        ClassGrade $classGrade,
        ?AcademicGroup $academicGroup,
        array $selectedIds,
        bool $autoIncludeMandatory = true
    ): array {
        $selection = $this->service->resolveForSelection($institute, $classGrade, $academicGroup);

        $counts = [];
        foreach (array_values($selectedIds) as $id) {
            $id = (int) $id;
            if ($id > 0) {
                $counts[$id] = ($counts[$id] ?? 0) + 1;
            }
        }

        $flat = $selection['flat'];

        $errors = [];
        foreach ($counts as $id => $count) {
            if ($count > 1) {
                $errors[] = [
                    'code' => 'duplicate_subject',
                    'subject_id' => $id,
                    'subject' => $flat[$id]['subject'] ?? null,
                    'message' => 'Subject is selected more than once.',
                ];
            }
            if (! isset($flat[$id])) {
                $errors[] = [
                    'code' => 'subject_not_available',
                    'subject_id' => $id,
                    'subject' => null,
                    'message' => 'Selected subject is not available for this class.',
                ];
            }
        }

        $autoIncluded = [];
        foreach ($selection['mandatory'] as $node) {
            $id = (int) $node['subject']->id;
            if (isset($counts[$id]) && $counts[$id] > 0) {
                continue;
            }
            if ($autoIncludeMandatory) {
                $autoIncluded[] = ['subject_id' => $id, 'subject' => $node['name']];
                $counts[$id] = 1;
            } else {
                $errors[] = [
                    'code' => 'missing_mandatory',
                    'subject_id' => $id,
                    'subject' => $node['name'],
                    'message' => 'Mandatory subject "'.$node['name'].'" must be selected.',
                ];
            }
        }

        foreach ($selection['config_errors'] as $message) {
            $errors[] = ['code' => 'configuration_error', 'message' => $message];
        }

        $groups = [];
        foreach ($selection['groups'] as $entry) {
            $rules = $entry['rules'];
            $memberIds = array_flip($rules['member_ids']);

            $picked = [];
            foreach ($counts as $id => $count) {
                if ($count > 0 && isset($memberIds[$id])) {
                    $picked[] = $id;
                }
            }
            $pickedCount = count($picked);

            if (! $rules['valid']) {
                $errors[] = [
                    'code' => 'invalid_group_rules',
                    'group_id' => (int) $entry['group']->id,
                    'group' => $entry['group']->name,
                    'message' => 'Selection group "'.$entry['group']->name.'" has inconsistent min/max rules.',
                ];
            } elseif ($pickedCount < $rules['minimum']) {
                $errors[] = [
                    'code' => 'group_minimum',
                    'group_id' => (int) $entry['group']->id,
                    'group' => $entry['group']->name,
                    'minimum' => $rules['minimum'],
                    'picked' => $pickedCount,
                    'message' => 'Select at least '.$rules['minimum'].' subject(s) from "'.$entry['group']->name.'".',
                ];
            } elseif ($pickedCount > $rules['maximum']) {
                $errors[] = [
                    'code' => 'group_maximum',
                    'group_id' => (int) $entry['group']->id,
                    'group' => $entry['group']->name,
                    'maximum' => $rules['maximum'],
                    'picked' => $pickedCount,
                    'message' => 'Cannot select more than '.$rules['maximum'].' subject(s) from "'.$entry['group']->name.'".',
                ];
            }

            $groups[(int) $entry['group']->id] = [
                'group' => $entry['group'],
                'rules' => $rules,
                'picked' => $picked,
            ];
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'auto_included' => $autoIncluded,
            'selected_ids' => array_map('intval', array_keys(array_filter($counts, fn ($count) => $count > 0))),
            'selection' => $selection,
            'groups' => $groups,
        ];
    }
}

<?php

namespace App\Services;

use App\Models\AcademicGroup;
use App\Models\AcademicYear;
use App\Models\ClassGrade;
use App\Models\Institute;
use App\Models\Student;
use App\Models\StudentAcademicPlacement;

/**
 * Next-year placement creation for promotion (Step 11).
 *
 * A promotion NEVER updates or deletes the source placement. This service
 * creates a NEW student_academic_placements row for the target academic year
 * (reusing StudentAcademicPlacementService, which enforces the
 * (student_id, academic_year_id) uniqueness and runs the real selection
 * validator) and resolves the subject selection against the NEW class/group:
 *
 *   - mandatory subjects of the target class are auto-included;
 *   - previously selected optional/elective subjects are carried forward only
 *     when they still exist in the target class/group curriculum;
 *   - selection groups are re-checked against the target rules (top-up to the
 *     minimum, capped at the maximum);
 *   - the whole result is then re-validated by the authoritative validator.
 */
class PromotionPlacementService
{
    public function __construct(
        private readonly AcademicSubjectService $subjects,
        private readonly StudentAcademicPlacementService $placements
    ) {}

    /**
     * Resolve the subject selection ids for the target class/group, carrying
     * forward previously selected subjects that still exist there.
     *
     * @return array<int, int>
     */
    public function resolveSelectionIds(
        Institute $institute,
        ClassGrade $targetClass,
        ?AcademicGroup $targetGroup,
        ?StudentAcademicPlacement $source
    ): array {
        $selection = $this->subjects->resolveForSelection($institute, $targetClass, $targetGroup);
        $flat = $selection['flat'];

        $previous = $source !== null
            ? $source->selections()->pluck('subject_id')->map(fn ($id) => (int) $id)->all()
            : [];

        $previousSet = array_fill_keys($previous, true);

        $picked = [];

        foreach ($selection['mandatory'] as $node) {
            $picked[(int) $node['subject']->id] = true;
        }

        foreach ($selection['groups'] as $entry) {
            $rules = $entry['rules'];
            $memberIds = $rules['member_ids'];

            $carried = [];
            foreach ($memberIds as $memberId) {
                if (isset($previousSet[$memberId])) {
                    $carried[] = $memberId;
                }
            }

            $limit = min((int) $rules['maximum'], count($memberIds));
            $top = array_slice($carried, 0, max(0, $limit));
            $count = count($top);

            if ($count < (int) $rules['minimum']) {
                foreach ($memberIds as $memberId) {
                    if ($count >= (int) $rules['minimum']) {
                        break;
                    }
                    if (in_array($memberId, $top, true)) {
                        continue;
                    }
                    $top[] = $memberId;
                    $count++;
                }
            }

            foreach ($top as $memberId) {
                $picked[$memberId] = true;
            }
        }

        foreach ($selection['ungrouped'] as $node) {
            $id = (int) $node['subject']->id;
            if (isset($previousSet[$id])) {
                $picked[$id] = true;
            }
        }

        return array_map('intval', array_keys($picked));
    }

    /**
     * Create the NEW placement for the target academic year with a
     * revalidated subject selection against the target class/group.
     */
    public function createPlacement(
        Institute $institute,
        Student $student,
        AcademicYear $targetYear,
        ClassGrade $targetClass,
        ?AcademicGroup $targetGroup,
        ?StudentAcademicPlacement $source,
        ?string $notes = null
    ): StudentAcademicPlacement {
        $selectedIds = $this->resolveSelectionIds($institute, $targetClass, $targetGroup, $source);

        return $this->placements->storePlacement(
            $institute,
            $student,
            $targetYear,
            $targetClass,
            $targetGroup,
            $selectedIds,
            StudentAcademicPlacement::STATUS_ACTIVE,
            $notes !== null ? $notes : 'Promoted from '.($source?->classGrade?->name ?? 'previous class')
        );
    }
}

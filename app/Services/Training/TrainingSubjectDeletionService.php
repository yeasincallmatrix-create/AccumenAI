<?php

namespace App\Services\Training;

use App\Models\Training\TrainingSubject;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * Training-only subject deletion guard.
 * Dependencies are checked exclusively in training_* tables — no education coupling.
 */
class TrainingSubjectDeletionService
{
    public const STATE_UNREFERENCED = 'UNREFERENCED';

    public const STATE_ACTIVE_DEPENDENCY = 'ACTIVE_DEPENDENCY';

    public function classify(TrainingSubject $subject): array
    {
        $id = (int) $subject->id;

        $coursePivot = Schema::hasTable('training_course_subjects')
            ? (int) DB::table('training_course_subjects')->where('subject_id', $id)->count()
            : 0;
        $examResults = Schema::hasTable('training_exam_results')
            ? (int) DB::table('training_exam_results')->where('subject_id', $id)->count()
            : 0;

        $counts = [
            'training_course_subjects' => $coursePivot,
            'training_exam_results' => $examResults,
        ];

        $hasActive = $coursePivot > 0;
        $hasHistorical = $examResults > 0;

        if ($hasActive) {
            return [
                'state' => self::STATE_ACTIVE_DEPENDENCY,
                'blockReason' => 'This Subject is currently assigned to active Training Courses. Remove all course assignments before deletion.',
                'counts' => $counts,
                'canSoftDelete' => false,
                'canForceDelete' => false,
            ];
        }

        if ($hasHistorical) {
            return [
                'state' => 'HISTORICAL_DEPENDENCY',
                'blockReason' => 'This Subject is referenced by historical training exam results. Hard deletion is blocked.',
                'counts' => $counts,
                'canSoftDelete' => true,
                'canForceDelete' => false,
            ];
        }

        return [
            'state' => self::STATE_UNREFERENCED,
            'blockReason' => null,
            'counts' => $counts,
            'canSoftDelete' => true,
            'canForceDelete' => false,
        ];
    }

    public function softDelete(TrainingSubject $subject): void
    {
        DB::transaction(function () use ($subject) {
            $fresh = TrainingSubject::withTrashed()->whereKey($subject->id)->lockForUpdate()->firstOrFail();
            if ($fresh->deleted_at !== null) {
                throw ValidationException::withMessages(['subject' => 'Subject is already deleted.']);
            }
            $c = $this->classify($fresh);
            if (! $c['canSoftDelete']) {
                throw ValidationException::withMessages(['subject' => $c['blockReason'] ?? 'Deletion is blocked.']);
            }
            $fresh->delete();
        });
    }

    public function restore(TrainingSubject $subject): void
    {
        DB::transaction(function () use ($subject) {
            $t = TrainingSubject::withTrashed()->whereKey($subject->id)->lockForUpdate()->firstOrFail();
            if ($t->deleted_at === null) {
                throw ValidationException::withMessages(['subject' => 'Subject is not deleted.']);
            }
            $t->restore();
        });
    }
}

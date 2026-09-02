<?php

namespace App\Services;

use App\Models\Subject;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SubjectDeletionService
{
    public const STATE_UNREFERENCED = 'UNREFERENCED';
    public const STATE_ACTIVE_DEPENDENCY = 'ACTIVE_DEPENDENCY';
    public const STATE_HISTORICAL_DEPENDENCY = 'HISTORICAL_DEPENDENCY';
    public const STATE_SYSTEM_REFERENCE = 'SYSTEM_REFERENCE';

    public function classify(Subject $subject, bool $withLock = false): array
    {
        $id = (int) $subject->id;
        $countForUpdate = function (string $table, string $column = 'subject_id') use ($id, $withLock) {
            $q = DB::table($table)->where($column, $id);
            if ($withLock) { $q->lockForUpdate(); }
            return (int) $q->count();
        };
        $counts = [
            'course_subjects' => $countForUpdate('course_subjects'),
            'subject_academic_assignments' => $countForUpdate('subject_academic_assignments'),
            'institute_subjects' => $countForUpdate('institute_subjects'),
            'student_subject_selections' => $countForUpdate('student_subject_selections'),
            'assessment_subjects' => $countForUpdate('assessment_subjects'),
            'exam_subjects' => $countForUpdate('exam_subjects'),
            'exam_results' => $countForUpdate('exam_results'),
            'academic_final_result_rows' => $countForUpdate('academic_final_result_rows'),
            'calendar_events' => $countForUpdate('calendar_events'),
            'teacher_academic_assignments' => $countForUpdate('teacher_academic_assignments'),
        ];
        $isGlobal = $subject->institute_id === null;
        $hasGlobalAssignment = $counts['subject_academic_assignments'] > 0;
        $hasHistorical = $counts['exam_results'] > 0 || $counts['academic_final_result_rows'] > 0 || $counts['student_subject_selections'] > 0;
        $hasActive = $counts['course_subjects'] > 0 || $counts['assessment_subjects'] > 0 || $counts['subject_academic_assignments'] > 0 || $counts['institute_subjects'] > 0 || $counts['exam_subjects'] > 0 || $counts['calendar_events'] > 0 || $counts['teacher_academic_assignments'] > 0;
        if ($isGlobal && $hasGlobalAssignment) {
            return ['state' => self::STATE_SYSTEM_REFERENCE, 'blockReason' => 'This is a shared global Subject used as a system reference for academic assignments. Deletion is blocked.', 'counts' => $counts, 'canSoftDelete' => false, 'canForceDelete' => false];
        }
        if ($hasHistorical) {
            return ['state' => self::STATE_HISTORICAL_DEPENDENCY, 'blockReason' => 'This Subject is referenced by historical academic records (exam results, final result snapshots, or student selections). Hard deletion is blocked.', 'counts' => $counts, 'canSoftDelete' => true, 'canForceDelete' => false];
        }
        if ($hasActive) {
            return ['state' => self::STATE_ACTIVE_DEPENDENCY, 'blockReason' => 'This Subject is currently assigned to active Courses, Assessments, Batches, or Classes. Remove all active assignments before deletion.', 'counts' => $counts, 'canSoftDelete' => false, 'canForceDelete' => false];
        }
        return ['state' => self::STATE_UNREFERENCED, 'blockReason' => null, 'counts' => $counts, 'canSoftDelete' => true, 'canForceDelete' => false];
    }

    public function softDelete(Subject $subject, int $actorId): void
    {
        DB::transaction(function () use ($subject, $actorId) {
            $fresh = Subject::withTrashed()->whereKey($subject->id)->lockForUpdate()->firstOrFail();
            if ($fresh->deleted_at !== null) throw ValidationException::withMessages(['subject' => 'Subject is already deleted.']);
            $c = $this->classify($fresh, true);
            if (!$c['canSoftDelete']) throw ValidationException::withMessages(['subject' => $c['blockReason'] ?? 'Deletion is blocked.']);
            $fresh->delete();
            $this->audit($fresh, $actorId, 'subject_soft_deleted', $c);
        });
    }

    public function restore(Subject $subject, int $actorId): void
    {
        DB::transaction(function () use ($subject, $actorId) {
            $t = Subject::withTrashed()->whereKey($subject->id)->lockForUpdate()->firstOrFail();
            if ($t->deleted_at === null) throw ValidationException::withMessages(['subject' => 'Subject is not deleted.']);
            $t->restore();
            $this->audit($t, $actorId, 'subject_restored', $this->classify($t));
        });
    }

    public function forceDelete(Subject $subject, int $actorId): void
    {
        DB::transaction(function () use ($subject, $actorId) {
            $t = Subject::withTrashed()->whereKey($subject->id)->lockForUpdate()->firstOrFail();
            if ($t->deleted_at === null) throw ValidationException::withMessages(['subject' => 'Subject must be soft-deleted before force deletion.']);
            $c = $this->classify($t, true);
            if ($c['state'] !== self::STATE_UNREFERENCED) throw ValidationException::withMessages(['subject' => $c['blockReason']]);
            foreach ($c['counts'] as $table => $cnt) if ($cnt > 0) throw ValidationException::withMessages(['subject' => "Still referenced by $table ($cnt)."]);
            $t->forceDelete();
            $this->audit($t, $actorId, 'subject_force_deleted', $c);
        });
    }

    private function audit(Subject $subject, int $actorId, string $action, array $classification): void
    {
        try {
            DB::table('audit_logs')->insert([
                'institute_id' => $subject->institute_id,
                'module' => 'subjects',
                'action' => $action,
                'record_id' => $subject->id,
                'actor_id' => $actorId,
                'payload' => json_encode(['state' => $classification['state'], 'counts' => $classification['counts']]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('subject_audit_failed', ['action' => $action, 'subject_id' => $subject->id, 'error' => $e->getMessage()]);
        }
    }
}

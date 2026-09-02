<?php

namespace App\Services;

use App\Models\CrmContact;
use App\Models\CrmLead;
use App\Models\CrmOrganization;
use App\Models\CrmTask;
use App\Models\InstituteUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * CRM follow-up task lifecycle (Step 31). A task may be standalone
 * (subject_type/subject_id NULL) or attached to a contact / organization /
 * lead. Subject resolution goes through the model's own global scopes so a
 * foreign or hidden subject is a 404.
 */
class CrmTaskService
{
    private const SUBJECT_TYPES = ['contact', 'organization', 'lead'];

    public function __construct(private readonly CrmAuditService $audit) {}

    public function create(array $data, int $instituteId, ?int $branchId, int $actorId): CrmTask
    {
        $subject = null;
        if (isset($data['subject_type']) && $data['subject_type'] !== null) {
            $subject = $this->resolveSubject($data['subject_type'], $data['subject_id'] ?? null);
        }

        $attributes = [
            'institute_id' => $instituteId,
            'branch_id' => $branchId,
            'subject_type' => $subject?->id !== null ? $subject::CRM_SUBJECT_TYPE : null,
            'subject_id' => $subject?->id,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'priority' => $data['priority'] ?? CrmTask::PRIORITY_NORMAL,
            'status' => $data['status'] ?? CrmTask::STATUS_OPEN,
            'due_at' => $data['due_at'] ?? null,
            'assigned_user_id' => $data['assigned_user_id'] ?? null,
            'created_by' => $actorId,
        ];

        if ($attributes['assigned_user_id'] !== null) {
            $this->assertUserBelongsToInstitute((int) $attributes['assigned_user_id'], $instituteId);
        }

        return DB::transaction(function () use ($attributes, $instituteId, $actorId) {
            $task = CrmTask::create($attributes);
            $this->audit->record($instituteId, $actorId, 'created', $task->id, null, $task->getAttributes());

            return $task;
        });
    }

    public function update(CrmTask $task, array $data, int $instituteId, int $actorId): CrmTask
    {
        $this->assertSameInstitute($task, $instituteId);

        $fill = array_intersect_key($data, array_flip(['title', 'description', 'priority', 'status', 'due_at', 'assigned_user_id']));

        if (isset($fill['assigned_user_id']) && $fill['assigned_user_id'] !== null) {
            $this->assertUserBelongsToInstitute((int) $fill['assigned_user_id'], $instituteId);
        }

        $old = $task->getAttributes();

        return DB::transaction(function () use ($task, $fill, $old, $instituteId, $actorId) {
            $task->fill($fill)->save();
            $this->audit->record($instituteId, $actorId, 'updated', $task->id, $old, $task->fresh()->getAttributes());

            return $task->fresh();
        });
    }

    public function toggleComplete(CrmTask $task, int $instituteId, int $actorId): CrmTask
    {
        $this->assertSameInstitute($task, $instituteId);

        $old = $task->getAttributes();
        $completed = $task->status === CrmTask::STATUS_COMPLETED;

        $task->forceFill([
            'status' => $completed ? CrmTask::STATUS_OPEN : CrmTask::STATUS_COMPLETED,
            'completed_at' => $completed ? null : now(),
        ])->save();

        $this->audit->record($instituteId, $actorId, $completed ? 'updated' : 'task_completed', $task->id, $old, $task->getAttributes());

        return $task->refresh();
    }

    public function delete(CrmTask $task, int $instituteId, int $actorId): void
    {
        $this->assertSameInstitute($task, $instituteId);
        $old = $task->getAttributes();

        DB::transaction(function () use ($task, $old, $instituteId, $actorId) {
            $task->delete();
            $this->audit->record($instituteId, $actorId, 'deleted', $task->id, $old, null);
        });
    }

    // ------------------------------------------------------------- Helpers

    private function resolveSubject(?string $subjectType, mixed $subjectId): Model
    {
        abort_if(! in_array($subjectType, self::SUBJECT_TYPES, true), 422, 'Invalid subject type.');

        $subject = match ($subjectType) {
            'contact' => CrmContact::query()->find($subjectId),
            'organization' => CrmOrganization::query()->find($subjectId),
            'lead' => CrmLead::query()->find($subjectId),
        };

        abort_if($subject === null, 404, 'Subject not found.');

        return $subject;
    }

    private function assertSameInstitute(CrmTask $task, int $instituteId): void
    {
        abort_if((int) $task->institute_id !== (int) $instituteId, 404, 'Task not found.');
    }

    private function assertUserBelongsToInstitute(int $userId, int $instituteId): void
    {
        $exists = InstituteUser::query()
            ->where('id', $userId)
            ->where('institute_id', $instituteId)
            ->exists();

        abort_if(! $exists, 422, 'Assigned user does not belong to this institute.');
    }
}

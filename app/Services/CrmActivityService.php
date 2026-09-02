<?php

namespace App\Services;

use App\Models\CrmActivity;
use App\Models\CrmContact;
use App\Models\CrmLead;
use App\Models\CrmOrganization;
use App\Models\InstituteUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * CRM activity timeline (Step 31): calls, emails, meetings, follow-ups, notes
 * and system events attached to a contact / organization / lead.
 *
 * The subject is resolved through the model's own global scopes (tenant +
 * branch), so a foreign or hidden subject is a 404 — cross-tenant/cross-branch
 * access is impossible.
 */
class CrmActivityService
{
    private const SUBJECT_TYPES = ['contact', 'organization', 'lead'];

    public function __construct(private readonly CrmAuditService $audit) {}

    public function create(array $data, int $instituteId, ?int $branchId, int $actorId): CrmActivity
    {
        $subject = $this->resolveSubject($data['subject_type'] ?? null, $data['subject_id'] ?? null);

        $attributes = [
            'institute_id' => $instituteId,
            'branch_id' => $branchId,
            'subject_type' => $subject::CRM_SUBJECT_TYPE,
            'subject_id' => $subject->id,
            'type' => $data['type'] ?? CrmActivity::TYPE_NOTE,
            'summary' => $data['summary'],
            'description' => $data['description'] ?? null,
            'activity_at' => $data['activity_at'] ?? now(),
            'completed_at' => $data['completed_at'] ?? null,
            'assigned_user_id' => $data['assigned_user_id'] ?? null,
            'created_by' => $actorId,
        ];

        if ($attributes['assigned_user_id'] !== null) {
            $this->assertUserBelongsToInstitute((int) $attributes['assigned_user_id'], $instituteId);
        }

        return DB::transaction(function () use ($attributes, $instituteId, $actorId) {
            $activity = CrmActivity::create($attributes);
            $this->audit->record($instituteId, $actorId, 'activity_added', $activity->id, null, $activity->getAttributes());

            return $activity;
        });
    }

    public function markCompleted(CrmActivity $activity, int $instituteId, int $actorId): CrmActivity
    {
        $this->assertSameInstitute($activity, $instituteId);

        $old = $activity->getAttributes();
        $activity->forceFill(['completed_at' => now()])->save();
        $this->audit->record($instituteId, $actorId, 'updated', $activity->id, $old, $activity->getAttributes());

        return $activity->refresh();
    }

    public function delete(CrmActivity $activity, int $instituteId, int $actorId): void
    {
        $this->assertSameInstitute($activity, $instituteId);
        $old = $activity->getAttributes();

        DB::transaction(function () use ($activity, $old, $instituteId, $actorId) {
            $activity->delete();
            $this->audit->record($instituteId, $actorId, 'deleted', $activity->id, $old, null);
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

    private function assertSameInstitute(CrmActivity $activity, int $instituteId): void
    {
        abort_if((int) $activity->institute_id !== (int) $instituteId, 404, 'Activity not found.');
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

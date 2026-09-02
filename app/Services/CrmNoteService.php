<?php

namespace App\Services;

use App\Models\CrmContact;
use App\Models\CrmLead;
use App\Models\CrmNote;
use App\Models\CrmOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * CRM note lifecycle (Step 31): free-text notes attached to a contact /
 * organization / lead.
 *
 * Same security rules as CrmActivityService: the subject is resolved through
 * its own global scopes so a foreign or hidden subject is a 404.
 */
class CrmNoteService
{
    private const SUBJECT_TYPES = ['contact', 'organization', 'lead'];

    public function __construct(private readonly CrmAuditService $audit) {}

    public function create(array $data, int $instituteId, ?int $branchId, int $actorId): CrmNote
    {
        $subject = $this->resolveSubject($data['subject_type'] ?? null, $data['subject_id'] ?? null);

        $attributes = [
            'institute_id' => $instituteId,
            'branch_id' => $branchId,
            'subject_type' => $subject::CRM_SUBJECT_TYPE,
            'subject_id' => $subject->id,
            'body' => $data['body'],
            'created_by' => $actorId,
        ];

        return DB::transaction(function () use ($attributes, $instituteId, $actorId) {
            $note = CrmNote::create($attributes);
            $this->audit->record($instituteId, $actorId, 'created', $note->id, null, $note->getAttributes());

            return $note;
        });
    }

    public function delete(CrmNote $note, int $instituteId, int $actorId): void
    {
        abort_if((int) $note->institute_id !== (int) $instituteId, 404, 'Note not found.');
        $old = $note->getAttributes();

        DB::transaction(function () use ($note, $old, $instituteId, $actorId) {
            $note->delete();
            $this->audit->record($instituteId, $actorId, 'deleted', $note->id, $old, null);
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
}

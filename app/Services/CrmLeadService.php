<?php

namespace App\Services;

use App\Models\CrmContact;
use App\Models\CrmLead;
use App\Models\CrmLeadSource;
use App\Models\CrmLeadStatus;
use App\Models\CrmOrganization;
use App\Models\InstituteUser;
use App\Services\Notification\NotificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * CRM lead lifecycle (Step 31).
 *
 * Same rules as CrmContactService: tenant/branch identity from the caller,
 * service-level duplicate protection on email (active leads only) returning a
 * 422, and an audit trail per mutation.
 *
 * Conversion turns a lead into an active contact (same branch context), marks
 * the lead won, and links both directions (lead.contact_id + lead.converted_contact_id)
 * inside one transaction.
 */
class CrmLeadService
{
    private const FIELDS = [
        'status_id', 'source_id', 'contact_id', 'organization_id',
        'first_name', 'last_name', 'email', 'phone', 'interest_summary',
        'value_amount', 'assigned_user_id',
    ];

    public function __construct(
        private readonly CrmAuditService $audit,
        private readonly NotificationService $notifications,
    ) {}

    public function create(array $data, int $instituteId, ?int $branchId, int $actorId): CrmLead
    {
        $this->assertUniqueEmail($data['email'] ?? null, $instituteId, null);
        $this->validateReferences($data, $instituteId);

        $attributes = array_merge($this->fillable($data), [
            'institute_id' => $instituteId,
            'branch_id' => $branchId,
            'status_id' => $data['status_id'] ?? $this->defaultStatusId(),
            'created_by' => $actorId,
        ]);

        $lead = DB::transaction(function () use ($attributes, $instituteId, $actorId) {
            $lead = CrmLead::create($attributes);
            $this->audit->record($instituteId, $actorId, 'created', $lead->id, null, $lead->getAttributes());

            return $lead;
        });

        $this->notifyLeadCreated($lead, $instituteId, $actorId);

        return $lead;
    }

    /**
     * Notify the assigned user (or the institute owners) that a new lead
     * arrived. Safe pipeline — never fails lead creation.
     */
    private function notifyLeadCreated(CrmLead $lead, int $instituteId, int $actorId): void
    {
        $recipients = $lead->assigned_user_id !== null
            ? InstituteUser::query()->where('id', $lead->assigned_user_id)->where('institute_id', $instituteId)->get()
            : InstituteUser::query()
                ->where('institute_id', $instituteId)
                ->whereHas('role', fn ($q) => $q->where('slug', 'institute-owner'))
                ->get();

        if ($recipients->isEmpty()) {
            return;
        }

        $this->notifications->send('crm.lead_created', $recipients, [
            'lead_name' => trim(($lead->first_name ?? '').' '.($lead->last_name ?? '')),
            'lead_source' => $lead->source?->name ?? $lead->source?->slug ?? '',
            'lead_status' => $lead->status?->name ?? $lead->status?->slug ?? '',
        ], [
            'actor_type' => 'institute_user',
            'actor_id' => $actorId,
            'channels' => ['in_app'],
        ]);
    }

    public function update(CrmLead $lead, array $data, int $instituteId, int $actorId): CrmLead
    {
        $this->assertSameInstitute($lead, $instituteId);
        $this->assertUniqueEmail($data['email'] ?? $lead->email, $instituteId, $lead->id);
        $this->validateReferences($data, $instituteId);

        $old = $lead->getAttributes();
        $fill = $this->fillable($data);

        return DB::transaction(function () use ($lead, $fill, $old, $instituteId, $actorId) {
            $lead->fill($fill)->forceFill(['updated_by' => $actorId])->save();
            $this->audit->record($instituteId, $actorId, 'updated', $lead->id, $old, $lead->fresh()->getAttributes());

            return $lead->fresh();
        });
    }

    public function delete(CrmLead $lead, int $instituteId, int $actorId): void
    {
        $this->assertSameInstitute($lead, $instituteId);
        $old = $lead->getAttributes();

        DB::transaction(function () use ($lead, $old, $instituteId, $actorId) {
            $lead->delete();
            $this->audit->record($instituteId, $actorId, 'deleted', $lead->id, $old, null);
        });
    }

    public function assign(CrmLead $lead, ?int $assignedUserId, int $instituteId, int $actorId): CrmLead
    {
        $this->assertSameInstitute($lead, $instituteId);
        $this->assertUserBelongsToInstitute($assignedUserId, $instituteId);

        $old = $lead->getAttributes();
        $lead->forceFill(['assigned_user_id' => $assignedUserId, 'updated_by' => $actorId])->save();
        $this->audit->record($instituteId, $actorId, 'assigned', $lead->id, $old, $lead->getAttributes());

        return $lead->refresh();
    }

    /**
     * Convert a lead into a contact (same branch), mark the lead won, and link
     * the conversion both ways. Idempotent-ish: converting an already converted
     * lead returns its existing converted contact.
     */
    public function convert(CrmLead $lead, int $instituteId, int $actorId): CrmContact
    {
        $this->assertSameInstitute($lead, $instituteId);

        if ($lead->converted_contact_id !== null) {
            return CrmContact::withoutGlobalScopes()
                ->where('id', $lead->converted_contact_id)
                ->where('institute_id', $instituteId)
                ->firstOrFail();
        }

        return DB::transaction(function () use ($lead, $instituteId, $actorId) {
            $contact = CrmContact::create([
                'institute_id' => $instituteId,
                'branch_id' => $lead->branch_id,
                'first_name' => $lead->first_name,
                'last_name' => $lead->last_name,
                'email' => $lead->email,
                'phone' => $lead->phone,
                'organization_id' => $lead->organization_id,
                'source_id' => $lead->source_id,
                'assigned_user_id' => $lead->assigned_user_id,
                'is_customer' => true,
                'status' => CrmContact::STATUS_ACTIVE,
                'created_by' => $actorId,
            ]);

            $lead->forceFill([
                'status_id' => CrmLeadStatus::where('slug', CrmLeadStatus::SLUG_WON)->value('id') ?? $lead->status_id,
                'converted_at' => now(),
                'converted_contact_id' => $contact->id,
                'updated_by' => $actorId,
            ])->save();

            $this->audit->record($instituteId, $actorId, 'converted', $lead->id, null, $lead->getAttributes());

            return $contact;
        });
    }

    // ------------------------------------------------------------- Helpers

    private function fillable(array $data): array
    {
        return array_intersect_key($data, array_flip(self::FIELDS));
    }

    private function assertSameInstitute(CrmLead $lead, int $instituteId): void
    {
        abort_if((int) $lead->institute_id !== (int) $instituteId, 404, 'Lead not found.');
    }

    private function assertUniqueEmail(?string $email, int $instituteId, ?int $ignoreId): void
    {
        $email = $email !== null ? trim(mb_strtolower($email)) : '';
        if ($email === '') {
            return;
        }

        $exists = CrmLead::withoutGlobalScopes()
            ->where('institute_id', $instituteId)
            ->whereNull('deleted_at')
            ->where('email', $email)
            ->when($ignoreId !== null, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages(['email' => 'A lead with this email already exists.']);
        }
    }

    private function assertUserBelongsToInstitute(?int $userId, int $instituteId): void
    {
        if ($userId === null) {
            return;
        }

        $exists = InstituteUser::query()
            ->where('id', $userId)
            ->where('institute_id', $instituteId)
            ->exists();

        abort_if(! $exists, 422, 'Assigned user does not belong to this institute.');
    }

    private function defaultStatusId(): ?int
    {
        return CrmLeadStatus::where('is_default', true)->value('id') ?? CrmLeadStatus::where('slug', CrmLeadStatus::SLUG_NEW)->value('id');
    }

    private function validateReferences(array $data, int $instituteId): void
    {
        if (isset($data['status_id']) && $data['status_id'] !== null) {
            abort_if(! CrmLeadStatus::whereKey($data['status_id'])->exists(), 422, 'Unknown lead status.');
        }

        if (isset($data['source_id']) && $data['source_id'] !== null) {
            abort_if(! CrmLeadSource::whereKey($data['source_id'])->exists(), 422, 'Unknown lead source.');
        }

        if (isset($data['contact_id']) && $data['contact_id'] !== null) {
            $contact = CrmContact::withoutGlobalScopes()
                ->where('id', $data['contact_id'])
                ->where('institute_id', $instituteId)
                ->exists();
            abort_if(! $contact, 422, 'Contact does not belong to this institute.');
        }

        if (isset($data['organization_id']) && $data['organization_id'] !== null) {
            $org = CrmOrganization::withoutGlobalScopes()
                ->where('id', $data['organization_id'])
                ->where('institute_id', $instituteId)
                ->exists();
            abort_if(! $org, 422, 'Organization does not belong to this institute.');
        }

        if (isset($data['assigned_user_id'])) {
            $this->assertUserBelongsToInstitute((int) $data['assigned_user_id'], $instituteId);
        }
    }
}

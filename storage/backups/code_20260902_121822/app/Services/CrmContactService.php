<?php

namespace App\Services;

use App\Models\CrmContact;
use App\Models\CrmContactType;
use App\Models\CrmLeadSource;
use App\Models\CrmOrganization;
use App\Models\InstituteUser;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * CRM contact lifecycle (Step 31).
 *
 * - Institute/branch identity is NEVER taken from request input: callers pass
 *   the resolved institute id and the acting branch (branch_id NULL =
 *   whole-institute).
 * - Duplicate protection is a service-level check on the primary identifying
 *   field (email, active records only) returning a 422 — deliberately NOT a DB
 *   unique constraint, so soft-deleted rows never block re-creation and
 *   restore stays the intended recovery path.
 * - Every mutation is audited through CrmAuditService.
 */
class CrmContactService
{
    private const FIELDS = [
        'contact_type_id', 'salutation', 'first_name', 'last_name', 'email',
        'phone', 'phone_alt', 'whatsapp', 'organization_id', 'designation',
        'address_line1', 'city', 'state', 'postal_code', 'country_id',
        'is_customer', 'is_prospect', 'customer_since', 'source_id',
        'assigned_user_id', 'status', 'notes',
    ];

    public function __construct(private readonly CrmAuditService $audit) {}

    public function create(array $data, int $instituteId, ?int $branchId, int $actorId): CrmContact
    {
        $this->assertUniqueEmail($data['email'] ?? null, $instituteId, null);
        $this->validateReferences($data, $instituteId);

        $attributes = array_merge($this->fillable($data), [
            'institute_id' => $instituteId,
            'branch_id' => $branchId,
            'is_customer' => (bool) ($data['is_customer'] ?? false),
            'is_prospect' => (bool) ($data['is_prospect'] ?? false),
            'status' => $data['status'] ?? CrmContact::STATUS_ACTIVE,
            'created_by' => $actorId,
        ]);

        return DB::transaction(function () use ($attributes, $instituteId, $actorId) {
            $contact = CrmContact::create($attributes);
            $this->audit->record($instituteId, $actorId, 'created', $contact->id, null, $contact->getAttributes());

            return $contact;
        });
    }

    public function update(CrmContact $contact, array $data, int $instituteId, int $actorId): CrmContact
    {
        $this->assertSameInstitute($contact, $instituteId);
        $this->assertUniqueEmail($data['email'] ?? $contact->email, $instituteId, $contact->id);
        $this->validateReferences($data, $instituteId);

        $old = $contact->getAttributes();
        $fill = $this->fillable($data);

        if (array_key_exists('is_customer', $data)) {
            $fill['is_customer'] = (bool) $data['is_customer'];
        }
        if (array_key_exists('is_prospect', $data)) {
            $fill['is_prospect'] = (bool) $data['is_prospect'];
        }

        return DB::transaction(function () use ($contact, $fill, $old, $instituteId, $actorId) {
            $contact->fill($fill)->forceFill(['updated_by' => $actorId])->save();
            $this->audit->record($instituteId, $actorId, 'updated', $contact->id, $old, $contact->fresh()->getAttributes());

            return $contact->fresh();
        });
    }

    public function delete(CrmContact $contact, int $instituteId, int $actorId): void
    {
        $this->assertSameInstitute($contact, $instituteId);
        $old = $contact->getAttributes();

        DB::transaction(function () use ($contact, $old, $instituteId, $actorId) {
            $contact->delete();
            $this->audit->record($instituteId, $actorId, 'deleted', $contact->id, $old, null);
        });
    }

    public function assign(CrmContact $contact, ?int $assignedUserId, int $instituteId, int $actorId): CrmContact
    {
        $this->assertSameInstitute($contact, $instituteId);
        $this->assertUserBelongsToInstitute($assignedUserId, $instituteId);

        $old = $contact->getAttributes();
        $contact->forceFill(['assigned_user_id' => $assignedUserId, 'updated_by' => $actorId])->save();
        $this->audit->record($instituteId, $actorId, 'assigned', $contact->id, $old, $contact->getAttributes());

        return $contact->refresh();
    }

    // ------------------------------------------------------------- Helpers

    private function fillable(array $data): array
    {
        return array_intersect_key($data, array_flip(self::FIELDS));
    }

    private function assertSameInstitute(CrmContact $contact, int $instituteId): void
    {
        abort_if((int) $contact->institute_id !== (int) $instituteId, 404, 'Contact not found.');
    }

    private function assertUniqueEmail(?string $email, int $instituteId, ?int $ignoreId): void
    {
        $email = $email !== null ? trim(mb_strtolower($email)) : '';
        if ($email === '') {
            return;
        }

        $exists = CrmContact::withoutGlobalScopes()
            ->where('institute_id', $instituteId)
            ->whereNull('deleted_at')
            ->where('email', $email)
            ->when($ignoreId !== null, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages(['email' => 'A contact with this email already exists.']);
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

    private function validateReferences(array $data, int $instituteId): void
    {
        if (isset($data['organization_id']) && $data['organization_id'] !== null) {
            $org = CrmOrganization::withoutGlobalScopes()
                ->where('id', $data['organization_id'])
                ->where('institute_id', $instituteId)
                ->exists();
            abort_if(! $org, 422, 'Organization does not belong to this institute.');
        }

        if (isset($data['contact_type_id']) && $data['contact_type_id'] !== null) {
            abort_if(! CrmContactType::whereKey($data['contact_type_id'])->exists(), 422, 'Unknown contact type.');
        }

        if (isset($data['source_id']) && $data['source_id'] !== null) {
            abort_if(! CrmLeadSource::whereKey($data['source_id'])->exists(), 422, 'Unknown lead source.');
        }

        if (isset($data['assigned_user_id'])) {
            $this->assertUserBelongsToInstitute((int) $data['assigned_user_id'], $instituteId);
        }
    }
}

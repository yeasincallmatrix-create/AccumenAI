<?php

namespace App\Services;

use App\Models\CrmOrganization;
use App\Models\InstituteUser;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * CRM organization lifecycle (Step 31).
 *
 * Same rules as CrmContactService: tenant/branch identity from the caller,
 * service-level duplicate protection on the primary identifying field (name,
 * active records only) returning a 422, and an audit trail per mutation.
 */
class CrmOrganizationService
{
    private const FIELDS = [
        'name', 'email', 'phone', 'website', 'industry', 'description',
        'address_line1', 'city', 'state', 'postal_code', 'country_id',
        'is_customer', 'is_prospect', 'customer_since', 'assigned_user_id',
        'status', 'notes',
    ];

    public function __construct(private readonly CrmAuditService $audit) {}

    public function create(array $data, int $instituteId, ?int $branchId, int $actorId): CrmOrganization
    {
        $this->assertUniqueName($data['name'] ?? null, $instituteId, null);

        $attributes = array_merge($this->fillable($data), [
            'institute_id' => $instituteId,
            'branch_id' => $branchId,
            'is_customer' => (bool) ($data['is_customer'] ?? false),
            'is_prospect' => (bool) ($data['is_prospect'] ?? false),
            'status' => $data['status'] ?? CrmOrganization::STATUS_ACTIVE,
            'created_by' => $actorId,
        ]);

        return DB::transaction(function () use ($attributes, $instituteId, $actorId) {
            $organization = CrmOrganization::create($attributes);
            $this->audit->record($instituteId, $actorId, 'created', $organization->id, null, $organization->getAttributes());

            return $organization;
        });
    }

    public function update(CrmOrganization $organization, array $data, int $instituteId, int $actorId): CrmOrganization
    {
        $this->assertSameInstitute($organization, $instituteId);
        $this->assertUniqueName($data['name'] ?? $organization->name, $instituteId, $organization->id);

        $old = $organization->getAttributes();
        $fill = $this->fillable($data);

        if (array_key_exists('is_customer', $data)) {
            $fill['is_customer'] = (bool) $data['is_customer'];
        }
        if (array_key_exists('is_prospect', $data)) {
            $fill['is_prospect'] = (bool) $data['is_prospect'];
        }

        return DB::transaction(function () use ($organization, $fill, $old, $instituteId, $actorId) {
            $organization->fill($fill)->forceFill(['updated_by' => $actorId])->save();
            $this->audit->record($instituteId, $actorId, 'updated', $organization->id, $old, $organization->fresh()->getAttributes());

            return $organization->fresh();
        });
    }

    public function delete(CrmOrganization $organization, int $instituteId, int $actorId): void
    {
        $this->assertSameInstitute($organization, $instituteId);
        $old = $organization->getAttributes();

        DB::transaction(function () use ($organization, $old, $instituteId, $actorId) {
            $organization->delete();
            $this->audit->record($instituteId, $actorId, 'deleted', $organization->id, $old, null);
        });
    }

    public function assign(CrmOrganization $organization, ?int $assignedUserId, int $instituteId, int $actorId): CrmOrganization
    {
        $this->assertSameInstitute($organization, $instituteId);

        if ($assignedUserId !== null) {
            $exists = InstituteUser::query()
                ->where('id', $assignedUserId)
                ->where('institute_id', $instituteId)
                ->exists();
            abort_if(! $exists, 422, 'Assigned user does not belong to this institute.');
        }

        $old = $organization->getAttributes();
        $organization->forceFill(['assigned_user_id' => $assignedUserId, 'updated_by' => $actorId])->save();
        $this->audit->record($instituteId, $actorId, 'assigned', $organization->id, $old, $organization->getAttributes());

        return $organization->refresh();
    }

    // ------------------------------------------------------------- Helpers

    private function fillable(array $data): array
    {
        return array_intersect_key($data, array_flip(self::FIELDS));
    }

    private function assertSameInstitute(CrmOrganization $organization, int $instituteId): void
    {
        abort_if((int) $organization->institute_id !== (int) $instituteId, 404, 'Organization not found.');
    }

    private function assertUniqueName(?string $name, int $instituteId, ?int $ignoreId): void
    {
        $name = $name !== null ? trim($name) : '';
        if ($name === '') {
            return;
        }

        $exists = CrmOrganization::withoutGlobalScopes()
            ->where('institute_id', $instituteId)
            ->whereNull('deleted_at')
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->when($ignoreId !== null, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages(['name' => 'An organization with this name already exists.']);
        }
    }
}

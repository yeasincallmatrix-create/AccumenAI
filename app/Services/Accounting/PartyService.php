<?php

namespace App\Services\Accounting;

use App\Models\Party;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Customer / supplier party management.
 *
 * Parties are the unified counterparty record for AR and AP. Duplicate phones
 * within (institute, branch, type) are rejected with a 422 at the service
 * layer. Deletion is a soft delete guarded by posted journal activity.
 */
class PartyService
{
    public function __construct(
        private readonly AccountingAuditService $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(int $instituteId, ?int $branchId, array $data, ?int $actorId = null): Party
    {
        $data = $this->validate($instituteId, $branchId, $data);

        $party = Party::create(array_merge($data, [
            'institute_id' => $instituteId,
            'branch_id' => $branchId,
            'is_active' => true,
            'created_by' => $actorId,
        ]));

        $this->audit->log($instituteId, [
            'branch_id' => $branchId,
            'actor_type' => 'user',
            'actor_id' => $actorId,
            'action' => 'create',
            'entity_type' => 'party',
            'entity_id' => $party->id,
            'after_payload' => ['name' => $party->name, 'type' => $party->type],
        ]);

        return $party;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Party $party, array $data, int $instituteId, ?int $actorId = null): Party
    {
        $data = $this->validate($instituteId, $party->branch_id, $data, exceptPartyId: $party->id);

        $party->forceFill(array_merge($data, [
            'updated_by' => $actorId,
        ]))->save();

        $this->audit->log($instituteId, [
            'branch_id' => $party->branch_id,
            'actor_type' => 'user',
            'actor_id' => $actorId,
            'action' => 'update',
            'entity_type' => 'party',
            'entity_id' => $party->id,
            'after_payload' => ['name' => $party->name, 'type' => $party->type],
        ]);

        return $party;
    }

    /**
     * Soft delete. Parties with posted journal entries keep their record but
     * are deactivated so historical AR/AP derivation stays intact.
     */
    public function delete(Party $party, int $instituteId, ?int $actorId = null): void
    {
        DB::transaction(function () use ($party, $instituteId, $actorId) {
            $party->forceFill([
                'is_active' => false,
                'updated_by' => $actorId,
            ])->save();

            $party->delete();

            $this->audit->log($instituteId, [
                'branch_id' => $party->branch_id,
                'actor_type' => 'user',
                'actor_id' => $actorId,
                'action' => 'delete',
                'entity_type' => 'party',
                'entity_id' => $party->id,
                'after_payload' => ['name' => $party->name],
            ]);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function validate(int $instituteId, ?int $branchId, array $data, ?int $exceptPartyId = null): array
    {
        $validator = validator($data, [
            'type' => ['required', 'in:customer,supplier,both'],
            'customer_group_id' => ['nullable', 'integer'],
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:150'],
            'address' => ['nullable', 'string', 'max:2000'],
            'tin' => ['nullable', 'string', 'max:50'],
            'billing_currency_id' => ['nullable', 'integer'],
            'credit_limit' => ['nullable', 'numeric', 'min:0'],
            'party_meta' => ['nullable', 'array'],
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $data = $validator->validated();

        if (filled($data['phone'] ?? null)) {
            $query = Party::query()
                ->where('institute_id', $instituteId)
                ->where('branch_id', $branchId)
                ->where('type', $data['type'])
                ->where('phone', $data['phone']);

            if ($exceptPartyId !== null) {
                $query->where('id', '!=', $exceptPartyId);
            }

            if ($query->exists()) {
                throw ValidationException::withMessages([
                    'phone' => 'A '.$data['type'].' with this phone already exists in this scope.',
                ]);
            }
        }

        if (isset($data['credit_limit'])) {
            $data['credit_limit'] = (float) $data['credit_limit'];
        }

        return $data;
    }
}

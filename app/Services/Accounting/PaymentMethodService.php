<?php

namespace App\Services\Accounting;

use App\Models\ChartOfAccount;
use App\Models\Payment;
use App\Models\PaymentMethod;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

/**
 * Payment method management (cash, bank, mobile banking, card, ...).
 *
 * A method may link the default posting account (coa_id) used when a payment
 * is recorded. Names are unique per (institute, branch); rows are
 * branch-scoped-or-shared like the rest of the accounting tables. Every write
 * is audit-logged.
 */
class PaymentMethodService
{
    public function __construct(private readonly AccountingAuditService $audit) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function list(int $instituteId, ?int $branchId, array $filters = []): LengthAwarePaginator
    {
        return PaymentMethod::query()
            ->with(['coa', 'branch'])
            ->when($branchId !== null, fn ($query) => $query->where(fn ($scope) => $scope
                ->where('branch_id', $branchId)
                ->orWhereNull('branch_id')))
            ->when(filled($filters['q'] ?? null), fn ($query) => $query->where('name', 'like', '%'.$filters['q'].'%'))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(int $instituteId, ?int $branchId, array $data, ?int $actorId = null): PaymentMethod
    {
        $data = $this->validate($instituteId, $branchId, $data);

        $method = PaymentMethod::create(array_merge($data, [
            'institute_id' => $instituteId,
            'branch_id' => $branchId,
            'is_system' => false,
            'created_by' => $actorId,
        ]));

        $this->audit->log($instituteId, [
            'branch_id' => $branchId,
            'actor_type' => 'user',
            'actor_id' => $actorId,
            'action' => 'create',
            'entity_type' => 'payment_method',
            'entity_id' => $method->id,
            'after_payload' => ['name' => $method->name, 'coa_id' => $method->coa_id],
        ]);

        return $method;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(PaymentMethod $method, array $data, ?int $actorId = null): PaymentMethod
    {
        $data = $this->validate($method->institute_id, $method->branch_id, $data, exceptId: $method->id);

        $method->forceFill(array_merge($data, [
            'updated_by' => $actorId,
        ]))->save();

        $this->audit->log($method->institute_id, [
            'branch_id' => $method->branch_id,
            'actor_type' => 'user',
            'actor_id' => $actorId,
            'action' => 'update',
            'entity_type' => 'payment_method',
            'entity_id' => $method->id,
            'after_payload' => ['name' => $method->name, 'coa_id' => $method->coa_id, 'is_active' => $method->is_active],
        ]);

        return $method;
    }

    public function toggleActive(PaymentMethod $method, ?int $actorId = null): PaymentMethod
    {
        $method->forceFill([
            'is_active' => ! $method->is_active,
            'updated_by' => $actorId,
        ])->save();

        $this->audit->log($method->institute_id, [
            'branch_id' => $method->branch_id,
            'actor_type' => 'user',
            'actor_id' => $actorId,
            'action' => 'update',
            'entity_type' => 'payment_method',
            'entity_id' => $method->id,
            'after_payload' => ['name' => $method->name, 'is_active' => $method->is_active],
        ]);

        return $method;
    }

    public function delete(PaymentMethod $method, ?int $actorId = null): void
    {
        if ($method->is_system) {
            throw ValidationException::withMessages([
                'method' => 'System payment methods cannot be deleted; deactivate them instead.',
            ]);
        }

        $used = Payment::query()
            ->where('institute_id', $method->institute_id)
            ->where('payment_method_id', $method->id)
            ->exists();

        if ($used) {
            throw ValidationException::withMessages([
                'method' => 'Payment methods already used by recorded payments cannot be deleted; deactivate them instead.',
            ]);
        }

        $method->delete();

        $this->audit->log($method->institute_id, [
            'branch_id' => $method->branch_id,
            'actor_type' => 'user',
            'actor_id' => $actorId,
            'action' => 'delete',
            'entity_type' => 'payment_method',
            'entity_id' => $method->id,
            'after_payload' => ['name' => $method->name],
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function validate(int $instituteId, ?int $branchId, array $data, ?int $exceptId = null): array
    {
        $validator = validator($data, [
            'name' => ['required', 'string', 'max:100'],
            'coa_id' => ['nullable', 'integer'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $data = $validator->validated();
        $data['is_active'] = (bool) ($data['is_active'] ?? true);

        $duplicate = PaymentMethod::query()
            ->where('institute_id', $instituteId)
            ->where('branch_id', $branchId)
            ->where('name', $data['name']);

        if ($exceptId !== null) {
            $duplicate->where('id', '!=', $exceptId);
        }

        if ($duplicate->exists()) {
            throw ValidationException::withMessages([
                'name' => 'A payment method with this name already exists in this scope.',
            ]);
        }

        if (filled($data['coa_id'] ?? null)) {
            $coa = ChartOfAccount::query()
                ->where('institute_id', $instituteId)
                ->where('id', (int) $data['coa_id'])
                ->where(fn ($query) => $query
                    ->where('branch_id', $branchId)
                    ->orWhereNull('branch_id'))
                ->first();

            if ($coa === null) {
                throw ValidationException::withMessages([
                    'coa_id' => 'The selected account does not belong to this institute or its branch.',
                ]);
            }

            $data['coa_id'] = (int) $coa->id;
        } else {
            $data['coa_id'] = null;
        }

        return $data;
    }
}

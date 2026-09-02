<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Currency;
use App\Models\HrDepartment;
use App\Models\HrSalaryStructure;
use App\Models\HrSalaryStructureComponent;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class HrSalaryStructureService
{
    public function __construct(private readonly HrAuditService $audit) {}

    public function create(array $data, int $instituteId, ?int $branchId, ?int $actorId): HrSalaryStructure
    {
        $this->assertBranchOfInstitute($branchId, $instituteId);
        if (! empty($data['department_id'])) {
            $this->assertDepartmentOfInstitute((int) $data['department_id'], $instituteId, $branchId);
        }
        if (! empty($data['currency_id'])) {
            $this->assertCurrency((int) $data['currency_id']);
        } else {
            $data['currency_id'] = $this->defaultCurrencyId();
        }
        $this->assertUniqueCode($data['code'] ?? null, $instituteId);

        return DB::transaction(function () use ($data, $instituteId, $branchId, $actorId) {
            $structure = HrSalaryStructure::create([
                'institute_id' => $instituteId,
                'branch_id' => $branchId,
                'department_id' => $data['department_id'] ?? null,
                'name' => trim($data['name']),
                'code' => trim($data['code']),
                'currency_id' => $data['currency_id'],
                'pay_frequency' => $data['pay_frequency'] ?? 'monthly',
                'effective_from' => $data['effective_from'],
                'effective_to' => $data['effective_to'] ?? null,
                'basic_salary' => $data['basic_salary'] ?? 0,
                'housing_allowance' => $data['housing_allowance'] ?? 0,
                'medical_allowance' => $data['medical_allowance'] ?? 0,
                'transport_allowance' => $data['transport_allowance'] ?? 0,
                'other_allowance' => $data['other_allowance'] ?? 0,
                'overtime_rate' => $data['overtime_rate'] ?? 0,
                'bonus_amount' => $data['bonus_amount'] ?? 0,
                'commission_amount' => $data['commission_amount'] ?? 0,
                'deduction_amount' => $data['deduction_amount'] ?? 0,
                'tax_deduction' => $data['tax_deduction'] ?? 0,
                'is_active' => $data['is_active'] ?? true,
                'notes' => $data['notes'] ?? null,
                'created_by' => $actorId,
                'updated_by' => $actorId,
            ]);

            if (! empty($data['components']) && is_array($data['components'])) {
                foreach ($data['components'] as $idx => $c) {
                    HrSalaryStructureComponent::create([
                        'institute_id' => $instituteId,
                        'salary_structure_id' => $structure->id,
                        'name' => trim($c['name']),
                        'code' => trim($c['code'] ?? strtolower(str_replace(' ', '_', $c['name']))),
                        'component_type' => $c['component_type'] ?? 'earning',
                        'amount_type' => $c['amount_type'] ?? 'fixed',
                        'amount' => $c['amount'] ?? 0,
                        'percent_base' => $c['percent_base'] ?? null,
                        'is_taxable' => $c['is_taxable'] ?? true,
                        'display_order' => $c['display_order'] ?? $idx,
                        'is_active' => $c['is_active'] ?? true,
                    ]);
                }
            }

            $this->audit->record($instituteId, $actorId, 'hr_salary_structure_created', $structure->id, null, ['code' => $structure->code, 'name' => $structure->name]);
            return $structure;
        });
    }

    public function update(HrSalaryStructure $structure, array $data, ?int $actorId): HrSalaryStructure
    {
        $this->assertUniqueCode($data['code'] ?? $structure->code, $structure->institute_id, $structure->id);
        if (isset($data['department_id']) && $data['department_id']) {
            $this->assertDepartmentOfInstitute((int) $data['department_id'], $structure->institute_id, $structure->branch_id);
        }
        $old = $structure->toArray();
        $structure->update([
            'name' => isset($data['name']) ? trim($data['name']) : $structure->name,
            'code' => isset($data['code']) ? trim($data['code']) : $structure->code,
            'currency_id' => $data['currency_id'] ?? $structure->currency_id,
            'pay_frequency' => $data['pay_frequency'] ?? $structure->pay_frequency,
            'effective_from' => $data['effective_from'] ?? $structure->effective_from,
            'effective_to' => array_key_exists('effective_to', $data) ? $data['effective_to'] : $structure->effective_to,
            'basic_salary' => $data['basic_salary'] ?? $structure->basic_salary,
            'housing_allowance' => $data['housing_allowance'] ?? $structure->housing_allowance,
            'medical_allowance' => $data['medical_allowance'] ?? $structure->medical_allowance,
            'transport_allowance' => $data['transport_allowance'] ?? $structure->transport_allowance,
            'other_allowance' => $data['other_allowance'] ?? $structure->other_allowance,
            'overtime_rate' => $data['overtime_rate'] ?? $structure->overtime_rate,
            'bonus_amount' => $data['bonus_amount'] ?? $structure->bonus_amount,
            'commission_amount' => $data['commission_amount'] ?? $structure->commission_amount,
            'deduction_amount' => $data['deduction_amount'] ?? $structure->deduction_amount,
            'tax_deduction' => $data['tax_deduction'] ?? $structure->tax_deduction,
            'is_active' => $data['is_active'] ?? $structure->is_active,
            'notes' => array_key_exists('notes', $data) ? $data['notes'] : $structure->notes,
            'updated_by' => $actorId,
        ]);
        $this->audit->record($structure->institute_id, $actorId, 'hr_salary_structure_updated', $structure->id, $old, $structure->fresh()->toArray());
        return $structure;
    }

    public function toggle(HrSalaryStructure $structure, ?int $actorId): HrSalaryStructure
    {
        $old = ['is_active' => $structure->is_active];
        $structure->update(['is_active' => ! $structure->is_active, 'updated_by' => $actorId]);
        $this->audit->record($structure->institute_id, $actorId, 'hr_salary_structure_toggled', $structure->id, $old, ['is_active' => $structure->is_active]);
        return $structure;
    }

    public function delete(HrSalaryStructure $structure, ?int $actorId): void
    {
        $structure->delete();
        $this->audit->record($structure->institute_id, $actorId, 'hr_salary_structure_deleted', $structure->id, ['code' => $structure->code], null);
    }

    private function assertBranchOfInstitute(?int $branchId, int $instituteId): void
    {
        if ($branchId === null) return;
        $exists = Branch::where('institute_id', $instituteId)->where('id', $branchId)->exists();
        if (! $exists) {
            throw ValidationException::withMessages(['branch_id' => 'Branch does not belong to this institute.']);
        }
    }

    private function assertDepartmentOfInstitute(int $deptId, int $instituteId, ?int $branchId): void
    {
        $dept = HrDepartment::where('id', $deptId)->where('institute_id', $instituteId)->first();
        if (! $dept) {
            throw ValidationException::withMessages(['department_id' => 'Department does not belong to this institute.']);
        }
        if ($branchId !== null && $dept->branch_id !== null && (int) $dept->branch_id !== (int) $branchId) {
            throw ValidationException::withMessages(['department_id' => 'Department does not belong to selected branch.']);
        }
    }

    private function assertCurrency(int $currencyId): void
    {
        if (! Currency::where('id', $currencyId)->exists()) {
            throw ValidationException::withMessages(['currency_id' => 'Invalid currency.']);
        }
    }

    private function defaultCurrencyId(): ?int
    {
        $currency = Currency::where('is_base', true)->first() ?? Currency::orderBy('code')->first();
        return $currency?->id;
    }

    private function assertUniqueCode(?string $code, int $instituteId, ?int $ignoreId = null): void
    {
        if ($code === null || trim($code) === '') {
            throw ValidationException::withMessages(['code' => 'Code is required.']);
        }
        $exists = HrSalaryStructure::where('institute_id', $instituteId)->where('code', trim($code))->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))->exists();
        if ($exists) {
            throw ValidationException::withMessages(['code' => 'Code already exists for this institute.']);
        }
    }
}

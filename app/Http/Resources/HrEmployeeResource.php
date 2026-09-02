<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HrEmployeeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'employee_code' => $this->employee_code ?? null,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'email' => $this->email ?? null,
            'phone' => $this->phone ?? null,
            'gender' => $this->gender ?? null,
            'date_of_birth' => $this->date_of_birth?->toDateString(),
            'joining_date' => $this->joining_date?->toDateString(),
            'employment_status' => $this->employment_status,
            'employment_type' => $this->employment_type ?? null,
            'department_id' => $this->department_id ?? null,
            'designation_id' => $this->designation_id ?? null,
            'branch_id' => $this->branch_id ?? null,
        ];
    }
}

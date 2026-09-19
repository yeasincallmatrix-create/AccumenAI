<?php

namespace Database\Seeders;

use App\Models\FiscalYear;
use App\Models\Institute;
use Illuminate\Database\Seeder;

/**
 * Seeds the current open fiscal year for one institute.
 * Idempotent (firstOrCreate on institute+branch+is_current).
 * No-op when no institute id is given.
 */
class FiscalYearSeeder extends Seeder
{
    public function __construct(
        protected ?int $instituteId = null,
        protected ?int $branchId = null,
    ) {}

    public function run(): void
    {
        if ($this->instituteId === null) {
            return;
        }

        if (Institute::whereKey($this->instituteId)->doesntExist()) {
            return;
        }

        FiscalYear::firstOrCreate(
            [
                'institute_id' => $this->instituteId,
                'branch_id' => $this->branchId,
                'is_current' => true,
            ],
            [
                'name' => 'FY ' . date('Y'),
                'start_date' => date('Y-01-01'),
                'end_date' => date('Y-12-31'),
                'status' => 'open',
            ]
        );
    }
}

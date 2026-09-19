<?php

namespace Database\Seeders;

use App\Models\Institute;
use App\Models\TaxRate;
use Illuminate\Database\Seeder;

/**
 * Seeds the standard 15% VAT rate for one institute, attached to the
 * default Standard VAT group (created via TaxGroupSeeder when missing).
 * Idempotent (firstOrCreate on institute+name). No-op without institute.
 */
class TaxRateSeeder extends Seeder
{
    public function __construct(
        protected ?int $instituteId = null,
        protected ?int $branchId = null,
    ) {}

    public function run(): ?TaxRate
    {
        if ($this->instituteId === null) {
            return null;
        }

        if (Institute::whereKey($this->instituteId)->doesntExist()) {
            return null;
        }

        $group = (new TaxGroupSeeder($this->instituteId, $this->branchId))->run();

        return TaxRate::firstOrCreate(
            ['institute_id' => $this->instituteId, 'name' => 'Standard VAT 15%'],
            [
                'branch_id' => $this->branchId,
                'tax_group_id' => $group?->id,
                'type' => 'vat',
                'rate_type' => 'percentage',
                'rate' => 15,
                'is_compound' => false,
                'is_inclusive' => false,
                'effective_from' => date('Y-01-01'),
                'effective_to' => null,
                'is_active' => true,
            ]
        );
    }
}

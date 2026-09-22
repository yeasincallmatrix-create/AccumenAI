<?php

namespace Database\Seeders;

use App\Models\ChartOfAccount;
use App\Models\Institute;
use App\Models\PaymentMethod;
use Illuminate\Database\Seeder;

/**
 * Seeds default Cash / Bank payment methods for one institute,
 * linked to the 1000.1 Cash account (created via ChartOfAccountsSeeder
 * when missing). Idempotent (firstOrCreate on institute+name).
 * No-op without institute.
 */
class PaymentMethodSeeder extends Seeder
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

        (new ChartOfAccountsSeeder($this->instituteId, $this->branchId))->run();

        $cashCoaId = ChartOfAccount::where('institute_id', $this->instituteId)
            ->where('code', '1000.1')
            ->value('id');

        foreach ([
            ['name' => 'Cash', 'coa_id' => $cashCoaId, 'is_system' => true],
            ['name' => 'Bank Transfer', 'coa_id' => null, 'is_system' => true],
            ['name' => 'Mobile Banking', 'coa_id' => null, 'is_system' => false],
        ] as $row) {
            PaymentMethod::firstOrCreate(
                [
                    'institute_id' => $this->instituteId,
                    'branch_id' => $this->branchId,
                    'name' => $row['name'],
                ],
                [
                    'coa_id' => $row['coa_id'],
                    'is_system' => $row['is_system'],
                    'is_active' => true,
                ]
            );
        }
    }
}

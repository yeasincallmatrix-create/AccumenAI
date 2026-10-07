<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Operating Expenses family moves from 5200* to 6000*.
 *
 * Rename only: parent_id links, journal entries and opening balances all
 * reference ids and stay untouched. Rows are matched on (code, name) so a
 * tenant's custom account that happens to share the code is never touched,
 * and each (institute, branch) scope must have the target code free before
 * the row is renamed (otherwise that row is skipped).
 */
return new class extends Migration
{
    /**
     * Old code => [new code, expected account name].
     *
     * @var array<string, array{0: string, 1: string}>
     */
    private const FAMILY = [
        '5200' => ['6000', 'Operating Expenses'],
        '5200.1' => ['6000.1', 'Rent'],
        '5200.2' => ['6000.2', 'Utilities'],
        '5200.3' => ['6000.3', 'Internet & Telephone'],
        '5200.4' => ['6000.4', 'Office Supplies'],
        '5200.5' => ['6000.5', 'Marketing & Advertising'],
        '5200.6' => ['6000.6', 'Travel & Conveyance'],
        '5200.7' => ['6000.7', 'Repairs & Maintenance'],
        '5200.8' => ['6000.8', 'Legal & Professional'],
    ];

    public function up(): void
    {
        $this->rename(self::FAMILY);
    }

    public function down(): void
    {
        $back = [];
        foreach (self::FAMILY as $oldCode => [$newCode, $name]) {
            $back[$newCode] = [$oldCode, $name];
        }

        $this->rename($back);
    }

    /**
     * @param  array<string, array{0: string, 1: string}>  $map
     */
    private function rename(array $map): void
    {
        foreach ($map as $oldCode => [$newCode, $name]) {
            $rows = DB::table('chart_of_accounts')
                ->where('code', $oldCode)
                ->where('name', $name)
                ->get(['id', 'institute_id', 'branch_id']);

            foreach ($rows as $row) {
                if ($this->targetExists($newCode, $row->institute_id, $row->branch_id)) {
                    continue;
                }

                DB::table('chart_of_accounts')
                    ->where('id', $row->id)
                    ->update(['code' => $newCode, 'updated_at' => now()]);
            }
        }
    }

    private function targetExists(string $code, ?int $instituteId, ?int $branchId): bool
    {
        return DB::table('chart_of_accounts')
            ->where('code', $code)
            ->where(function ($query) use ($instituteId) {
                $instituteId === null
                    ? $query->whereNull('institute_id')
                    : $query->where('institute_id', $instituteId);
            })
            ->where(function ($query) use ($branchId) {
                $branchId === null
                    ? $query->whereNull('branch_id')
                    : $query->where('branch_id', $branchId);
            })
            ->exists();
    }
};

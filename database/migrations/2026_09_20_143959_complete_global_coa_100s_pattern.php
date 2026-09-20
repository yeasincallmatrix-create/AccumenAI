<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * Complete 100s-multiple pattern.
     *
     * Phase 1: Add 5 new anchor parents (1000, 2000, 3000, 4000, 5000)
     * Phase 2: Re-parent ALL children (existing anchor families + new)
     *
     * Total children linked: 31
     *   - New families: 25 (1001-1002, 2001-2003, 3001-3002, 4001-4005+4010, 5001-5012)
     *   - Existing anchor families: 6 (1201, 1301, 2101, 2102, 4901, 5901)
     *
     * Genuine childless anchors (left bare): 1100 (AR), 3100 (Revaluation Surplus)
     *
     * Zero code changes. Only parent_id updated. Fully reversible.
     */
    public function up(): void
    {
        $now = now();

        // Group map: type → global group ID (mirrors GlobalChartOfAccountsSeeder).
        // account_group_id is NOT NULL — every new anchor needs its family group.
        $groupMap = DB::table('account_groups')
            ->whereNull('institute_id')
            ->where('is_system', 1)
            ->pluck('id', 'category')
            ->toArray();

        // ============================================================
        // Phase 1: Add 5 new parent anchors (idempotent)
        // NOTE: chart_of_accounts has NO `category` column — `type`
        // (asset/liability/equity/income/expense) is the classifier.
        // ============================================================
        $newParents = [
            ['code' => '1000', 'name' => 'Cash',               'type' => 'asset'],
            ['code' => '2000', 'name' => 'Payables',           'type' => 'liability'],
            ['code' => '3000', 'name' => 'Equity',             'type' => 'equity'],
            ['code' => '4000', 'name' => 'Revenue',            'type' => 'income'],
            ['code' => '5000', 'name' => 'Operating Expenses', 'type' => 'expense'],
        ];

        foreach ($newParents as $parent) {
            $exists = DB::table('chart_of_accounts')
                ->whereNull('institute_id')
                ->where('code', $parent['code'])
                ->exists();

            if ($exists) {
                continue;
            }

            if (! isset($groupMap[$parent['type']])) {
                Log::warning("COA 100s-pattern migration: skipping {$parent['code']} — no global group for '{$parent['type']}'");
                continue;
            }

            DB::table('chart_of_accounts')->insert([
                'institute_id'     => null,
                'branch_id'        => null,
                'account_group_id' => $groupMap[$parent['type']],
                'code'             => $parent['code'],
                'name'             => $parent['name'],
                'type'             => $parent['type'],
                'is_cash'          => 0,
                'is_bank'          => 0,
                'is_receivable'    => 0,
                'is_payable'       => 0,
                'is_system'        => 1,
                'is_active'        => 1,
                'parent_id'        => null,
                'created_at'       => $now,
                'updated_at'       => $now,
            ]);
        }

        // ============================================================
        // Phase 2: Re-parent ALL children (extended map)
        //
        // Covers:
        // - New anchor families (1000, 2000, 3000, 4000, 5000)
        // - Existing anchor families (1200, 1300, 2100, 4900, 5900)
        //
        // Left bare (genuine childless anchors): 1100, 3100
        // ============================================================
        $reparentMap = [
            // NEW anchor families
            '1000' => ['1001', '1002'],
            '2000' => ['2001', '2002', '2003'],
            '3000' => ['3001', '3002'],
            '4000' => ['4001', '4002', '4003', '4004', '4005', '4010'],
            '5000' => ['5001', '5002', '5003', '5004', '5005', '5006',
                '5007', '5008', '5009', '5010', '5011', '5012'],

            // EXISTING anchor families (extended per Finding #2)
            '1200' => ['1201'],
            '1300' => ['1301'],
            '2100' => ['2101', '2102'],
            '4900' => ['4901'],
            '5900' => ['5901'],
        ];

        $linkedCount = 0;
        $skippedCount = 0;

        foreach ($reparentMap as $parentCode => $childCodes) {
            $parentId = DB::table('chart_of_accounts')
                ->whereNull('institute_id')
                ->where('code', $parentCode)
                ->value('id');

            if (! $parentId) {
                $skippedCount += count($childCodes);
                continue;
            }

            $updated = DB::table('chart_of_accounts')
                ->whereNull('institute_id')
                ->whereIn('code', $childCodes)
                ->whereNull('parent_id')
                ->update([
                    'parent_id'  => $parentId,
                    'updated_at' => $now,
                ]);

            $linkedCount += $updated;
            $skippedCount += (count($childCodes) - $updated);
        }

        Log::info("COA 100s-pattern migration: {$linkedCount} children linked, {$skippedCount} skipped");
    }

    public function down(): void
    {
        $now = now();

        // Revert Phase 2: unlink ALL children we linked
        $reparentMap = [
            '1000' => ['1001', '1002'],
            '2000' => ['2001', '2002', '2003'],
            '3000' => ['3001', '3002'],
            '4000' => ['4001', '4002', '4003', '4004', '4005', '4010'],
            '5000' => ['5001', '5002', '5003', '5004', '5005', '5006',
                '5007', '5008', '5009', '5010', '5011', '5012'],
            '1200' => ['1201'],
            '1300' => ['1301'],
            '2100' => ['2101', '2102'],
            '4900' => ['4901'],
            '5900' => ['5901'],
        ];

        foreach ($reparentMap as $parentCode => $childCodes) {
            $parentId = DB::table('chart_of_accounts')
                ->whereNull('institute_id')
                ->where('code', $parentCode)
                ->value('id');

            if ($parentId) {
                DB::table('chart_of_accounts')
                    ->whereNull('institute_id')
                    ->whereIn('code', $childCodes)
                    ->where('parent_id', $parentId)
                    ->update(['parent_id' => null, 'updated_at' => $now]);
            }
        }

        // Revert Phase 1: delete new parents (only if no children linked)
        DB::table('chart_of_accounts')
            ->whereNull('institute_id')
            ->whereIn('code', ['1000', '2000', '3000', '4000', '5000'])
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('chart_of_accounts as children')
                    ->whereColumn('children.parent_id', 'chart_of_accounts.id');
            })
            ->delete();
    }
};

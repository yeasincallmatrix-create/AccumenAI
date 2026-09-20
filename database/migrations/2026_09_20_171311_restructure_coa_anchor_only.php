<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * COA Anchor-Only Restructure
     *
     * Final state:
     *   1000 Cash (posting anchor)
     *   1100 Bank (posting anchor, NEW)
     *   1200 AR (shifted from 1100)
     *   1300 Inventory (shifted from 1200)
     *   1400 Prepaid (NEW)
     *   1500 Fixed Assets (shifted from 1300)
     *
     * Removed: 1001 (Cash in Hand), 1002 (Bank Account)
     *
     * Code mapping (applied in Phase 1):
     *   1001 → 1000 (Cash)
     *   1002 → 1100 (Bank)
     *   1100 → 1200 (AR)
     *   1200 → 1300 (Inventory)
     *   1300 → 1500 (Fixed Assets)
     *
     * NOTE: flag columns (is_cash/is_bank/is_receivable/is_payable) are
     * NOT NULL — inserts set them explicitly. down() anchor delete is
     * guarded (only when childless), mirroring the 100s-pattern migration.
     */
    public function up(): void
    {
        $now = now();

        // Step 1: Delete Cash children
        $deleted = DB::table('chart_of_accounts')
            ->whereNull('institute_id')
            ->whereIn('code', ['1001', '1002'])
            ->delete();
        Log::info("COA anchor restructure: deleted {$deleted} cash children (1001/1002)");

        // Step 2: Temp-code dance (avoid unique conflicts)
        $tempDance = [
            ['1100', '99110'],
            ['1200', '99220'],
            ['1300', '99330'],
        ];
        foreach ($tempDance as [$from, $to]) {
            DB::table('chart_of_accounts')
                ->whereNull('institute_id')
                ->where('code', $from)
                ->update(['code' => $to, 'updated_at' => $now]);
        }

        // Step 3: Temp → final
        $finalPos = [
            ['99110', '1200'],
            ['99220', '1300'],
            ['99330', '1500'],
        ];
        foreach ($finalPos as [$from, $to]) {
            DB::table('chart_of_accounts')
                ->whereNull('institute_id')
                ->where('code', $from)
                ->update(['code' => $to, 'updated_at' => $now]);
        }

        // Step 4: Add 1100 Bank anchor
        if (! DB::table('chart_of_accounts')->whereNull('institute_id')->where('code', '1100')->exists()) {
            $assetGroupId = DB::table('account_groups')
                ->whereNull('institute_id')
                ->where('category', 'asset')
                ->value('id');

            DB::table('chart_of_accounts')->insert([
                'code' => '1100',
                'name' => 'Bank',
                'type' => 'asset',
                'account_group_id' => $assetGroupId,
                'institute_id' => null,
                'branch_id' => null,
                'is_cash' => 0,
                'is_bank' => 1,
                'is_receivable' => 0,
                'is_payable' => 0,
                'is_system' => 1,
                'is_active' => 1,
                'parent_id' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // Step 5: Add 1400 Prepaid anchor
        if (! DB::table('chart_of_accounts')->whereNull('institute_id')->where('code', '1400')->exists()) {
            $assetGroupId = DB::table('account_groups')
                ->whereNull('institute_id')
                ->where('category', 'asset')
                ->value('id');

            DB::table('chart_of_accounts')->insert([
                'code' => '1400',
                'name' => 'Prepaid Expenses',
                'type' => 'asset',
                'account_group_id' => $assetGroupId,
                'institute_id' => null,
                'branch_id' => null,
                'is_cash' => 0,
                'is_bank' => 0,
                'is_receivable' => 0,
                'is_payable' => 0,
                'is_system' => 1,
                'is_active' => 1,
                'parent_id' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        $now = now();

        // Delete new anchors (only when childless — never orphan subs)
        DB::table('chart_of_accounts')
            ->whereNull('institute_id')
            ->whereIn('code', ['1100', '1400'])
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('chart_of_accounts as children')
                    ->whereColumn('children.parent_id', 'chart_of_accounts.id');
            })
            ->delete();

        // Reverse temp dance
        DB::table('chart_of_accounts')->whereNull('institute_id')->where('code', '1200')->update(['code' => '99110', 'updated_at' => $now]);
        DB::table('chart_of_accounts')->whereNull('institute_id')->where('code', '1300')->update(['code' => '99220', 'updated_at' => $now]);
        DB::table('chart_of_accounts')->whereNull('institute_id')->where('code', '1500')->update(['code' => '99330', 'updated_at' => $now]);

        DB::table('chart_of_accounts')->whereNull('institute_id')->where('code', '99110')->update(['code' => '1100', 'updated_at' => $now]);
        DB::table('chart_of_accounts')->whereNull('institute_id')->where('code', '99220')->update(['code' => '1200', 'updated_at' => $now]);
        DB::table('chart_of_accounts')->whereNull('institute_id')->where('code', '99330')->update(['code' => '1300', 'updated_at' => $now]);

        // Note: 1001, 1002 cannot be restored from down() — restore from backup if needed
    }
};

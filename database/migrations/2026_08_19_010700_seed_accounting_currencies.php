<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Global Accounting Engine — Step 1: seed the global currency catalog.
 *
 * Idempotent: only inserts missing ISO codes. is_base is left false for all;
 * each institute pins its own base currency through accounting_settings.
 */
return new class extends Migration
{
    private const CURRENCIES = [
        ['code' => 'BDT', 'name' => 'Bangladeshi Taka', 'symbol' => '৳', 'decimal_places' => 2],
        ['code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$', 'decimal_places' => 2],
        ['code' => 'INR', 'name' => 'Indian Rupee', 'symbol' => '₹', 'decimal_places' => 2],
        ['code' => 'PKR', 'name' => 'Pakistani Rupee', 'symbol' => '₨', 'decimal_places' => 2],
        ['code' => 'EUR', 'name' => 'Euro', 'symbol' => '€', 'decimal_places' => 2],
    ];

    public function up(): void
    {
        $existing = DB::table('currencies')->pluck('code')->all();

        $rows = [];
        foreach (self::CURRENCIES as $currency) {
            if (in_array($currency['code'], $existing, true)) {
                continue;
            }
            $rows[] = $currency + [
                'is_base' => false,
                'is_active' => true,
                'created_at' => now()->toDateTimeString(),
                'updated_at' => now()->toDateTimeString(),
            ];
        }

        if ($rows !== []) {
            DB::table('currencies')->insert($rows);
        }
    }

    public function down(): void
    {
        DB::table('currencies')
            ->whereIn('code', array_column(self::CURRENCIES, 'code'))
            ->delete();
    }
};

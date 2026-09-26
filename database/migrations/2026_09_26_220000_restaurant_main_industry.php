<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Restaurant becomes a MAIN INDUSTRY (Phase 2). The legacy
     * `retail.restaurant` sub-category is deprecated (not deleted) so any
     * existing retail institute still resolves its stored sub-category row,
     * but it disappears from the retail sub-category picker.
     *
     * Idempotent: a re-run only flips the same single row.
     */
    public function up(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('industry_subcategories')) {
            return;
        }

        $deactivated = DB::table('industry_subcategories')
            ->where('industry_key', 'retail')
            ->where('subcategory_key', 'restaurant')
            ->where('is_active', 1)
            ->update([
                'is_active'  => 0,
                'updated_at' => now(),
            ]);

        echo "Deprecated retail.restaurant sub-category ({$deactivated} row(s)).\n";
    }

    public function down(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('industry_subcategories')) {
            return;
        }

        DB::table('industry_subcategories')
            ->where('industry_key', 'retail')
            ->where('subcategory_key', 'restaurant')
            ->update([
                'is_active'  => 1,
                'updated_at' => now(),
            ]);
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Tag industry-specific global accounts. Universal accounts keep
     * industries NULL (visible to every tenant).
     */
    public function up(): void
    {
        $tags = [
            '4001' => ['education', 'training_center'],
            '4002' => ['education', 'training_center'],
        ];

        foreach ($tags as $code => $industries) {
            DB::table('chart_of_accounts')
                ->whereNull('institute_id')
                ->where('code', $code)
                ->update([
                    'industries' => json_encode($industries),
                    'updated_at' => now(),
                ]);
        }
    }

    public function down(): void
    {
        DB::table('chart_of_accounts')
            ->whereNull('institute_id')
            ->update(['industries' => null]);
    }
};

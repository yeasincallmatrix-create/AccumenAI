<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('module_registry')
            ->whereIn('key', ['medical.radiology', 'medical.bloodbank'])
            ->where('status', 'active')
            ->where('coming_soon', 1)
            ->update(['coming_soon' => 0]);
    }

    public function down(): void
    {
        // intentionally no-op — coming_soon is a state flag,
        // not a reversible schema change
    }
};

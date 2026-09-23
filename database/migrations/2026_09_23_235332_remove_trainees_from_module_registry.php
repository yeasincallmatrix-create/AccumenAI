<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('module_registry')) {
            DB::table('module_registry')
                ->where('key', 'training_center.trainees')
                ->delete();
        }
        if (Schema::hasTable('feature_registry')) {
            DB::table('feature_registry')
                ->where('feature_key', 'training_center.trainees')
                ->delete();
        }
    }

    public function down(): void
    {
        // No-op: deprecated naming
    }
};

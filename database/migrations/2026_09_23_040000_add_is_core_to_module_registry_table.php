<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('module_registry', 'is_core')) {
            Schema::table('module_registry', function (Blueprint $table) {
                $table->boolean('is_core')->default(false)->after('type');
                $table->index('is_core');
            });
        }

        // Backfill: type='core' → is_core=true
        DB::table('module_registry')
            ->where('type', 'core')
            ->update(['is_core' => true]);

        // Also mark core modules from config
        $coreModules = config('industry-modules.core', []);
        if (! empty($coreModules)) {
            DB::table('module_registry')
                ->whereIn('key', $coreModules)
                ->update(['is_core' => true]);
        }
    }

    public function down(): void
    {
        Schema::table('module_registry', function (Blueprint $table) {
            $table->dropIndex(['is_core']);
            $table->dropColumn('is_core');
        });
    }
};

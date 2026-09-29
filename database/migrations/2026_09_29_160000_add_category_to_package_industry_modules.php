<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-package-industry module categories (Mandatory/Default/Optional/
     * Hidden), mirroring the module-config matrix semantics.
     *
     * Backfill preserves effective behavior: enabled=true → 'default',
     * enabled=false → 'hidden'.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('package_industry_modules', 'category')) {
            Schema::table('package_industry_modules', function (Blueprint $table) {
                $table->enum('category', ['mandatory', 'default', 'optional', 'hidden'])
                    ->nullable()
                    ->after('enabled');
            });
        }

        DB::table('package_industry_modules')
            ->whereNull('category')
            ->where('enabled', true)
            ->update(['category' => 'default']);

        DB::table('package_industry_modules')
            ->whereNull('category')
            ->where('enabled', false)
            ->update(['category' => 'hidden']);
    }

    public function down(): void
    {
        // NO destructive rollback per project rules.
    }
};

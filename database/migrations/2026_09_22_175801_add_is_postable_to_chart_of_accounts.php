<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('chart_of_accounts', 'is_postable')) {
            Schema::table('chart_of_accounts', function (Blueprint $table) {
                $table->boolean('is_postable')->default(true)->after('is_active');
            });
        }
        if (!Schema::hasColumn('chart_of_accounts', 'is_header')) {
            Schema::table('chart_of_accounts', function (Blueprint $table) {
                $table->boolean('is_header')->default(false)->after('is_postable');
            });
        }

        DB::statement("
            UPDATE chart_of_accounts c
            SET is_header = 1, is_postable = 0
            WHERE EXISTS (
                SELECT 1 FROM chart_of_accounts p WHERE p.parent_id = c.id
            )
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('chart_of_accounts', function (Blueprint $table) {
            if (Schema::hasColumn('chart_of_accounts', 'is_postable')) {
                $table->dropColumn('is_postable');
            }
            if (Schema::hasColumn('chart_of_accounts', 'is_header')) {
                $table->dropColumn('is_header');
            }
        });
    }
};

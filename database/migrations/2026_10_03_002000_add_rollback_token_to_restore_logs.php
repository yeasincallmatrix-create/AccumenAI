<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('restore_logs', function (Blueprint $table) {
            if (!Schema::hasColumn('restore_logs', 'rollback_token')) {
                $table->string('rollback_token', 64)->nullable()->after('rollback_path');
                $table->index('rollback_token');
            }
        });
    }

    public function down(): void
    {
        Schema::table('restore_logs', function (Blueprint $table) {
            if (Schema::hasColumn('restore_logs', 'rollback_token')) {
                $table->dropIndex(['rollback_token']);
                $table->dropColumn('rollback_token');
            }
        });
    }
};

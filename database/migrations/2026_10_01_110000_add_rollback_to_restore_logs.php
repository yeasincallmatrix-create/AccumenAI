<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('restore_logs', function (Blueprint $table) {
            if (!Schema::hasColumn('restore_logs', 'rollback_expires_at')) {
                $table->timestamp('rollback_expires_at')->nullable()->after('rollback_path');
            }
        });
    }

    public function down(): void
    {
        Schema::table('restore_logs', function (Blueprint $table) {
            $table->dropColumn('rollback_expires_at');
        });
    }
};

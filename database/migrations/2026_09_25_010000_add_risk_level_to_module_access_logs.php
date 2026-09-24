<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('module_access_logs') && ! Schema::hasColumn('module_access_logs', 'risk_level')) {
            Schema::table('module_access_logs', function (Blueprint $table) {
                $table->enum('risk_level', ['low', 'medium', 'high', 'critical'])
                    ->default('low')
                    ->after('action');
                $table->index('risk_level');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('module_access_logs') && Schema::hasColumn('module_access_logs', 'risk_level')) {
            Schema::table('module_access_logs', function (Blueprint $table) {
                $table->dropIndex(['risk_level']);
                $table->dropColumn('risk_level');
            });
        }
    }
};

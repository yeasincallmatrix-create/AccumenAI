<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('institutes') && ! Schema::hasColumn('institutes', 'rule_overrides')) {
            Schema::table('institutes', function (Blueprint $table) {
                $table->json('rule_overrides')->nullable()->after('terminology_overrides');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('institutes') && Schema::hasColumn('institutes', 'rule_overrides')) {
            Schema::table('institutes', function (Blueprint $table) {
                $table->dropColumn('rule_overrides');
            });
        }
    }
};

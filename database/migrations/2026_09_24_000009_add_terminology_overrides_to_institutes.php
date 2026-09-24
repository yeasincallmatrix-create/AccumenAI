<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('institutes') && ! Schema::hasColumn('institutes', 'terminology_overrides')) {
            Schema::table('institutes', function (Blueprint $table) {
                $table->json('terminology_overrides')->nullable()->after('subcategory_key');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('institutes') && Schema::hasColumn('institutes', 'terminology_overrides')) {
            Schema::table('institutes', function (Blueprint $table) {
                $table->dropColumn('terminology_overrides');
            });
        }
    }
};

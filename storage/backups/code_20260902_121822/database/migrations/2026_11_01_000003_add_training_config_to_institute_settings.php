<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('institute_settings') && !Schema::hasColumn('institute_settings', 'training_config')) {
            Schema::table('institute_settings', function (Blueprint $table) {
                $table->json('training_config')->nullable()->after('ai_config');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('institute_settings') && Schema::hasColumn('institute_settings', 'training_config')) {
            Schema::table('institute_settings', function (Blueprint $table) {
                $table->dropColumn('training_config');
            });
        }
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('module_access_logs', function (Blueprint $table) {
            $table->string('actor_type', 30)->nullable()->after('actor_id')
                ->comment('Realm: platform_admin|institute_user|user|guardian|platform_staff|system');
        });
    }

    public function down(): void
    {
        Schema::table('module_access_logs', function (Blueprint $table) {
            $table->dropColumn('actor_type');
        });
    }
};

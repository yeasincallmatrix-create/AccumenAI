<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users','inactivity_warning_sent_at')) {
                $table->timestamp('inactivity_warning_sent_at')->nullable()->after('last_login_at');
            }
            if (!Schema::hasColumn('users','inactivity_final_warning_sent_at')) {
                $table->timestamp('inactivity_final_warning_sent_at')->nullable()->after('inactivity_warning_sent_at');
            }
            if (!Schema::hasColumn('users','inactivity_deleted_at')) {
                $table->timestamp('inactivity_deleted_at')->nullable()->after('inactivity_final_warning_sent_at');
            }
        });
        try {
            Schema::table('users', function (Blueprint $table) {
                $table->index('last_login_at');
                $table->index('inactivity_warning_sent_at');
            });
        } catch (\Throwable $e) {}
        // Guardian and InstituteUser also have last_login_at — add warning cols for completeness
        foreach (['guardians','institute_users','platform_admins'] as $t) {
            if (Schema::hasTable($t)) {
                Schema::table($t, function (Blueprint $table) use ($t) {
                    if (!Schema::hasColumn($t,'inactivity_warning_sent_at')) $table->timestamp('inactivity_warning_sent_at')->nullable()->after('last_login_at');
                });
            }
        }
    }
    public function down(): void {
        Schema::table('users', function (Blueprint $table) {
            $cols = [];
            foreach (['inactivity_warning_sent_at','inactivity_final_warning_sent_at','inactivity_deleted_at'] as $c) if (Schema::hasColumn('users',$c)) $cols[]=$c;
            if ($cols) $table->dropColumn($cols);
        });
    }
};

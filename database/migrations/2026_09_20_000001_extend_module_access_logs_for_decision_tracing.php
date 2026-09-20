<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('module_access_logs', function (Blueprint $table) {
            $table->string('reason', 60)->nullable()->after('notes')
                ->comment('Structured decision reason code (e.g. PACKAGE_FEATURE_NOT_ENTITLED)');
            $table->string('feature_key', 100)->nullable()->after('reason')
                ->comment('Feature key for feature-level decisions (e.g. medical.pharmacy)');
            $table->string('decision', 10)->nullable()->after('feature_key')
                ->comment('Decision outcome: allow or deny');
            $table->string('request_id', 36)->nullable()->after('decision')
                ->comment('HTTP request correlation ID');
        });
    }

    public function down(): void
    {
        Schema::table('module_access_logs', function (Blueprint $table) {
            $table->dropColumn(['reason', 'feature_key', 'decision', 'request_id']);
        });
    }
};

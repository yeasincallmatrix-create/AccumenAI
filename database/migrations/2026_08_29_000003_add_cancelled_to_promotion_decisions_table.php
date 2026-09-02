<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P2-5 — Add Promotion Rollback (Cancel) support.
 * Adds cancelled_by / cancelled_at to promotion_decisions and ensures
 * status can be 'cancelled' (pending/review → cancelled, terminal).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('promotion_decisions')) {
            Schema::table('promotion_decisions', function (Blueprint $table) {
                if (! Schema::hasColumn('promotion_decisions', 'cancelled_by')) {
                    $table->foreignId('cancelled_by')->nullable()->after('approved_at')->constrained('institute_users')->nullOnDelete();
                }
                if (! Schema::hasColumn('promotion_decisions', 'cancelled_at')) {
                    $table->timestamp('cancelled_at')->nullable()->after('cancelled_by');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('promotion_decisions')) {
            Schema::table('promotion_decisions', function (Blueprint $table) {
                if (Schema::hasColumn('promotion_decisions', 'cancelled_at')) {
                    $table->dropColumn('cancelled_at');
                }
                if (Schema::hasColumn('promotion_decisions', 'cancelled_by')) {
                    $table->dropForeign(['cancelled_by']);
                    $table->dropColumn('cancelled_by');
                }
            });
        }
    }
};

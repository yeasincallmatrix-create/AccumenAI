<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Track who received the visit fee so the Audit Log can show it.
     *
     * - appointments: amount + receiver snapshot + timestamp of collection.
     * - queue_audit_logs: action discriminator ('reorder' | 'fee_collected')
     *   plus the collected amount, so fee collections appear in the same
     *   Audit Log feed as reorders.
     */
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->decimal('fee_collected_amount', 10, 2)->nullable()->after('fee_applied');
            $table->unsignedBigInteger('fee_collected_by_id')->nullable()->after('fee_collected_amount');
            $table->string('fee_collected_by_name', 150)->nullable()->after('fee_collected_by_id');
            $table->timestamp('fee_collected_at')->nullable()->after('fee_collected_by_name');
        });

        Schema::table('queue_audit_logs', function (Blueprint $table) {
            $table->string('action', 20)->default('reorder')->after('actor_name');
            $table->decimal('amount', 10, 2)->nullable()->after('new_order');
        });

        // Existing rows are all reorders (the only action logged so far).
        DB::table('queue_audit_logs')->whereNull('action')->orWhere('action', '')->update(['action' => 'reorder']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('queue_audit_logs', function (Blueprint $table) {
            $table->dropColumn(['action', 'amount']);
        });

        Schema::table('appointments', function (Blueprint $table) {
            $table->dropColumn([
                'fee_collected_amount',
                'fee_collected_by_id',
                'fee_collected_by_name',
                'fee_collected_at',
            ]);
        });
    }
};

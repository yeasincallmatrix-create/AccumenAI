<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            if (!Schema::hasColumn('invoices', 'progressive_contract_id')) {
                $table->unsignedBigInteger('progressive_contract_id')->nullable()->after('id');
                $table->foreign('progressive_contract_id')
                    ->references('id')->on('progressive_contracts')
                    ->onDelete('set null');
            }
            if (!Schema::hasColumn('invoices', 'is_progressive')) {
                $table->boolean('is_progressive')->default(false)->after('progressive_contract_id');
            }
            if (!Schema::hasColumn('invoices', 'is_final_progressive')) {
                $table->boolean('is_final_progressive')->default(false)->after('is_progressive');
            }
            if (!Schema::hasColumn('invoices', 'milestone_name')) {
                $table->string('milestone_name', 200)->nullable()->after('is_final_progressive');
            }
            if (!Schema::hasColumn('invoices', 'progress_percentage')) {
                $table->decimal('progress_percentage', 5, 2)->nullable()->after('milestone_name');
            }
            if (!Schema::hasColumn('invoices', 'cumulative_billed')) {
                $table->decimal('cumulative_billed', 15, 2)->default(0)->after('progress_percentage');
            }
            if (!Schema::hasColumn('invoices', 'retention_amount')) {
                $table->decimal('retention_amount', 15, 2)->default(0)->after('cumulative_billed');
            }
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            foreach ([
                'progressive_contract_id', 'is_progressive', 'is_final_progressive',
                'milestone_name', 'progress_percentage', 'cumulative_billed', 'retention_amount',
            ] as $col) {
                if (Schema::hasColumn('invoices', $col)) {
                    if (in_array($col, ['progressive_contract_id'])) {
                        $table->dropForeign([$col]);
                    }
                    $table->dropColumn($col);
                }
            }
        });
    }
};

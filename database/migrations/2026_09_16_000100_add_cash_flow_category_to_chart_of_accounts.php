<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chart_of_accounts', function (Blueprint $table) {
            if (! Schema::hasColumn('chart_of_accounts', 'cash_flow_category')) {
                $table->enum('cash_flow_category', ['operating', 'investing', 'financing'])
                    ->nullable()
                    ->default(null)
                    ->after('type');
            }
        });

        Schema::table('chart_of_accounts', function (Blueprint $table) {
            if (! Schema::hasIndex('chart_of_accounts', 'idx_coa_cash_flow_category')) {
                $table->index(['institute_id', 'cash_flow_category'], 'idx_coa_cash_flow_category');
            }
        });
    }

    public function down(): void
    {
        Schema::table('chart_of_accounts', function (Blueprint $table) {
            if (Schema::hasIndex('chart_of_accounts', 'idx_coa_cash_flow_category')) {
                $table->dropIndex('idx_coa_cash_flow_category');
            }
        });

        Schema::table('chart_of_accounts', function (Blueprint $table) {
            if (Schema::hasColumn('chart_of_accounts', 'cash_flow_category')) {
                $table->dropColumn('cash_flow_category');
            }
        });
    }
};

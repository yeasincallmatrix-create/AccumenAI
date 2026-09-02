<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fee_structures', function (Blueprint $table) {
            $table->enum('billing_frequency', ['monthly', 'quarterly', 'annually', 'one_time'])
                ->default('monthly')
                ->after('status');
            $table->boolean('auto_generate_monthly')->default(false)->after('billing_frequency');
        });
    }

    public function down(): void
    {
        Schema::table('fee_structures', function (Blueprint $table) {
            $table->dropColumn(['billing_frequency', 'auto_generate_monthly']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fee_heads', function (Blueprint $table) {
            $table->boolean('is_recurring')->default(false)->after('is_active');
            $table->enum('billing_frequency', ['monthly', 'quarterly', 'annually', 'one_time'])
                ->default('one_time')
                ->after('is_recurring');
            $table->integer('sort_order')->default(0)->after('billing_frequency');
        });
    }

    public function down(): void
    {
        Schema::table('fee_heads', function (Blueprint $table) {
            $table->dropColumn(['is_recurring', 'billing_frequency', 'sort_order']);
        });
    }
};

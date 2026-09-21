<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            if (!Schema::hasColumn('invoice_items', 'reference_type')) {
                $table->string('reference_type')->nullable()->after('fee_head_id');
                $table->unsignedBigInteger('reference_id')->nullable()->after('reference_type');
            }
        });
    }

    public function down(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->dropColumn(['reference_type', 'reference_id']);
        });
    }
};

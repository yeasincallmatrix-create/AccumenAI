<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('medical_doctors', function (Blueprint $table) {
            $table->boolean('collect_fee_before_visit')->default(false)->after('follow_up_days');
        });
    }

    public function down(): void
    {
        Schema::table('medical_doctors', function (Blueprint $table) {
            $table->dropColumn('collect_fee_before_visit');
        });
    }
};

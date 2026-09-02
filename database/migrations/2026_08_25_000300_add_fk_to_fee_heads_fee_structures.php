<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fee_heads', function ($table) {
            $table->foreign('created_by')->references('id')->on('institute_users')->nullOnDelete();
            $table->foreign('updated_by')->references('id')->on('institute_users')->nullOnDelete();
        });

        Schema::table('fee_structures', function ($table) {
            $table->foreign('created_by')->references('id')->on('institute_users')->nullOnDelete();
            $table->foreign('updated_by')->references('id')->on('institute_users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('fee_heads', function ($table) {
            $table->dropForeign(['created_by']);
            $table->dropForeign(['updated_by']);
        });

        Schema::table('fee_structures', function ($table) {
            $table->dropForeign(['created_by']);
            $table->dropForeign(['updated_by']);
        });
    }
};

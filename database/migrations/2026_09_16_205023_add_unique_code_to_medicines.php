<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('medicines', function (Blueprint $table) {
            $table->dropUnique('medicines_code_unique');
            $table->string('code', 50)->nullable()->change();
            // Unique per institute per code, but allows same code for
            // multiple soft-deleted rows (different deleted_at values).
            $table->unique(
                ['institute_id', 'code', 'deleted_at'],
                'uniq_medicine_code_per_institute_deleted'
            );
        });
    }

    public function down(): void
    {
        Schema::table('medicines', function (Blueprint $table) {
            $table->dropUnique('uniq_medicine_code_per_institute_deleted');
            $table->string('code', 50)->nullable(false)->change();
            if (! Schema::hasIndex('medicines', 'medicines_code_unique')) {
                $table->unique('code', 'medicines_code_unique');
            }
        });
    }
};

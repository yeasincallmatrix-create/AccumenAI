<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('training_exams') && ! Schema::hasColumn('training_exams', 'weight_percent')) {
            Schema::table('training_exams', function (Blueprint $table) {
                // Weight of this exam toward the batch's weighted final result
                // (1st exam + nth exam weighting); unrelated to written/practical/viva.
                $table->decimal('weight_percent', 5, 2)->nullable()->after('viva_percent');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('training_exams') && Schema::hasColumn('training_exams', 'weight_percent')) {
            Schema::table('training_exams', function (Blueprint $table) {
                $table->dropColumn('weight_percent');
            });
        }
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('training_schedules', function (Blueprint $table) {
            $table->date('effective_from')->nullable()->after('is_test');
            $table->date('effective_to')->nullable()->after('effective_from');
            $table->index(['batch_id', 'effective_to'], 'training_schedules_batch_effective_index');
        });

        // Existing rows keep NULL bounds = the historical baseline: active for every
        // date up to the moment a change is made (which end-dates them at that point).
    }

    public function down(): void
    {
        Schema::table('training_schedules', function (Blueprint $table) {
            $table->dropIndex('training_schedules_batch_effective_index');
            $table->dropColumn(['effective_from', 'effective_to']);
        });
    }
};

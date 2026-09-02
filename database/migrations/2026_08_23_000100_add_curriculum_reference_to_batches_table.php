<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Link batches to a curriculum version (Step 42).
 *
 * Batches retain their historical curriculum version (SET NULL is only ever
 * reached when a curriculum row is hard-deleted, which the service layer
 * prevents while batches reference it). Changing the current active
 * curriculum never rewrites existing batches.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('batches', function (Blueprint $table) {
            $table->foreignId('curriculum_id')
                ->nullable()
                ->after('course_id')
                ->constrained('course_curricula')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('batches', function (Blueprint $table) {
            $table->dropConstrainedForeignId('curriculum_id');
        });
    }
};

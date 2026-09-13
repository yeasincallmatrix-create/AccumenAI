<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reusable academic assessment types ("Mid Term", "Final", "First Term", ...).
 *
 * This is a GLOBAL master (like the subjects master): rows with institute_id
 * NULL are system defaults shared by every institute; a row with an
 * institute_id is that institute's own custom type/override. The schema leaves
 * room for a future country default layer via country_id.
 *
 * Deliberately NOT the training exam system's `exam_types`, which is actually a
 * component master (Written / Practical / Viva) used by the batch/course exams.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('assessment_types')) {
            Schema::create('assessment_types', function (Blueprint $table) {
                $table->id();
                $table->foreignId('institute_id')->nullable()->constrained('institutes')->cascadeOnDelete();
                $table->foreignId('country_id')->nullable()->constrained('countries')->nullOnDelete();
                $table->string('name', 120);
                $table->string('slug', 120);
                $table->string('description', 255)->nullable();
                $table->unsignedInteger('display_order')->default(0);
                $table->boolean('status')->default(true);
                $table->timestamps();

                $table->unique(['institute_id', 'slug']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_types');
    }
};

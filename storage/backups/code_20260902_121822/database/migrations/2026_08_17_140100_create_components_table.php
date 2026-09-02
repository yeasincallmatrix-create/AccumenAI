<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reusable component master ("Written", "MCQ", "Practical", "Viva", ...).
 *
 * The same component is reused across subjects and assessments; each
 * assessment-subject row configures its own full/pass marks via
 * assessment_subject_components. Global rows have institute_id NULL; an
 * institute can add its own components with an institute_id.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('components')) {
            Schema::create('components', function (Blueprint $table) {
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
        Schema::dropIfExists('components');
    }
};

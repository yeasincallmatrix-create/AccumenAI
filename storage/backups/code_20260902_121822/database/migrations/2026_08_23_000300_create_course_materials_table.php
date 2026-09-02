<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Course material / attachment store (Step 42).
 *
 * Reuses the application's public disk + upload validation; no second file
 * storage system is introduced. Materials are tenant-scoped and can be
 * attached to a curriculum version or a specific module.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_materials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institute_id')->constrained('institutes')->cascadeOnDelete();
            $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
            $table->foreignId('curriculum_module_id')
                ->nullable()
                ->constrained('curriculum_modules')
                ->nullOnDelete();
            $table->string('title', 200);
            $table->string('file_path', 500);
            $table->string('file_type', 50)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->unsignedInteger('display_order')->default(0);
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->foreignId('uploaded_by')->nullable()->constrained('institute_users')->nullOnDelete();
            $table->timestamps();

            $table->index(['course_id'], 'idx_course_materials_course');
            $table->index(['curriculum_module_id'], 'idx_course_materials_module');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_materials');
    }
};

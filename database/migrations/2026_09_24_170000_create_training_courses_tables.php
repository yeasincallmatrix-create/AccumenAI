<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('training_course_categories')) {
            Schema::create('training_course_categories', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('institute_id');
                $table->string('name', 100);
                $table->string('slug', 100);
                $table->string('status', 20)->default('active');
                $table->string('subject_type', 30)->default('professional');
                $table->timestamps();

                $table->unique(['institute_id', 'slug'], 'uq_tcc_inst_slug');
                $table->index('institute_id');
                $table->foreign('institute_id')->references('id')->on('institutes')->onDelete('cascade');
            });
        }

        if (! Schema::hasTable('training_course_sub_categories')) {
            Schema::create('training_course_sub_categories', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('institute_id');
                $table->unsignedBigInteger('category_id');
                $table->string('name', 100);
                $table->string('slug', 100);
                $table->string('status', 20)->default('active');
                $table->timestamps();

                $table->unique(['category_id', 'slug'], 'uq_tcsc_cat_slug');
                $table->index('institute_id');
                $table->foreign('institute_id')->references('id')->on('institutes')->onDelete('cascade');
                $table->foreign('category_id')->references('id')->on('training_course_categories')->onDelete('cascade');
            });
        }

        if (! Schema::hasTable('training_courses')) {
            Schema::create('training_courses', function (Blueprint $table) {
                $table->id();
                $table->boolean('is_test')->default(false);
                $table->unsignedBigInteger('institute_id')->nullable();
                $table->unsignedBigInteger('category_id')->nullable();
                $table->unsignedBigInteger('sub_category_id')->nullable();
                $table->string('course_code', 40);
                $table->string('name', 150);
                $table->string('slug', 200)->nullable();
                $table->string('short_name', 60)->nullable();
                $table->text('description')->nullable();
                $table->string('short_description', 500)->nullable();
                $table->longText('modules')->nullable();
                $table->enum('level', ['basic', 'intermediate', 'advanced'])->default('basic');
                $table->string('language', 30)->nullable();
                $table->enum('duration_type', ['hours', 'days', 'weeks', 'months', 'years'])->default('months');
                $table->decimal('duration_value', 6, 2)->default(0);
                $table->unsignedTinyInteger('weekly_classes')->nullable();
                $table->unsignedSmallInteger('class_duration_minutes')->nullable();
                $table->unsignedSmallInteger('total_classes')->nullable();
                $table->decimal('total_hours', 6, 2)->nullable();
                $table->enum('mode', ['offline', 'online', 'hybrid'])->default('offline');
                $table->unsignedSmallInteger('batch_capacity_default')->default(30);
                $table->decimal('fee', 10, 2)->default(0);
                $table->decimal('discount', 10, 2)->default(0);
                $table->decimal('admission_fee', 10, 2)->default(0);
                $table->decimal('exam_fee', 10, 2)->default(0);
                $table->decimal('certificate_fee', 10, 2)->default(0);
                $table->string('thumbnail', 255)->nullable();
                $table->string('banner', 255)->nullable();
                $table->string('intro_video', 500)->nullable();
                $table->boolean('is_featured')->default(false);
                $table->unsignedInteger('display_order')->default(0);
                $table->string('meta_title', 200)->nullable();
                $table->string('meta_description', 500)->nullable();
                $table->string('meta_keywords', 500)->nullable();
                $table->longText('requirements')->nullable();
                $table->longText('outcomes')->nullable();
                $table->longText('prerequisites')->nullable();
                $table->enum('status', ['active', 'inactive', 'draft'])->default('draft');
                $table->softDeletes();
                $table->timestamps();

                $table->index('institute_id');
                $table->index('slug');
                $table->index('course_code');
                $table->index('category_id');
                $table->foreign('institute_id')->references('id')->on('institutes')->nullOnDelete();
                $table->foreign('category_id')->references('id')->on('training_course_categories')->nullOnDelete();
                $table->foreign('sub_category_id')->references('id')->on('training_course_sub_categories')->nullOnDelete();
            });
        }

        if (! Schema::hasTable('training_course_materials')) {
            Schema::create('training_course_materials', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('institute_id');
                $table->unsignedBigInteger('course_id');
                $table->unsignedBigInteger('curriculum_module_id')->nullable();
                $table->string('title', 200);
                $table->string('file_path', 500);
                $table->string('file_type', 50)->nullable();
                $table->unsignedBigInteger('file_size')->nullable();
                $table->unsignedInteger('display_order')->default(0);
                $table->string('status', 20)->default('active');
                $table->unsignedBigInteger('uploaded_by')->nullable();
                $table->timestamps();

                $table->index('institute_id');
                $table->index('course_id');
                $table->foreign('institute_id')->references('id')->on('institutes')->onDelete('cascade');
                $table->foreign('course_id')->references('id')->on('training_courses')->onDelete('cascade');
                $table->foreign('uploaded_by')->references('id')->on('institute_users')->nullOnDelete();
            });
        }

        if (! Schema::hasTable('training_course_subjects')) {
            Schema::create('training_course_subjects', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('course_id');
                $table->unsignedBigInteger('subject_id');
                $table->unsignedBigInteger('assigned_by')->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->unique(['course_id', 'subject_id'], 'uq_tcs_course_subject');
                $table->index('subject_id');
                $table->foreign('course_id')->references('id')->on('training_courses')->onDelete('cascade');
                $table->foreign('subject_id')->references('id')->on('training_subjects')->onDelete('cascade');
            });
        }

        // Extend existing training_subjects with category_id FK → training_course_categories
        if (Schema::hasTable('training_subjects') && ! Schema::hasColumn('training_subjects', 'category_id')) {
            Schema::table('training_subjects', function (Blueprint $table) {
                $table->unsignedBigInteger('category_id')->nullable()->after('institute_id');
                $table->index('category_id');
            });
        }

        if (Schema::hasTable('training_subjects')) {
            $hasFk = false;
            try {
                $fks = Schema::getForeignKeys('training_subjects');
                foreach ($fks as $fk) {
                    $cols = $fk['columns'] ?? [];
                    if (in_array('category_id', $cols, true) && (($fk['foreign_table'] ?? '') === 'training_course_categories')) {
                        $hasFk = true;
                        break;
                    }
                }
            } catch (\Throwable) {
                // getForeignKeys unavailable on older doctrine — skip FK add
            }

            if (! $hasFk) {
                try {
                    Schema::table('training_subjects', function (Blueprint $table) {
                        $table->foreign('category_id')->references('id')->on('training_course_categories')->nullOnDelete();
                    });
                } catch (\Throwable) {
                    // FK already present or engine mismatch — column is enough
                }
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('training_subjects') && Schema::hasColumn('training_subjects', 'category_id')) {
            try {
                Schema::table('training_subjects', function (Blueprint $table) {
                    $table->dropForeign(['category_id']);
                });
            } catch (\Throwable) {
            }
            Schema::table('training_subjects', function (Blueprint $table) {
                $table->dropColumn('category_id');
            });
        }

        Schema::dropIfExists('training_course_subjects');
        Schema::dropIfExists('training_course_materials');
        Schema::dropIfExists('training_courses');
        Schema::dropIfExists('training_course_sub_categories');
        Schema::dropIfExists('training_course_categories');
    }
};

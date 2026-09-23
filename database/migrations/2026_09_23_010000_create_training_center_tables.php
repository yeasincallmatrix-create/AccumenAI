<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('training_students')) {
            Schema::create('training_students', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('institute_id');
                $table->unsignedBigInteger('branch_id')->nullable();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('reg_no', 50)->nullable();
                $table->string('student_id', 50)->nullable();
                $table->boolean('is_test')->default(false);
                $table->string('first_name', 100)->nullable();
                $table->string('last_name', 100)->nullable();
                $table->uuid('uuid')->nullable();
                $table->string('student_id_number', 50)->nullable();
                $table->string('application_number', 50)->nullable();
                $table->date('application_date')->nullable();
                $table->string('admission_status', 30)->nullable();
                $table->string('admission_source', 50)->nullable();
                $table->string('admission_reject_reason', 500)->nullable();
                $table->unsignedBigInteger('applied_course_id')->nullable();
                $table->unsignedBigInteger('applied_academic_year_id')->nullable();
                $table->unsignedBigInteger('preferred_batch_id')->nullable();
                $table->unsignedBigInteger('admission_assigned_user_id')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('approved_by')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->unsignedBigInteger('rejected_by')->nullable();
                $table->timestamp('rejected_at')->nullable();
                $table->string('roll_number', 50)->nullable();
                $table->string('photo', 500)->nullable();
                $table->string('document', 500)->nullable();
                $table->string('father_name', 150)->nullable();
                $table->string('mother_name', 150)->nullable();
                $table->string('gender', 20)->nullable();
                $table->date('dob')->nullable();
                $table->string('blood_group', 10)->nullable();
                $table->string('religion', 50)->nullable();
                $table->string('nationality', 50)->nullable();
                $table->string('nid_number', 50)->nullable();
                $table->string('birth_cert_number', 50)->nullable();
                $table->string('phone', 30)->nullable();
                $table->string('guardian_phone', 30)->nullable();
                $table->string('email', 150)->nullable();
                $table->string('country', 100)->nullable();
                $table->unsignedBigInteger('present_country_id')->nullable();
                $table->unsignedBigInteger('present_admin_1_id')->nullable();
                $table->unsignedBigInteger('present_admin_2_id')->nullable();
                $table->unsignedBigInteger('present_admin_3_id')->nullable();
                $table->string('present_address', 500)->nullable();
                $table->string('permanent_address', 500)->nullable();
                $table->string('present_post_office', 50)->nullable();
                $table->string('present_zip_code', 20)->nullable();
                $table->string('permanent_post_office', 50)->nullable();
                $table->string('permanent_zip_code', 20)->nullable();
                $table->unsignedBigInteger('permanent_country_id')->nullable();
                $table->unsignedBigInteger('permanent_admin_1_id')->nullable();
                $table->unsignedBigInteger('permanent_admin_2_id')->nullable();
                $table->unsignedBigInteger('permanent_admin_3_id')->nullable();
                $table->string('national_id_or_birth_certificate', 50)->nullable();
                $table->string('passport_number', 50)->nullable();
                $table->string('emergency_contact_name', 150)->nullable();
                $table->string('emergency_contact_phone', 30)->nullable();
                $table->date('admission_date')->nullable();
                $table->string('status', 30)->nullable();
                $table->unsignedBigInteger('crm_contact_id')->nullable();
                $table->unsignedBigInteger('crm_lead_id')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index('institute_id');
                $table->index('reg_no');
                $table->index('student_id');
                $table->index('uuid');
            });
        }

        if (! Schema::hasTable('training_batches')) {
            Schema::create('training_batches', function (Blueprint $table) {
                $table->id();
                $table->boolean('is_test')->default(false);
                $table->unsignedBigInteger('institute_id');
                $table->unsignedBigInteger('course_id')->nullable();
                $table->unsignedBigInteger('curriculum_id')->nullable();
                $table->unsignedBigInteger('academic_year_id')->nullable();
                $table->unsignedBigInteger('branch_id')->nullable();
                $table->unsignedBigInteger('teacher_id')->nullable();
                $table->unsignedBigInteger('room_id')->nullable();
                $table->string('name', 150)->nullable();
                $table->string('batch_code', 50)->nullable();
                $table->string('shift', 30)->nullable();
                $table->date('start_date')->nullable();
                $table->date('end_date')->nullable();
                $table->unsignedInteger('seat_capacity')->nullable();
                $table->unsignedInteger('seat_filled')->nullable();
                $table->string('status', 30)->nullable();
                $table->decimal('attendance_threshold', 5, 2)->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index('institute_id');
                $table->index('batch_code');
            });
        }

        if (! Schema::hasTable('training_enrollments')) {
            Schema::create('training_enrollments', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('institute_id');
                $table->unsignedBigInteger('batch_id')->nullable();
                $table->integer('roll_no')->nullable();
                $table->unsignedBigInteger('trainee_id')->nullable();
                $table->unsignedBigInteger('student_id');
                $table->date('enrollment_date')->nullable();
                $table->string('status', 30)->nullable();
                $table->string('payment_status', 30)->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index('institute_id');
                $table->index('batch_id');
                $table->index('student_id');
                $table->index('trainee_id');
            });
        }

        if (! Schema::hasTable('training_classes')) {
            Schema::create('training_classes', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('institute_id')->nullable();
                $table->unsignedBigInteger('country_id')->nullable();
                $table->unsignedBigInteger('education_system_id')->nullable();
                $table->unsignedBigInteger('academic_level_id')->nullable();
                $table->string('name', 150)->nullable();
                $table->string('code', 50)->nullable();
                $table->integer('sequence')->nullable();
                $table->integer('display_order')->nullable();
                $table->string('status', 30)->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index('institute_id');
                $table->index('code');
            });
        }

        if (! Schema::hasTable('training_subjects')) {
            Schema::create('training_subjects', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('institute_id')->nullable();
                $table->unsignedBigInteger('category_id')->nullable();
                $table->string('subject_type', 30)->nullable();
                $table->string('subject_code', 50)->nullable();
                $table->string('name', 150)->nullable();
                $table->string('slug', 180)->nullable();
                $table->string('short_name', 50)->nullable();
                $table->text('description')->nullable();
                $table->string('status', 30)->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index('institute_id');
                $table->index('slug');
                $table->index('subject_code');
            });
        }

        if (! Schema::hasTable('training_exams')) {
            Schema::create('training_exams', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('institute_id');
                $table->unsignedBigInteger('course_id')->nullable();
                $table->unsignedBigInteger('batch_id')->nullable();
                $table->string('title', 200)->nullable();
                $table->date('exam_date')->nullable();
                $table->decimal('full_marks', 8, 2)->nullable();
                $table->decimal('pass_marks', 8, 2)->nullable();
                $table->decimal('written_percent', 5, 2)->nullable();
                $table->decimal('practical_percent', 5, 2)->nullable();
                $table->decimal('viva_percent', 5, 2)->nullable();
                $table->string('status', 30)->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->boolean('is_test')->default(false);
                $table->timestamps();
                $table->softDeletes();

                $table->index('institute_id');
                $table->index('batch_id');
            });
        }

        if (! Schema::hasTable('training_exam_results')) {
            Schema::create('training_exam_results', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('institute_id');
                $table->unsignedBigInteger('exam_id');
                $table->unsignedBigInteger('student_id');
                $table->unsignedBigInteger('subject_id')->nullable();
                $table->decimal('marks_obtained', 8, 2)->nullable();
                $table->decimal('written_marks', 8, 2)->nullable();
                $table->decimal('practical_marks', 8, 2)->nullable();
                $table->decimal('viva_marks', 8, 2)->nullable();
                $table->decimal('other_marks', 8, 2)->nullable();
                $table->decimal('component_marks', 8, 2)->nullable();
                $table->decimal('attendance_marks', 8, 2)->nullable();
                $table->string('grade', 10)->nullable();
                $table->decimal('gpa', 5, 2)->nullable();
                $table->string('result_status', 30)->nullable();
                $table->text('remarks')->nullable();
                $table->unsignedBigInteger('entered_by')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index('institute_id');
                $table->index('exam_id');
                $table->index('student_id');
                $table->index('subject_id');
            });
        }

        if (! Schema::hasTable('training_certificates')) {
            Schema::create('training_certificates', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->nullable();
                $table->unsignedBigInteger('institute_id');
                $table->unsignedBigInteger('student_id');
                $table->unsignedBigInteger('course_id')->nullable();
                $table->unsignedBigInteger('batch_id')->nullable();
                $table->unsignedBigInteger('certificate_type_id')->nullable();
                $table->unsignedBigInteger('template_id')->nullable();
                $table->unsignedBigInteger('result_id')->nullable();
                $table->string('certificate_number', 50)->nullable();
                $table->date('issue_date')->nullable();
                $table->string('qr_code_path', 500)->nullable();
                $table->string('verification_url', 500)->nullable();
                $table->text('digital_signature')->nullable();
                $table->string('status', 30)->nullable();
                $table->string('revoked_reason', 500)->nullable();
                $table->unsignedBigInteger('issued_by')->nullable();
                $table->unsignedBigInteger('reviewed_by')->nullable();
                $table->timestamp('reviewed_at')->nullable();
                $table->text('review_note')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index('institute_id');
                $table->index('student_id');
                $table->index('batch_id');
                $table->index('certificate_number');
                $table->index('uuid');
            });
        }

        if (! Schema::hasTable('training_attendance')) {
            Schema::create('training_attendance', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('institute_id');
                $table->unsignedBigInteger('batch_id')->nullable();
                $table->unsignedBigInteger('student_id');
                $table->date('class_date');
                $table->string('status', 20)->nullable();
                $table->text('remarks')->nullable();
                $table->unsignedBigInteger('marked_by')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['institute_id', 'class_date']);
                $table->index('batch_id');
                $table->index('student_id');
            });
        }
    }

    public function down(): void
    {
        foreach ([
            'training_attendance',
            'training_certificates',
            'training_exam_results',
            'training_exams',
            'training_subjects',
            'training_classes',
            'training_enrollments',
            'training_batches',
            'training_students',
        ] as $tableName) {
            Schema::dropIfExists($tableName);
        }
    }
};

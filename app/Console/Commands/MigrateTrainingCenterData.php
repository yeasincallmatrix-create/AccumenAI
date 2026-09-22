<?php

namespace App\Console\Commands;

use App\Models\Institute;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\Batch;
use App\Models\Certificate;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MigrateTrainingCenterData extends Command
{
    protected $signature = 'training:migrate-data {--dry-run : Preview without writing} {--institute= : Migrate only this institute ID}';

    protected $description = 'Migrate training_center institutes data from education tables to training_* tables';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $instituteId = $this->option('institute');

        $query = Institute::where('industry', 'training_center');
        if ($instituteId) {
            $query->where('id', $instituteId);
        }
        $institutes = $query->get();

        if ($institutes->isEmpty()) {
            $this->warn('No training_center institutes found.');
            return 0;
        }

        $this->info("Found {$institutes->count()} training_center institute(s)");

        foreach ($institutes as $institute) {
            $this->migrateInstitute($institute, $dryRun);
        }

        if ($dryRun) {
            $this->warn('DRY RUN — no changes applied.');
        } else {
            $this->info('Migration complete.');
        }

        return 0;
    }

    protected function migrateInstitute(Institute $institute, bool $dryRun): void
    {
        $this->info("Migrating institute #{$institute->id} — {$institute->name}");

        $studentCount = Student::where('institute_id', $institute->id)->count();
        $enrollmentCount = StudentEnrollment::where('institute_id', $institute->id)->count();
        $batchCount = Batch::where('institute_id', $institute->id)->count();
        $certCount = Certificate::where('institute_id', $institute->id)->count();

        $this->table(['Table', 'Count'], [
            ['students', $studentCount],
            ['enrollments', $enrollmentCount],
            ['batches', $batchCount],
            ['certificates', $certCount],
        ]);

        if ($dryRun) {
            return;
        }

        DB::transaction(function () use ($institute, $studentCount, $enrollmentCount, $batchCount, $certCount) {
            // Migrate students
            Student::where('institute_id', $institute->id)
                ->chunk(100, function ($students) use ($institute) {
                    foreach ($students as $student) {
                        DB::table('training_students')->insert($this->getStudentData($student));
                    }
                });

            // Migrate enrollments
            StudentEnrollment::where('institute_id', $institute->id)
                ->chunk(100, function ($enrollments) use ($institute) {
                    foreach ($enrollments as $enrollment) {
                        DB::table('training_enrollments')->insert([
                            'institute_id' => $enrollment->institute_id,
                            'batch_id' => $enrollment->batch_id,
                            'roll_no' => $enrollment->roll_no,
                            'trainee_id' => $enrollment->trainee_id,
                            'student_id' => $enrollment->student_id,
                            'enrollment_date' => $enrollment->enrollment_date,
                            'status' => $enrollment->status,
                            'payment_status' => $enrollment->payment_status,
                            'created_at' => $enrollment->created_at,
                            'updated_at' => $enrollment->updated_at,
                            'deleted_at' => $enrollment->deleted_at,
                        ]);
                    }
                });

            // Migrate batches
            Batch::where('institute_id', $institute->id)
                ->chunk(100, function ($batches) use ($institute) {
                    foreach ($batches as $batch) {
                        DB::table('training_batches')->insert([
                            'is_test' => $batch->is_test,
                            'institute_id' => $batch->institute_id,
                            'course_id' => $batch->course_id,
                            'curriculum_id' => $batch->curriculum_id,
                            'academic_year_id' => $batch->academic_year_id,
                            'branch_id' => $batch->branch_id,
                            'teacher_id' => $batch->teacher_id,
                            'room_id' => $batch->room_id,
                            'name' => $batch->name,
                            'batch_code' => $batch->batch_code,
                            'shift' => $batch->shift,
                            'start_date' => $batch->start_date,
                            'end_date' => $batch->end_date,
                            'seat_capacity' => $batch->seat_capacity,
                            'seat_filled' => $batch->seat_filled,
                            'status' => $batch->status,
                            'attendance_threshold' => $batch->attendance_threshold,
                            'created_at' => $batch->created_at,
                            'updated_at' => $batch->updated_at,
                            'deleted_at' => $batch->deleted_at,
                        ]);
                    }
                });

            // Migrate certificates
            Certificate::where('institute_id', $institute->id)
                ->chunk(100, function ($certs) use ($institute) {
                    foreach ($certs as $cert) {
                        DB::table('training_certificates')->insert([
                            'uuid' => $cert->uuid,
                            'institute_id' => $cert->institute_id,
                            'student_id' => $cert->student_id,
                            'course_id' => $cert->course_id,
                            'batch_id' => $cert->batch_id,
                            'certificate_type_id' => $cert->certificate_type_id,
                            'template_id' => $cert->template_id,
                            'result_id' => $cert->result_id,
                            'certificate_number' => $cert->certificate_number,
                            'issue_date' => $cert->issue_date,
                            'qr_code_path' => $cert->qr_code_path,
                            'verification_url' => $cert->verification_url,
                            'digital_signature' => $cert->digital_signature,
                            'status' => $cert->status,
                            'revoked_reason' => $cert->revoked_reason,
                            'issued_by' => $cert->issued_by,
                            'reviewed_by' => $cert->reviewed_by,
                            'reviewed_at' => $cert->reviewed_at,
                            'review_note' => $cert->review_note,
                            'created_at' => $cert->created_at,
                            'updated_at' => $cert->updated_at,
                            'deleted_at' => $cert->deleted_at,
                        ]);
                    }
                });
        });

        $this->info("Migrated institute #{$institute->id}");
    }

    protected function getStudentData($student): array
    {
        return [
            'user_id' => $student->user_id,
            'reg_no' => $student->reg_no,
            'student_id' => $student->student_id,
            'is_test' => $student->is_test,
            'first_name' => $student->first_name,
            'last_name' => $student->last_name,
            'uuid' => $student->uuid,
            'institute_id' => $student->institute_id,
            'branch_id' => $student->branch_id,
            'student_id_number' => $student->student_id_number,
            'application_number' => $student->application_number,
            'application_date' => $student->application_date,
            'admission_status' => $student->admission_status,
            'admission_source' => $student->admission_source,
            'admission_reject_reason' => $student->admission_reject_reason,
            'applied_course_id' => $student->applied_course_id,
            'applied_academic_year_id' => $student->applied_academic_year_id,
            'preferred_batch_id' => $student->preferred_batch_id,
            'admission_assigned_user_id' => $student->admission_assigned_user_id,
            'created_by' => $student->created_by,
            'approved_by' => $student->approved_by,
            'approved_at' => $student->approved_at,
            'rejected_by' => $student->rejected_by,
            'rejected_at' => $student->rejected_at,
            'roll_number' => $student->roll_number,
            'photo' => $student->photo,
            'document' => $student->document,
            'father_name' => $student->father_name,
            'mother_name' => $student->mother_name,
            'gender' => $student->gender,
            'dob' => $student->dob,
            'blood_group' => $student->blood_group,
            'religion' => $student->religion,
            'nationality' => $student->nationality,
            'nid_number' => $student->nid_number,
            'birth_cert_number' => $student->birth_cert_number,
            'phone' => $student->phone,
            'guardian_phone' => $student->guardian_phone,
            'email' => $student->email,
            'country' => $student->country,
            'present_country_id' => $student->present_country_id,
            'present_admin_1_id' => $student->present_admin_1_id,
            'present_admin_2_id' => $student->present_admin_2_id,
            'present_admin_3_id' => $student->present_admin_3_id,
            'present_address' => $student->present_address,
            'permanent_address' => $student->permanent_address,
            'present_post_office' => $student->present_post_office,
            'present_zip_code' => $student->present_zip_code,
            'permanent_post_office' => $student->permanent_post_office,
            'permanent_zip_code' => $student->permanent_zip_code,
            'permanent_country_id' => $student->permanent_country_id,
            'permanent_admin_1_id' => $student->permanent_admin_1_id,
            'permanent_admin_2_id' => $student->permanent_admin_2_id,
            'permanent_admin_3_id' => $student->permanent_admin_3_id,
            'national_id_or_birth_certificate' => $student->national_id_or_birth_certificate,
            'passport_number' => $student->passport_number,
            'emergency_contact_name' => $student->emergency_contact_name,
            'emergency_contact_phone' => $student->emergency_contact_phone,
            'admission_date' => $student->admission_date,
            'status' => $student->status,
            'crm_contact_id' => $student->crm_contact_id,
            'crm_lead_id' => $student->crm_lead_id,
            'created_at' => $student->created_at,
            'updated_at' => $student->updated_at,
            'deleted_at' => $student->deleted_at,
            'full_name' => $student->full_name,
            'name' => $student->name,
        ];
    }
}

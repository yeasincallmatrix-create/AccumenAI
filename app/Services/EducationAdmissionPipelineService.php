<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\CrmLead;
use App\Models\CrmLeadStatus;
use App\Models\Student;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Step 38 — CRM → Education admission pipeline (forward direction).
 *
 * Step 34's EducationCrmIntegrationService bridges the education student to
 * the CRM (student is the source of truth; the lead/contact are projections).
 * This service completes the loop in the opposite direction: a CRM lead becomes
 * an education application/admission, reusing the SAME students row (the
 * application IS the student record, admission_status funnel) and the SAME CRM
 * services (CrmLeadService / CrmNoteService), so no parallel CRM, student,
 * enrollment or finance entity is ever created.
 *
 * Rules:
 *  - Idempotent: a lead already linked to an application returns that student.
 *  - Duplicate prevention: a student with the same email/phone exists → 422
 *    guiding the operator to link the existing student instead.
 *  - Existing-student reuse: linkExistingStudent() attaches the lead to an
 *    existing student without creating a duplicate application.
 *  - Conversion marks the lead "qualified" (interested) — the lead is only
 *    marked won later by the existing enrollment flow (convertAdmissionLead).
 *  - CRM mutations are skipped when the actor lacks the CRM permission; the
 *    education record is never sacrificed for a CRM side-effect.
 */
class EducationAdmissionPipelineService
{
    public function __construct(
        private readonly CrmLeadService $leads,
        private readonly CrmNoteService $notes,
    ) {}

    /**
     * Convert a CRM lead into an education application (a students row in the
     * submitted state). Returns the existing application when the lead is
     * already linked (idempotent).
     *
     * @param  array<string, mixed>  $data
     */
    public function convertLeadToApplication(CrmLead $lead, array $data, int $instituteId, int $actorId, bool $canUpdateCrm = true): Student
    {
        $existing = $this->applicationForLead($instituteId, (int) $lead->id);
        if ($existing !== null) {
            return $existing;
        }

        $this->assertNoDuplicatePerson($lead, $instituteId);

        return DB::transaction(function () use ($lead, $data, $instituteId, $actorId, $canUpdateCrm) {
            $branchId = $data['branch_id'] ?? $lead->branch_id;

            $attributes = [
                'institute_id' => $instituteId,
                'branch_id' => $branchId,
                'student_id_number' => Student::nextStudentNumber($instituteId),
                'application_date' => $data['application_date'] ?? now()->toDateString(),
                'admission_date' => $data['application_date'] ?? now()->toDateString(),
                'admission_status' => Student::ADMISSION_STATUS_SUBMITTED,
                'admission_source' => $data['admission_source'] ?? $this->sourceLabel($lead),
                'applied_course_id' => $data['applied_course_id'],
                'applied_academic_year_id' => $data['applied_academic_year_id'] ?? null,
                'preferred_batch_id' => $data['preferred_batch_id'] ?? null,
                'admission_assigned_user_id' => $data['admission_assigned_user_id'] ?? $lead->assigned_user_id,
                'status' => Student::STATUS_ACTIVE,
                'crm_lead_id' => $lead->id,
                'crm_contact_id' => $lead->contact_id,
                'full_name' => $data['full_name'] ?? $lead->displayName(),
                'phone' => $data['phone'] ?? $lead->phone,
                'guardian_phone' => $data['guardian_phone'] ?? null,
                'email' => $data['email'] ?? $lead->email,
                'gender' => $data['gender'] ?? null,
                'dob' => $data['dob'] ?? null,
                'country' => $data['country'] ?? null,
                'present_zip_code' => $data['present_zip_code'] ?? null,
            ];

            $student = Student::create(array_filter($attributes, fn ($value) => $value !== null && $value !== ''));

            $student->update([
                'application_number' => 'AP-'.str_pad((string) $student->id, 6, '0', STR_PAD_LEFT),
            ]);

            if ($canUpdateCrm) {
                $this->markInterested($lead, $instituteId, $actorId);
                $this->notes->create([
                    'subject_type' => 'lead',
                    'subject_id' => $lead->id,
                    'body' => 'Admission application '.$student->application_number.' created from this lead.',
                ], $instituteId, $branchId, $actorId);
            }

            $this->audit($instituteId, $actorId, 'Lead → Application', $lead->id, null, [
                'student_id' => $student->id,
                'application_number' => $student->application_number,
                'admission_status' => $student->admission_status,
            ]);

            return $student->refresh();
        });
    }

    /**
     * Attach a lead to an existing student (reuse) instead of creating a
     * duplicate application. Never creates or deletes records; never changes
     * the student's admission status.
     */
    public function linkExistingStudent(CrmLead $lead, Student $student, int $instituteId, int $actorId, bool $canUpdateCrm = true): Student
    {
        abort_if((int) $student->institute_id !== (int) $instituteId, 404, 'Student not found.');

        return DB::transaction(function () use ($lead, $student, $instituteId, $actorId, $canUpdateCrm) {
            $student->forceFill(['crm_lead_id' => $lead->id]);

            if ($student->crm_contact_id === null && $lead->contact_id !== null) {
                $student->forceFill(['crm_contact_id' => $lead->contact_id]);
            }

            if (blank($student->admission_source) && $lead->source_id !== null) {
                $student->forceFill(['admission_source' => $this->sourceLabel($lead)]);
            }

            if (blank($student->admission_assigned_user_id)) {
                $student->forceFill(['admission_assigned_user_id' => $lead->assigned_user_id]);
            }

            $student->save();

            if ($canUpdateCrm) {
                $this->markInterested($lead, $instituteId, $actorId);
                $this->notes->create([
                    'subject_type' => 'lead',
                    'subject_id' => $lead->id,
                    'body' => 'Linked to existing student '.$student->application_number.' ('.$student->full_name.').',
                ], $instituteId, $student->branch_id, $actorId);
            }

            $this->audit($instituteId, $actorId, 'Lead → Existing student', $lead->id, null, [
                'student_id' => $student->id,
                'application_number' => $student->application_number,
            ]);

            return $student->refresh();
        });
    }

    /**
     * The application currently linked to a lead (none if not yet converted).
     */
    public function applicationForLead(int $instituteId, int $leadId): ?Student
    {
        return Student::withoutGlobalScopes()
            ->where('institute_id', $instituteId)
            ->where('crm_lead_id', $leadId)
            ->first();
    }

    // ------------------------------------------------------------- Helpers

    /**
     * Move a not-yet-decided lead to "qualified" (the Interested stage of the
     * pipeline). Won/lost leads never regress; already-qualified stays put.
     */
    private function markInterested(CrmLead $lead, int $instituteId, int $actorId): void
    {
        if (in_array($lead->status?->slug, [CrmLeadStatus::SLUG_WON, CrmLeadStatus::SLUG_LOST], true)) {
            return;
        }

        $qualifiedId = CrmLeadStatus::where('slug', CrmLeadStatus::SLUG_QUALIFIED)->value('id');
        if ($qualifiedId === null || (int) $lead->status_id === (int) $qualifiedId) {
            return;
        }

        $this->leads->update($lead, ['status_id' => (int) $qualifiedId], $instituteId, $actorId);
    }

    /**
     * Block creating a duplicate application when the same person (email or
     * phone) already has a student record in the institute.
     */
    private function assertNoDuplicatePerson(CrmLead $lead, int $instituteId): void
    {
        $email = $lead->email !== null ? trim(mb_strtolower($lead->email)) : '';
        $phone = $lead->phone !== null ? trim($lead->phone) : '';

        if ($email === '' && $phone === '') {
            return;
        }

        $exists = Student::withoutGlobalScopes()
            ->where('institute_id', $instituteId)
            ->whereNull('deleted_at')
            ->where(function ($query) use ($email, $phone) {
                if ($email !== '') {
                    $query->where('email', $email);
                }
                if ($phone !== '') {
                    $query->orWhere('phone', $phone);
                }
            })
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'existing_student' => 'A student with this email or phone already exists. Link the existing student instead of creating a duplicate application.',
            ]);
        }
    }

    private function sourceLabel(CrmLead $lead): ?string
    {
        $source = $lead->source;

        return $source !== null ? ($source->name ?? $source->slug) : null;
    }

    private function audit(int $instituteId, ?int $actorId, string $action, ?int $recordId, ?array $oldValues, ?array $newValues): void
    {
        AuditLog::create([
            'institute_id' => $instituteId,
            'user_type' => 'institute_user',
            'user_id' => $actorId,
            'action' => $action,
            'module' => 'admission',
            'record_id' => $recordId,
            'old_values' => $oldValues !== null ? json_encode($oldValues) : null,
            'new_values' => $newValues !== null ? json_encode($newValues) : null,
            'ip_address' => request()->ip(),
            'user_agent' => substr((string) request()->userAgent(), 0, 255),
            'created_at' => now(),
        ]);
    }
}

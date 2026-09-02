<?php

namespace App\Services;

use App\Models\Document;
use App\Models\HrEmployee;
use App\Models\HrEmployeeSkill;
use App\Models\HrTraining;
use App\Models\HrTrainingEnrollment;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class HrTrainingService
{
    public function __construct(private readonly HrAuditService $audit) {}

    public function createTraining(array $data, int $instituteId, ?int $branchId, ?int $actorId): HrTraining
    {
        $this->assertBranchOfInstitute($branchId, $instituteId);
        if (strtotime($data['start_date']) > strtotime($data['end_date'])) {
            throw ValidationException::withMessages(['end_date' => 'End date must be after start date.']);
        }

        return DB::transaction(function () use ($data, $instituteId, $branchId, $actorId) {
            $training = HrTraining::create([
                'institute_id' => $instituteId,
                'branch_id' => $branchId,
                'title' => trim($data['title']),
                'description' => $data['description'] ?? null,
                'provider' => $data['provider'] ?? null,
                'trainer' => $data['trainer'] ?? null,
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'location' => $data['location'] ?? null,
                'is_online' => (bool) ($data['is_online'] ?? false),
                'capacity' => $data['capacity'] ?? null,
                'cost' => $data['cost'] ?? 0,
                'status' => $data['status'] ?? 'planned',
                'created_by' => $actorId,
                'updated_by' => $actorId,
            ]);
            $this->audit->record($instituteId, $actorId, 'hr_training_created', $training->id, null, $training->getAttributes());

            return $training;
        });
    }

    public function updateTraining(HrTraining $training, array $data, int $instituteId, ?int $actorId): HrTraining
    {
        abort_if((int) $training->institute_id !== (int) $instituteId, 404);
        $old = $training->getAttributes();

        return DB::transaction(function () use ($training, $data, $actorId, $instituteId, $old) {
            $training->update([
                'title' => isset($data['title']) ? trim($data['title']) : $training->title,
                'description' => $data['description'] ?? $training->description,
                'provider' => $data['provider'] ?? $training->provider,
                'trainer' => $data['trainer'] ?? $training->trainer,
                'start_date' => $data['start_date'] ?? $training->start_date,
                'end_date' => $data['end_date'] ?? $training->end_date,
                'location' => $data['location'] ?? $training->location,
                'is_online' => array_key_exists('is_online', $data) ? (bool) $data['is_online'] : $training->is_online,
                'capacity' => array_key_exists('capacity', $data) ? $data['capacity'] : $training->capacity,
                'cost' => $data['cost'] ?? $training->cost,
                'status' => $data['status'] ?? $training->status,
                'updated_by' => $actorId,
            ]);
            $this->audit->record($instituteId, $actorId, 'hr_training_updated', $training->id, $old, $training->fresh()->getAttributes());

            return $training->fresh();
        });
    }

    public function enroll(array $data, int $instituteId, ?int $branchId, ?int $actorId): HrTrainingEnrollment
    {
        $training = HrTraining::where('institute_id', $instituteId)->where('id', $data['training_id'])->firstOrFail();
        $employee = HrEmployee::where('institute_id', $instituteId)->where('id', $data['employee_id'])->firstOrFail();
        if ($branchId !== null) {
            // branch isolation: employee must be in manager's branch or training branch must match
            abort_if($employee->branch_id !== null && (int) $employee->branch_id !== (int) $branchId, 404);
            if ($training->branch_id !== null && (int) $training->branch_id !== (int) $branchId) {
                abort(403, 'Cannot enroll employee outside your branch training.');
            }
        }
        if ($training->capacity !== null) {
            $enrolledCount = HrTrainingEnrollment::where('training_id', $training->id)->whereNotIn('status', ['cancelled', 'dropped'])->count();
            if ($enrolledCount >= $training->capacity) {
                throw ValidationException::withMessages(['training_id' => 'Training capacity reached.']);
            }
        }
        $exists = HrTrainingEnrollment::where('training_id', $training->id)->where('employee_id', $employee->id)->exists();
        if ($exists) {
            throw ValidationException::withMessages(['employee_id' => 'Employee already enrolled in this training.']);
        }

        return DB::transaction(function () use ($data, $instituteId, $actorId, $training, $employee) {
            $enrollment = HrTrainingEnrollment::create([
                'institute_id' => $instituteId,
                'training_id' => $training->id,
                'employee_id' => $employee->id,
                'status' => $data['status'] ?? 'enrolled',
                'enrollment_date' => $data['enrollment_date'] ?? now()->toDateString(),
                'notes' => $data['notes'] ?? null,
                'created_by' => $actorId,
                'updated_by' => $actorId,
            ]);
            $training->increment('enrolled_count');
            $this->audit->record($instituteId, $actorId, 'hr_training_enrolled', $enrollment->id, null, $enrollment->getAttributes());

            return $enrollment;
        });
    }

    public function updateEnrollment(HrTrainingEnrollment $enrollment, array $data, int $instituteId, ?int $actorId): HrTrainingEnrollment
    {
        abort_if((int) $enrollment->institute_id !== (int) $instituteId, 404);
        $old = $enrollment->getAttributes();

        return DB::transaction(function () use ($enrollment, $data, $actorId, $instituteId, $old) {
            $updates = ['updated_by' => $actorId];
            foreach (['status', 'attendance_status', 'result', 'score', 'completion_date', 'notes'] as $field) {
                if (array_key_exists($field, $data)) {
                    $updates[$field] = $data[$field];
                }
            }
            // Handle certificate upload via document infrastructure (reuse HR-3)
            if (! empty($data['certificate_file'])) {
                $file = $data['certificate_file'];
                $path = $file->store('hr-training-certificates/'.$instituteId, 'public');
                $updates['certificate_path'] = $path;
                // Also create Document morph for reuse of HR-3 infra (certificate)
                $employee = HrEmployee::find($enrollment->employee_id);
                $categoryId = HrDocumentService::certificateCategoryId($instituteId);
                $doc = Document::create([
                    'institute_id' => $instituteId,
                    'branch_id' => $employee?->branch_id,
                    'category_id' => $categoryId,
                    'documentable_type' => HrEmployee::class,
                    'documentable_id' => $employee->id,
                    'title' => 'Training Certificate: '.$enrollment->training->title,
                    'file_path' => $path,
                    'disk' => 'public',
                    'original_filename' => $file->getClientOriginalName(),
                    'extension' => $file->getClientOriginalExtension(),
                    'mime_type' => $file->getMimeType(),
                    'file_size' => $file->getSize(),
                    'status' => Document::STATUS_ACTIVE,
                    'uploaded_by' => $actorId,
                ]);
                $updates['document_id'] = $doc->id;
            }
            $enrollment->update($updates);
            $this->audit->record($instituteId, $actorId, 'hr_training_enrollment_updated', $enrollment->id, $old, $enrollment->fresh()->getAttributes());

            return $enrollment->fresh();
        });
    }

    public function createSkill(array $data, int $instituteId, ?int $branchId, ?int $actorId): HrEmployeeSkill
    {
        $employee = HrEmployee::where('institute_id', $instituteId)->where('id', $data['employee_id'])->firstOrFail();
        if ($branchId !== null && $employee->branch_id !== null && (int) $employee->branch_id !== (int) $branchId) {
            abort(404);
        }

        return DB::transaction(function () use ($data, $instituteId, $actorId, $employee) {
            $skill = HrEmployeeSkill::create([
                'institute_id' => $instituteId,
                'employee_id' => $employee->id,
                'skill_name' => trim($data['skill_name']),
                'description' => $data['description'] ?? null,
                'proficiency_level' => $data['proficiency_level'] ?? 'beginner',
                'acquired_date' => $data['acquired_date'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => $actorId,
                'updated_by' => $actorId,
            ]);
            $this->audit->record($instituteId, $actorId, 'hr_skill_created', $skill->id, null, $skill->getAttributes());

            return $skill;
        });
    }

    public function verifySkill(HrEmployeeSkill $skill, int $instituteId, ?int $actorId, string $status = 'verified'): HrEmployeeSkill
    {
        abort_if((int) $skill->institute_id !== (int) $instituteId, 404);
        $old = $skill->getAttributes();

        return DB::transaction(function () use ($skill, $actorId, $status, $instituteId, $old) {
            $skill->update(['verification_status' => $status, 'verified_by' => $actorId, 'verified_at' => now()]);
            $this->audit->record($instituteId, $actorId, 'hr_skill_'.$status, $skill->id, $old, $skill->fresh()->getAttributes());

            return $skill->fresh();
        });
    }

    private function assertBranchOfInstitute(?int $branchId, int $instituteId): void
    {
        if ($branchId === null) {
            return;
        }
        $exists = DB::table('branches')->where('id', $branchId)->where('institute_id', $instituteId)->exists();
        abort_if(! $exists, 422, 'Branch does not belong to this institute.');
    }
}

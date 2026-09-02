<?php

namespace App\Services;

use App\Models\AcademicGroup;
use App\Models\AcademicYear;
use App\Models\Batch;
use App\Models\ClassGrade;
use App\Models\Course;
use App\Models\InstituteUser;
use App\Models\Role;
use App\Models\Subject;
use App\Models\TeacherAcademicAssignment;
use App\Models\TeacherProfile;
use App\Services\Auth\PasswordService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Teacher / instructor lifecycle (Step 36).
 *
 * The teacher identity is an existing `institute_users` row (role `teacher`);
 * the professional extension lives in `teacher_profiles`. Rules:
 *   - institute_id / branch_id NEVER come from request input — callers pass the
 *     resolved institute and the acting branch (branch_id NULL = institute-wide).
 *   - instructor codes are generated per-institute and collision-safe.
 *   - duplicate assignments are prevented at the service level.
 *   - deactivating a teacher never deletes assignment history, but a
 *     deactivated teacher cannot receive new assignments.
 *   - every mutation is audited through TeacherAuditService (no secrets).
 */
class TeacherProfileService
{
    private const USER_FIELDS = [
        'first_name', 'last_name', 'name', 'gender', 'designation', 'department',
        'qualification', 'joining_date', 'employee_id', 'photo', 'branch_id',
    ];

    private const PROFILE_FIELDS = [
        'specialization', 'experience_years', 'employment_type', 'employment_status',
        'date_of_birth', 'address', 'emergency_contact_name', 'emergency_contact_phone',
        'bio', 'skills', 'languages', 'notes',
    ];

    public function __construct(private readonly TeacherAuditService $audit) {}

    public function create(array $data, int $instituteId, ?int $branchId, int $actorId): InstituteUser
    {
        $this->assertBranchOfInstitute($branchId, $instituteId);

        $profileData = $this->profileData($data);
        $profileData['employment_status'] ??= 'active';

        return DB::transaction(function () use ($data, $instituteId, $branchId, $actorId, $profileData) {
            $userAttributes = $this->userAttributes($data, $instituteId, $branchId);
            $userAttributes['role_id'] = $this->teacherRoleId();
            $userAttributes['employee_id'] = $this->nextEmployeeCode($instituteId);
            $userAttributes['status'] = 'active';
            // Canonical password path — hash exactly once via PasswordService, mutator keeps it
            $userAttributes['password_hash'] = isset($data['password']) && $data['password'] !== null && $data['password'] !== ''
                ? app(PasswordService::class)->hash($data['password'])
                : null;

            $teacher = InstituteUser::create($userAttributes);

            $profile = TeacherProfile::create(array_merge($profileData, [
                'institute_id' => $instituteId,
                'institute_user_id' => $teacher->id,
                'created_by' => $actorId,
            ]));

            $this->audit->record(
                $instituteId,
                $actorId,
                'teacher_created',
                $teacher->id,
                null,
                $this->snapshot($teacher, $profile)
            );

            return $teacher;
        });
    }

    public function update(InstituteUser $teacher, array $data, int $instituteId, ?int $branchId, int $actorId): InstituteUser
    {
        $this->assertSameInstitute($teacher, $instituteId);
        $this->assertBranchOfInstitute($branchId, $instituteId);

        $oldUser = $teacher->getAttributes();
        $oldProfile = $teacher->teacherProfile?->getAttributes() ?? [];

        return DB::transaction(function () use ($teacher, $data, $instituteId, $branchId, $actorId, $oldUser, $oldProfile) {
            $userAttributes = $this->userAttributes($data, $instituteId, $branchId);
            $teacher->fill($userAttributes)->save();

            $profileData = array_merge($this->profileData($data), [
                'institute_id' => $instituteId,
                'institute_user_id' => $teacher->id,
                'updated_by' => $actorId,
            ]);

            $profile = TeacherProfile::updateOrCreate(
                ['institute_user_id' => $teacher->id],
                $profileData
            );

            $this->audit->record(
                $instituteId,
                $actorId,
                'teacher_updated',
                $teacher->id,
                $this->snapshot($oldUser, $oldProfile),
                $this->snapshot($teacher, $profile)
            );

            return $teacher->fresh(['teacherProfile']);
        });
    }

    public function setStatus(InstituteUser $teacher, string $employmentStatus, int $instituteId, int $actorId): InstituteUser
    {
        $this->assertSameInstitute($teacher, $instituteId);
        $this->assertValidEmploymentStatus($employmentStatus);

        $oldProfile = $teacher->teacherProfile?->getAttributes() ?? [];
        $oldUser = $teacher->getAttributes();

        return DB::transaction(function () use ($teacher, $employmentStatus, $instituteId, $actorId, $oldUser, $oldProfile) {
            $profile = $teacher->teacherProfile ?? new TeacherProfile([
                'institute_id' => $instituteId,
                'institute_user_id' => $teacher->id,
            ]);

            $profile->employment_status = $employmentStatus;
            $profile->updated_by = $actorId;
            $profile->save();

            $teacher->forceFill(['status' => $this->accountStatusFor($employmentStatus)])->save();

            $this->audit->record(
                $instituteId,
                $actorId,
                'teacher_status_changed',
                $teacher->id,
                $this->snapshot($oldUser, $oldProfile),
                $this->snapshot($teacher, $profile)
            );

            return $teacher->fresh(['teacherProfile']);
        });
    }

    public function assign(array $data, int $instituteId, int $actorId, ?int $actingBranchId = null): TeacherAcademicAssignment
    {
        $teacher = InstituteUser::query()
            ->where('id', (int) $data['institute_user_id'])
            ->first();

        abort_if($teacher === null || (int) $teacher->institute_id !== (int) $instituteId, 404, 'Teacher not found.');
        abort_if($teacher->role?->slug !== 'teacher', 404, 'Teacher not found.');

        $profile = $teacher->teacherProfile;
        abort_if($profile !== null && ! in_array($profile->employment_status, ['active', 'on_leave'], true), 422, 'A deactivated teacher cannot receive new assignments.');
        if ($profile === null) {
            abort(422, 'Teacher profile has no employment status.');
        }

        $branchId = $data['branch_id'] ?? $actingBranchId;
        $this->assertBranchOfInstitute($branchId, $instituteId);
        abort_if($branchId === null, 422, 'An assignment requires a branch.');
        $data['branch_id'] = $branchId;

        $year = AcademicYear::query()
            ->where('id', (int) $data['academic_year_id'])
            ->where('institute_id', $instituteId)
            ->exists();
        abort_if(! $year, 422, 'Academic year does not belong to this institute.');

        $this->assertHasScope($data);
        $this->assertReference($data, 'course_id', Course::class, $instituteId, true);
        $this->assertReference($data, 'subject_id', Subject::class, $instituteId, true);
        $this->assertReference($data, 'batch_id', Batch::class, $instituteId, true);
        if (isset($data['class_grade_id']) && filled($data['class_grade_id'])) {
            abort_if(! ClassGrade::whereKey((int) $data['class_grade_id'])->exists(), 422, 'Unknown class.');
        }
        if (isset($data['academic_group_id']) && filled($data['academic_group_id'])) {
            abort_if(! AcademicGroup::whereKey((int) $data['academic_group_id'])->exists(), 422, 'Unknown academic group.');
        }

        $this->assertNoActiveDuplicate($data, $instituteId);

        $attributes = [
            'institute_id' => $instituteId,
            'branch_id' => $branchId,
            'institute_user_id' => (int) $data['institute_user_id'],
            'academic_year_id' => (int) $data['academic_year_id'],
            'course_id' => $this->nullableInt($data, 'course_id'),
            'subject_id' => $this->nullableInt($data, 'subject_id'),
            'batch_id' => $this->nullableInt($data, 'batch_id'),
            'class_grade_id' => $this->nullableInt($data, 'class_grade_id'),
            'academic_group_id' => $this->nullableInt($data, 'academic_group_id'),
            'responsibility' => $data['responsibility'] ?? 'subject_teacher',
            'status' => TeacherAcademicAssignment::STATUS_ACTIVE,
            'assigned_at' => $data['assigned_at'] ?? now()->toDateString(),
            'notes' => $data['notes'] ?? null,
            'created_by' => $actorId,
        ];

        return DB::transaction(function () use ($attributes, $instituteId, $actorId) {
            $assignment = TeacherAcademicAssignment::create($attributes);
            $this->audit->record($instituteId, $actorId, 'assignment_created', $assignment->id, null, $assignment->getAttributes());

            return $assignment;
        });
    }

    public function complete(TeacherAcademicAssignment $assignment, int $instituteId, int $actorId): void
    {
        $this->assertSameInstitute($assignment, $instituteId);

        DB::transaction(function () use ($assignment, $instituteId, $actorId) {
            $old = $assignment->getAttributes();
            $assignment->forceFill([
                'status' => TeacherAcademicAssignment::STATUS_COMPLETED,
                'completed_at' => now()->toDateString(),
                'updated_by' => $actorId,
            ])->save();

            $this->audit->record($instituteId, $actorId, 'assignment_completed', $assignment->id, $old, $assignment->fresh()->getAttributes());
        });
    }

    public function remove(TeacherAcademicAssignment $assignment, int $instituteId, int $actorId): void
    {
        $this->assertSameInstitute($assignment, $instituteId);

        DB::transaction(function () use ($assignment, $instituteId, $actorId) {
            $old = $assignment->getAttributes();
            $assignment->delete();
            $this->audit->record($instituteId, $actorId, 'assignment_removed', $old['id'] ?? null, $old, null);
        });
    }

    // ------------------------------------------------------------- Helpers

    private function userAttributes(array $data, int $instituteId, ?int $branchId): array
    {
        $firstName = trim((string) ($data['first_name'] ?? ''));
        $lastName = trim((string) ($data['last_name'] ?? ''));

        return [
            'institute_id' => $instituteId,
            'branch_id' => $branchId,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'gender' => $data['gender'] ?? null,
            'designation' => $data['designation'] ?? null,
            'department' => $data['department'] ?? null,
            'qualification' => $data['qualification'] ?? null,
            'joining_date' => $data['joining_date'] ?? null,
            'photo' => $data['photo'] ?? null,
        ];
    }

    private function profileData(array $data): array
    {
        return array_intersect_key($data, array_flip(self::PROFILE_FIELDS));
    }

    private function snapshot(InstituteUser|array $user, TeacherProfile|array $profile): array
    {
        $userAttrs = is_array($user) ? $user : $user->getAttributes();
        $profileAttrs = is_array($profile) ? $profile : $profile->getAttributes();

        return array_diff_key(
            array_merge($userAttrs, $profileAttrs),
            ['password_hash' => true, 'two_factor_secret' => true, 'two_factor_recovery_codes' => true, 'remember_token' => true]
        );
    }

    private function assertSameInstitute(InstituteUser|TeacherAcademicAssignment $model, int $instituteId): void
    {
        abort_if((int) $model->institute_id !== (int) $instituteId, 404, 'Not found.');
    }

    private function assertBranchOfInstitute(?int $branchId, int $instituteId): void
    {
        if ($branchId === null) {
            return;
        }

        $exists = DB::table('branches')
            ->where('id', $branchId)
            ->where('institute_id', $instituteId)
            ->exists();

        abort_if(! $exists, 422, 'Branch does not belong to this institute.');
    }

    private function teacherRoleId(): int
    {
        $role = Role::where('slug', 'teacher')->first();

        abort_if($role === null, 500, 'Teacher role is not configured.');

        return (int) $role->id;
    }

    private function assertValidEmploymentStatus(string $status): void
    {
        abort_if(! in_array($status, TeacherProfile::EMPLOYMENT_STATUSES, true), 422, 'Unknown employment status.');
    }

    private function accountStatusFor(string $employmentStatus): string
    {
        return match ($employmentStatus) {
            'suspended' => 'suspended',
            'active', 'on_leave' => 'active',
            default => 'inactive',
        };
    }

    private function nextEmployeeCode(int $instituteId): string
    {
        return DB::transaction(function () use ($instituteId) {
            while (true) {
                $updated = DB::table('teacher_code_sequences')
                    ->where('institute_id', $instituteId)
                    ->increment('last_sequence');

                if ($updated > 0) {
                    $sequence = (int) DB::table('teacher_code_sequences')
                        ->where('institute_id', $instituteId)
                        ->value('last_sequence');

                    return $this->formatCode($instituteId, $sequence);
                }

                try {
                    DB::table('teacher_code_sequences')->insert([
                        'institute_id' => $instituteId,
                        'last_sequence' => 1,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    return $this->formatCode($instituteId, 1);
                } catch (QueryException $e) {
                    if ((int) $e->errorInfo[1] !== 1062) {
                        throw $e;
                    }
                }
            }
        });
    }

    private function formatCode(int $instituteId, int $sequence): string
    {
        return 'EMP-'.str_pad((string) $instituteId, 3, '0', STR_PAD_LEFT).'-'.str_pad((string) $sequence, 5, '0', STR_PAD_LEFT);
    }

    private function assertHasScope(array $data): void
    {
        $has = collect(['course_id', 'subject_id', 'batch_id', 'class_grade_id', 'academic_group_id'])
            ->contains(fn ($key) => filled($data[$key] ?? null));

        abort_if(! $has, 422, 'Assign the teacher to a course, subject, batch, class or group.');
    }

    private function assertReference(array $data, string $key, string $model, int $instituteId, bool $tenantScoped): void
    {
        $value = $data[$key] ?? null;
        if (! filled($value)) {
            return;
        }

        $query = $model::query();
        if ($tenantScoped) {
            $query->where(fn ($q) => $q->where('institute_id', $instituteId)->orWhereNull('institute_id'));
        }

        abort_if(! $query->whereKey((int) $value)->exists(), 422, 'Unknown reference for '.$key.'.');
    }

    private function assertNoActiveDuplicate(array $data, int $instituteId): void
    {
        $scopes = ['course_id', 'subject_id', 'batch_id', 'class_grade_id', 'academic_group_id'];
        $keys = ['institute_user_id', 'branch_id', 'academic_year_id', 'responsibility'];

        $query = TeacherAcademicAssignment::query()
            ->where('institute_id', $instituteId)
            ->where('status', TeacherAcademicAssignment::STATUS_ACTIVE);

        foreach ($keys as $key) {
            $value = $data[$key] ?? null;
            $query->where($key, in_array($key, ['institute_user_id', 'branch_id', 'academic_year_id'], true) ? (int) $value : $value);
        }

        foreach ($scopes as $key) {
            $value = $this->nullableInt($data, $key);
            if ($value === null) {
                $query->whereNull($key);
            } else {
                $query->where($key, $value);
            }
        }

        if ($query->exists()) {
            throw ValidationException::withMessages(['assignment' => 'An identical active assignment already exists for this teacher.']);
        }
    }

    private function nullableInt(array $data, string $key): ?int
    {
        $value = $data[$key] ?? null;

        return filled($value) ? (int) $value : null;
    }
}

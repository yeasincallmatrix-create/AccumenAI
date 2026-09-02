<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AcademicGroup;
use App\Models\AcademicLevel;
use App\Models\AcademicSelectionGroup;
use App\Models\ClassGrade;
use App\Models\Country;
use App\Models\CourseCategory;
use App\Models\EducationSystem;
use App\Models\Institute;
use App\Models\Subject;
use App\Models\SubjectAcademicAssignment;
use App\Services\AcademicSubjectService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Super Admin manager for the academic subject curriculum:
 *
 *   Subject Master            CRUD + activate/deactivate of academic subjects
 *   Academic Assignments      subject ⟷ class/grade (⟷ group) management
 *
 * Global shared reference data, country-scoped via the classes they attach to.
 * Authorization: `auth:platform_admin` route group (implicit superuser).
 */
class AcademicSubjectAdminController extends Controller
{
    public const SUBJECTS_COLUMNS = [
        'serial', 'name', 'code', 'type', 'category', 'institute', 'assignments', 'status', 'actions',
    ];

    public function __construct(private readonly AcademicSubjectService $service) {}

    // ------------------------------------------------------------- Subject Master

    public function index(Request $request): View
    {
        $institutes = Institute::query()
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->get(['id', 'name']);

        $query = Subject::query()
            ->with(['category', 'institute'])
            ->withCount('academicAssignments')
            ->whereNull('deleted_at')
            ->where('subject_type', 'academic')
            ->when($request->query('q'), function ($query, string $q) {
                $query->where(function ($query) use ($q) {
                    $query->where('name', 'like', "%{$q}%")
                        ->orWhere('short_name', 'like', "%{$q}%")
                        ->orWhere('subject_code', 'like', "%{$q}%");
                });
            })
            ->when($request->query('category_id'), fn ($query, $id) => $query->where('category_id', (int) $id))
            ->when($request->query('institute_id'), fn ($query, $id) => $query->where('institute_id', (int) $id))
            ->when($request->query('status'), fn ($query, $status) => $query->where('status', $status))
            ->orderBy('name');

        $items = (clone $query)->paginate(20)->withQueryString();

        $visibleColumns = $request->user()->preference('academic_subjects_columns', self::SUBJECTS_COLUMNS);
        $visibleColumns = array_values(array_intersect(self::SUBJECTS_COLUMNS, (array) $visibleColumns));

        return view('admin.academic.subjects.index', [
            'items' => $items,
            'institutes' => $institutes,
            'categories' => CourseCategory::query()->where('subject_type', 'academic')->orderBy('name')->get(['id', 'name']),
            'visibleColumns' => $visibleColumns,
            'selectedInstituteId' => (int) $request->query('institute_id'),
            'filters' => [
                'q' => $request->query('q'),
                'category_id' => $request->query('category_id'),
                'institute_id' => $request->query('institute_id'),
                'status' => $request->query('status'),
            ],
        ]);
    }

    public function saveColumns(Request $request): JsonResponse
    {
        $data = $request->validate([
            'columns' => ['nullable', 'array'],
            'columns.*' => ['string'],
        ]);

        $columns = array_values(array_intersect(self::SUBJECTS_COLUMNS, $data['columns'] ?? []));
        $request->user()->setPreference('academic_subjects_columns', $columns);

        return response()->json(['ok' => true, 'columns' => $columns]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'short_name' => ['nullable', 'string', 'max:100'],
            'subject_code' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        $code = filled($data['subject_code'] ?? null) ? trim($data['subject_code']) : null;
        if (blank($code)) {
            do {
                $code = 'SUB'.mt_rand(100000, 999999);
            } while (Subject::query()->where('subject_code', $code)->exists());
        }

        Subject::create([
            'institute_id' => null,
            'category_id' => null,
            'subject_type' => 'academic',
            'subject_code' => $code,
            'name' => trim($data['name']),
            'slug' => Str::slug(trim($data['name']).'-'.strtolower(Str::random(6))),
            'short_name' => filled($data['short_name'] ?? null) ? trim($data['short_name']) : null,
            'description' => filled($data['description'] ?? null) ? trim($data['description']) : null,
            'status' => 'active',
        ]);

        return redirect(route('admin.academic.subjects.index'))->with('status', "Subject '{$data['name']}' added.");
    }

    public function update(Request $request, Subject $subject): RedirectResponse
    {
        abort_if($subject->subject_type !== 'academic', 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'short_name' => ['nullable', 'string', 'max:100'],
            'subject_code' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        $subject->forceFill([
            'name' => trim($data['name']),
            'short_name' => filled($data['short_name'] ?? null) ? trim($data['short_name']) : null,
            'subject_code' => filled($data['subject_code'] ?? null) ? trim($data['subject_code']) : $subject->subject_code,
            'description' => filled($data['description'] ?? null) ? trim($data['description']) : null,
        ])->save();

        return redirect(route('admin.academic.subjects.index'))->with('status', 'Subject updated.');
    }

    public function toggle(Request $request, Subject $subject): RedirectResponse
    {
        abort_if($subject->subject_type !== 'academic', 404);

        $subject->forceFill(['status' => $subject->status === 'active' ? 'inactive' : 'active'])->save();

        return redirect(route('admin.academic.subjects.index'))
            ->with('status', "Subject '{$subject->name}' ".($subject->status === 'active' ? 'activated' : 'deactivated').'.');
    }

    // ------------------------------------------------------------- Assignments

    /**
     * Assignment manager: cascade Country → System → Level → Class → Group.
     * GET re-submits to narrow the selection; assignments render once a class is chosen.
     */
    public function assign(Request $request): View
    {
        $countryId = $request->query('country_id');
        $systemId = $request->query('system_id');
        $levelId = $request->query('level_id');
        $classId = $request->query('class_id');
        $groupId = $request->query('group_id');

        $countries = Country::query()->where('status', true)->orderBy('name')->get(['id', 'name']);
        $systems = filled($systemId)
            ? EducationSystem::query()->where('country_id', (int) $countryId)->where('status', true)->orderBy('display_order')->orderBy('id')->get(['id', 'name'])
            : collect();
        $levels = filled($levelId)
            ? AcademicLevel::query()->where('education_system_id', (int) $systemId)->where('status', true)->orderBy('display_order')->orderBy('id')->get(['id', 'name'])
            : collect();
        $classes = filled($classId)
            ? ClassGrade::query()->where('academic_level_id', (int) $levelId)->where('status', true)->orderBy('display_order')->orderBy('id')->get(['id', 'name'])
            : collect();
        $groups = filled($classId)
            ? AcademicGroup::query()->where('class_grade_id', (int) $classId)->where('status', true)->orderBy('display_order')->orderBy('id')->get(['id', 'name'])
            : collect();

        $classGrade = filled($classId) ? ClassGrade::query()->find((int) $classId) : null;
        $academicGroup = filled($groupId) ? AcademicGroup::query()->find((int) $groupId) : null;

        $nodes = [];
        $addableSubjects = collect();
        $selectionGroups = [];
        if ($classGrade !== null) {
            $nodes = $this->service->resolveRawAssignments($classGrade, $academicGroup);
            $addableSubjects = $this->service->addableSubjects($classGrade, $academicGroup);
            $selectionGroups = $this->service->selectionGroupsForClass($classGrade, $academicGroup);
        }

        return view('admin.academic.subjects.assign', [
            'countries' => $countries,
            'systems' => $systems,
            'levels' => $levels,
            'classes' => $classes,
            'groups' => $groups,
            'classGrade' => $classGrade,
            'academicGroup' => $academicGroup,
            'nodes' => $nodes,
            'addableSubjects' => $addableSubjects,
            'selectionGroups' => $selectionGroups,
            'selected' => [
                'country_id' => (int) ($countryId ?? 0),
                'system_id' => (int) ($systemId ?? 0),
                'level_id' => (int) ($levelId ?? 0),
                'class_id' => (int) ($classId ?? 0),
                'group_id' => (int) ($groupId ?? 0),
            ],
        ]);
    }

    public function storeAssignment(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'subject_id' => ['required', 'integer', Rule::exists('subjects', 'id')->where('subject_type', 'academic')->where('status', 'active')],
            'class_grade_id' => ['required', 'integer', Rule::exists('class_grades', 'id')->where('status', true)],
            'academic_group_id' => ['nullable', 'integer', Rule::exists('academic_groups', 'id')->where('status', true)],
            'selection_group_id' => ['nullable', 'integer', Rule::exists('academic_selection_groups', 'id')->where('status', 'active')],
            'requirement_type' => ['required', Rule::in(AcademicSubjectService::REQUIREMENT_TYPES)],
            'display_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $classId = (int) $data['class_grade_id'];
        $groupId = filled($data['academic_group_id'] ?? null) ? (int) $data['academic_group_id'] : null;
        $selectionGroupId = filled($data['selection_group_id'] ?? null) ? (int) $data['selection_group_id'] : null;

        if ($groupId !== null) {
            $group = AcademicGroup::query()->find($groupId);
            abort_if($group === null || $group->class_grade_id !== $classId, 422);
        }

        $this->assertSelectionGroupUsable($selectionGroupId, $classId, $groupId, $data['requirement_type']);

        $exists = SubjectAcademicAssignment::query()
            ->where('subject_id', (int) $data['subject_id'])
            ->where('class_grade_id', $classId)
            ->where('academic_group_id', $groupId)
            ->exists();

        abort_if($exists, 422, 'This subject is already assigned to this class/group.');

        SubjectAcademicAssignment::create([
            'subject_id' => (int) $data['subject_id'],
            'class_grade_id' => $classId,
            'academic_group_id' => $groupId,
            'selection_group_id' => $selectionGroupId,
            'requirement_type' => $data['requirement_type'],
            'display_order' => (int) ($data['display_order'] ?? 0),
            'status' => 'active',
        ]);

        return redirect(route('admin.academic.subjects.assign', ['class_id' => $classId, 'group_id' => $groupId ?? null]))
            ->with('status', 'Subject assigned.');
    }

    public function updateAssignment(Request $request, SubjectAcademicAssignment $assignment): RedirectResponse
    {
        $data = $request->validate([
            'display_order' => ['nullable', 'integer', 'min:0'],
            'selection_group_id' => ['nullable', 'integer', Rule::exists('academic_selection_groups', 'id')->where('status', 'active')],
            'requirement_type' => ['required', Rule::in(AcademicSubjectService::REQUIREMENT_TYPES)],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);

        $selectionGroupId = filled($data['selection_group_id'] ?? null) ? (int) $data['selection_group_id'] : null;

        $this->assertSelectionGroupUsable(
            $selectionGroupId,
            $assignment->class_grade_id,
            $assignment->academic_group_id,
            $data['requirement_type']
        );

        $assignment->forceFill([
            'display_order' => (int) ($data['display_order'] ?? $assignment->display_order),
            'selection_group_id' => $selectionGroupId,
            'requirement_type' => $data['requirement_type'],
            'status' => $data['status'],
        ])->save();

        return redirect(route('admin.academic.subjects.assign', [
            'class_id' => $assignment->class_grade_id,
            'group_id' => $assignment->academic_group_id,
        ]))->with('status', 'Assignment updated.');
    }

    public function destroyAssignment(Request $request, SubjectAcademicAssignment $assignment): RedirectResponse
    {
        $classId = $assignment->class_grade_id;
        $groupId = $assignment->academic_group_id;

        $assignment->delete();

        return redirect(route('admin.academic.subjects.assign', ['class_id' => $classId, 'group_id' => $groupId]))
            ->with('status', 'Assignment removed.');
    }

    // ------------------------------------------------------------- Selection Groups

    public function storeSelectionGroup(Request $request): RedirectResponse
    {
        $data = $this->validateSelectionGroup($request);

        $classId = (int) $data['class_grade_id'];
        $groupId = filled($data['academic_group_id'] ?? null) ? (int) $data['academic_group_id'] : null;

        if ($groupId !== null) {
            $group = AcademicGroup::query()->find($groupId);
            abort_if($group === null || $group->class_grade_id !== $classId, 422);
        }

        $this->assertMinMax($data['minimum_selection'] ?? null, $data['maximum_selection'] ?? null);

        AcademicSelectionGroup::create([
            'class_grade_id' => $classId,
            'academic_group_id' => $groupId,
            'name' => trim($data['name']),
            'code' => Str::slug($data['code'], '_'),
            'selection_type' => $data['selection_type'],
            'minimum_selection' => $data['minimum_selection'] ?? null,
            'maximum_selection' => $data['maximum_selection'] ?? null,
            'display_order' => (int) ($data['display_order'] ?? 0),
            'status' => 'active',
        ]);

        return redirect(route('admin.academic.subjects.assign', ['class_id' => $classId, 'group_id' => $groupId]))
            ->with('status', 'Selection group added.');
    }

    public function updateSelectionGroup(Request $request, AcademicSelectionGroup $selectionGroup): RedirectResponse
    {
        $data = $this->validateSelectionGroup($request, $selectionGroup->id);

        abort_if((int) $data['class_grade_id'] !== $selectionGroup->class_grade_id, 422);

        $groupId = filled($data['academic_group_id'] ?? null) ? (int) $data['academic_group_id'] : null;
        if ($groupId !== null) {
            $group = AcademicGroup::query()->find($groupId);
            abort_if($group === null || $group->class_grade_id !== $selectionGroup->class_grade_id, 422);
        }

        $this->assertMinMax($data['minimum_selection'] ?? null, $data['maximum_selection'] ?? null);

        $selectionGroup->forceFill([
            'academic_group_id' => $groupId,
            'name' => trim($data['name']),
            'code' => Str::slug($data['code'], '_'),
            'selection_type' => $data['selection_type'],
            'minimum_selection' => $data['minimum_selection'] ?? null,
            'maximum_selection' => $data['maximum_selection'] ?? null,
            'display_order' => (int) ($data['display_order'] ?? 0),
        ])->save();

        return redirect(route('admin.academic.subjects.assign', [
            'class_id' => $selectionGroup->class_grade_id,
            'group_id' => $selectionGroup->academic_group_id,
        ]))->with('status', 'Selection group updated.');
    }

    public function toggleSelectionGroup(Request $request, AcademicSelectionGroup $selectionGroup): RedirectResponse
    {
        $selectionGroup->forceFill(['status' => $selectionGroup->status === 'active' ? 'inactive' : 'active'])->save();

        return redirect(route('admin.academic.subjects.assign', [
            'class_id' => $selectionGroup->class_grade_id,
            'group_id' => $selectionGroup->academic_group_id,
        ]))->with('status', 'Selection group '.($selectionGroup->status === 'active' ? 'activated' : 'deactivated').'.');
    }

    public function destroySelectionGroup(Request $request, AcademicSelectionGroup $selectionGroup): RedirectResponse
    {
        $classId = $selectionGroup->class_grade_id;
        $groupId = $selectionGroup->academic_group_id;

        $selectionGroup->delete();

        return redirect(route('admin.academic.subjects.assign', ['class_id' => $classId, 'group_id' => $groupId]))
            ->with('status', 'Selection group removed. Member assignments were kept.');
    }

    /**
     * A selection group is usable by an assignment only when it belongs to the
     * same class/grade and (when stream-scoped) matches the assignment's group.
     * Mandatory subjects cannot join a selection group.
     */
    private function assertSelectionGroupUsable(?int $selectionGroupId, int $classId, ?int $groupId, string $requirementType): void
    {
        if ($selectionGroupId === null) {
            return;
        }

        $group = AcademicSelectionGroup::query()->find($selectionGroupId);
        abort_if($group === null, 422, 'Selection group not found.');

        abort_if($group->class_grade_id !== $classId, 422, 'Selection group does not belong to this class.');

        if ($group->academic_group_id !== null) {
            abort_if($groupId === null, 422, 'This selection group is scoped to a stream; assign the subject to that stream first.');
            abort_if((int) $group->academic_group_id !== $groupId, 422, 'This selection group belongs to a different stream.');
        }

        abort_if($requirementType === AcademicSubjectService::REQUIREMENT_MANDATORY, 422, 'Mandatory subjects cannot be members of a selection group.');
    }

    private function validateSelectionGroup(Request $request, ?int $ignoreId = null): array
    {
        $uniqueCode = Rule::unique('academic_selection_groups', 'code')
            ->where('class_grade_id', $request->input('class_grade_id'));
        if ($ignoreId !== null) {
            $uniqueCode->ignore($ignoreId);
        }

        return $request->validate([
            'class_grade_id' => ['required', 'integer', Rule::exists('class_grades', 'id')->where('status', true)],
            'academic_group_id' => ['nullable', 'integer', Rule::exists('academic_groups', 'id')->where('status', true)],
            'name' => ['required', 'string', 'max:120'],
            'code' => ['required', 'string', 'max:40', $uniqueCode],
            'selection_type' => ['required', Rule::in(['optional', 'elective'])],
            'minimum_selection' => ['nullable', 'integer', 'min:0'],
            'maximum_selection' => ['nullable', 'integer', 'min:0'],
            'display_order' => ['nullable', 'integer', 'min:0'],
        ]);
    }

    private function assertMinMax($minimum, $maximum): void
    {
        if ($minimum !== null && $maximum !== null && (int) $minimum > (int) $maximum) {
            abort(422, 'Minimum selection cannot exceed maximum selection.');
        }
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AcademicLevel;
use App\Models\Country;
use App\Models\EducationSystem;
use App\Models\GradeScale;
use App\Services\AcademicGradingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Super Admin manager for the global grading ladder:
 *
 *   GLOBAL DEFAULT → COUNTRY DEFAULT → SYSTEM DEFAULT → LEVEL DEFAULT
 *
 * Institute overrides are NOT managed here (they belong to the tenant
 * AcademicGradingController). Listing here is filtered to defaults only so a
 * super admin never silently mutates an institute's own scale.
 *
 * Authorization: `auth:platform_admin` route group (implicit superuser).
 */
class AcademicGradingAdminController extends Controller
{
    public function __construct(private readonly AcademicGradingService $grading) {}

    public function index(): View
    {
        $scales = GradeScale::query()
            ->with(['country', 'educationSystem', 'academicLevel'])
            ->withCount('rows')
            ->whereNull('institute_id')
            ->get()
            ->sortBy(fn (GradeScale $scale) => [$scale->ladderWeight(), $scale->name])
            ->values();

        return view('admin.academic.grading.index', [
            'scales' => $scales,
        ]);
    }

    public function create(Request $request): View
    {
        $scope = $this->scopeFromQuery($request);

        return view('admin.academic.grading.form', [
            'scale' => null,
            'scope' => $scope,
            'options' => $this->grading->scopeOptions($scope['country_id']),
            'levels' => $this->grading->scopeLevels($scope['system_id']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $rows = $this->rowPayload($request);

        $hasBase = collect($request->input('rows'))->contains(fn ($r) => isset($r['grade']) && trim((string) $r['grade']) !== '');
        abort_if(! $hasBase, 422, 'Add at least one grade band.');

        $this->assertScopeCoherent($data);

        $scale = $this->grading->store($data, $rows);

        return redirect(route('admin.academic.grading.index'))->with('status', 'Grade scale "'.$scale->name.'" created.');
    }

    public function edit(Request $request, GradeScale $grading): View
    {
        abort_if($grading->isInstituteOverride(), 404);

        $scope = [
            'country_id' => $grading->country_id,
            'system_id' => $grading->education_system_id,
            'level_id' => $grading->academic_level_id,
        ];

        return view('admin.academic.grading.form', [
            'scale' => $grading->load('rows'),
            'scope' => $scope,
            'options' => $this->grading->scopeOptions($scope['country_id']),
            'levels' => $this->grading->scopeLevels($scope['system_id']),
        ]);
    }

    public function update(Request $request, GradeScale $grading): RedirectResponse
    {
        abort_if($grading->isInstituteOverride(), 404);

        $data = $this->validated($request);
        $rows = $this->rowPayload($request);

        $hasBase = collect($request->input('rows'))->contains(fn ($r) => isset($r['grade']) && trim((string) $r['grade']) !== '');
        abort_if(! $hasBase, 422, 'Add at least one grade band.');

        $scale = $this->grading->update($grading, $data, $rows);

        return redirect(route('admin.academic.grading.index'))->with('status', 'Grade scale "'.$scale->name.'" updated.');
    }

    public function destroy(Request $request, GradeScale $grading): RedirectResponse
    {
        abort_if($grading->isInstituteOverride(), 404);

        $this->grading->destroy($grading);

        return redirect(route('admin.academic.grading.index'))->with('status', 'Grade scale removed.');
    }

    // ------------------------------------------------------------- Internals

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'country_id' => ['nullable', 'integer', Rule::exists('countries', 'id')],
            'education_system_id' => ['nullable', 'integer', Rule::exists('education_systems', 'id')],
            'academic_level_id' => ['nullable', 'integer', Rule::exists('academic_levels', 'id')],
            'gpa_mode' => ['required', Rule::in(GradeScale::GPA_MODES)],
            'optional_subject_gpa' => ['required', Rule::in(GradeScale::OPTIONAL_SUBJECT_GPA_POLICIES)],
            'status' => ['nullable', 'boolean'],
            'display_order' => ['nullable', 'integer', 'min:0'],
        ]);
    }

    /**
     * A default scale must sit on ONE ladder rung: scope columns must form a
     * valid parent→child chain (a system default belongs to the chosen
     * country; a level default to the chosen system).
     */
    private function assertScopeCoherent(array $data): void
    {
        $countryId = isset($data['country_id']) && filled($data['country_id']) ? (int) $data['country_id'] : null;
        $systemId = isset($data['education_system_id']) && filled($data['education_system_id']) ? (int) $data['education_system_id'] : null;
        $levelId = isset($data['academic_level_id']) && filled($data['academic_level_id']) ? (int) $data['academic_level_id'] : null;

        abort_if($levelId !== null && $systemId === null, 422, 'An academic-level default requires its education system.');
        abort_if($systemId !== null && $countryId === null, 422, 'An education-system default requires its country.');

        if ($systemId !== null) {
            $system = EducationSystem::query()->find($systemId);
            abort_if($system === null || (int) $system->country_id !== $countryId, 422, 'Education system does not belong to the selected country.');
        }

        if ($levelId !== null) {
            $level = AcademicLevel::query()->find($levelId);
            abort_if($level === null || (int) $level->education_system_id !== $systemId, 422, 'Academic level does not belong to the selected education system.');
        }
    }

    /**
     * @return array<string, int|null>
     */
    private function scopeFromQuery(Request $request): array
    {
        $countryId = $request->query('country_id') !== null ? (int) $request->query('country_id') : null;
        $systemId = $request->query('system_id') !== null ? (int) $request->query('system_id') : null;

        return [
            'country_id' => $countryId,
            'system_id' => $systemId,
            'level_id' => null,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function rowPayload(Request $request): array
    {
        $rows = $request->input('rows') ?? [];
        $payload = [];

        foreach ($rows as $index => $row) {
            if (! isset($row['grade']) || trim((string) $row['grade']) === '') {
                continue;
            }

            $payload[] = [
                'grade' => trim((string) $row['grade']),
                'min_score' => (float) ($row['min_score'] ?? 0),
                'max_score' => (float) ($row['max_score'] ?? 0),
                'grade_point' => (float) ($row['grade_point'] ?? 0),
                'is_pass' => (bool) ($row['is_pass'] ?? true),
                'gpa_included' => (bool) ($row['gpa_included'] ?? true),
                'display_order' => $index + 1,
                'status' => (bool) ($row['active'] ?? true),
            ];
        }

        return $payload;
    }
}

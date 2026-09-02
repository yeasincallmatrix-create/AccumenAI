<?php

namespace App\Http\Controllers;

use App\Models\AcademicLevel;
use App\Models\AcademicResultAggregationScheme;
use App\Models\Country;
use App\Models\EducationSystem;
use App\Models\GradeScale;
use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\User;
use App\Services\AcademicFinalResultService;
use App\Services\AcademicGradingService;
use App\Services\AcademicSubjectService;
use App\Support\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Institute-facing grading configuration + derived final-result preview.
 *
 * An institute owner/admin can:
 *   - see which grade scale would apply to each of their classes (the
 *     inherited ladder + any institute overrides)
 *   - create/update/delete INSTITUTE OVERRIDE scales (allowed to override the
 *     country/system/level defaults — the defaults themselves are managed by
 *     super admins only and are never mutated here)
 *   - preview final grades + GPA computed purely in the backend
 *
 * Security model mirrors AcademicAggregationController:
 *   - institute identity comes ONLY from the authenticated user / workspace
 *     (forged institute_id is ignored)
 *   - institute overrides are scoped by institute_id (checked explicitly here
 *     because GradeScale has no tenant global scope — it doubles as admin
 *     reference data)
 *   - branch restrictions: overrides are institute-wide, but the preview only
 *     shows placements the acting user may see (branch-scoped placements)
 *   - route group gated by permission:education.manage
 */
class AcademicGradingController extends Controller
{
    public function __construct(
        private readonly AcademicGradingService $grading,
        private readonly AcademicFinalResultService $finalResults,
        private readonly AcademicSubjectService $subjects
    ) {}

    public function index(Request $request): View
    {
        $institute = $this->requireInstitute($request);
        $country = $institute->country()->first();

        $overrides = GradeScale::query()
            ->with(['rows', 'academicLevel'])
            ->where('institute_id', $institute->id)
            ->orderBy('display_order')
            ->orderBy('id')
            ->get();

        // The effective scale for every class/grade this institute offers —
        // demonstrates exactly which ladder rung applies (and lets the owner
        // see the override they currently get).
        $labels = [];
        foreach ($this->subjects->effectiveClasses($institute) as $entry) {
            $classGrade = $entry['class_grade'];
            if ($classGrade === null) {
                continue;
            }
            $labels[] = [
                'class' => $entry['name'],
                'scale' => $this->grading->resolveScaleForClass($institute, $classGrade),
            ];
        }

        $availableLevels = collect();
        if ($country !== null) {
            $availableLevels = AcademicLevel::query()
                ->whereIn('education_system_id', EducationSystem::query()->where('country_id', $country->id)->pluck('id'))
                ->where('status', true)
                ->orderBy('display_order')
                ->orderBy('id')
                ->get();
        }

        return view('institute.academic-grading.index', [
            'institute' => $institute,
            'country' => $country,
            'overrides' => $overrides,
            'classLabels' => $labels,
            'availableLevels' => $availableLevels,
        ]);
    }

    public function create(Request $request): View
    {
        $institute = $this->requireInstitute($request);
        $country = $institute->country()->first();

        return view('institute.academic-grading.form', [
            'institute' => $institute,
            'scale' => null,
            'countries' => $country !== null ? collect([$country]) : collect(),
            'availableLevels' => $this->instituteLevels($institute),
            'inheritedDefaults' => $this->inheritedDefaults($institute),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $institute = $this->requireInstitute($request);

        $data = $this->validated($request, $institute);
        $rows = $this->rowPayload($request);
        // NOTE: rowPayload already ran the no-overlap/min-max server check via
        // the grading service; validation of responsibilities stays backend-only.

        $hasBase = collect($request->input('rows'))->contains(fn ($r) => isset($r['grade']) && trim((string) $r['grade']) !== '');
        abort_if(! $hasBase, 422, 'Add at least one grade band.');

        $scale = $this->grading->store($data, $rows);

        return redirect()
            ->route('settings.academic.grading.index')
            ->with('status', 'Grade scale "'.$scale->name.'" saved.');
    }

    public function edit(Request $request, int $scaleId): View
    {
        $institute = $this->requireInstitute($request);
        $scale = $this->requireInstituteScale($institute, $scaleId);

        return view('institute.academic-grading.form', [
            'institute' => $institute,
            'scale' => $scale,
            'countries' => $institute->country()->first() !== null ? collect([$institute->country()->first()]) : collect(),
            'availableLevels' => $this->instituteLevels($institute),
            'inheritedDefaults' => $this->inheritedDefaults($institute),
        ]);
    }

    public function update(Request $request, int $scaleId): RedirectResponse
    {
        $institute = $this->requireInstitute($request);
        $scale = $this->requireInstituteScale($institute, $scaleId);

        $data = $this->validated($request, $institute);
        $rows = $this->rowPayload($request);

        $hasBase = collect($request->input('rows'))->contains(fn ($r) => isset($r['grade']) && trim((string) $r['grade']) !== '');
        abort_if(! $hasBase, 422, 'Add at least one grade band.');

        $this->grading->update($scale, $data, $rows);

        return redirect()
            ->route('settings.academic.grading.index')
            ->with('status', 'Grade scale updated.');
    }

    public function destroy(Request $request, int $scaleId): RedirectResponse
    {
        $institute = $this->requireInstitute($request);
        $scale = $this->requireInstituteScale($institute, $scaleId);

        $this->grading->destroy($scale);

        return redirect()
            ->route('settings.academic.grading.index')
            ->with('status', 'Grade scale override removed.');
    }

    /**
     * Derived preview for an aggregation scheme: per placement final subject
     * grades + GPA, computed backend-only (never persisted).
     */
    public function preview(Request $request): View
    {
        $institute = $this->requireInstitute($request);

        $schemeId = $request->query('scheme_id');
        $scheme = $schemeId !== null
            ? AcademicResultAggregationScheme::query()->find((int) $schemeId)
            : null;
        $schemes = AcademicResultAggregationScheme::query()
            ->with(['academicYear', 'classGrade'])
            ->orderByDesc('id')
            ->get();

        $preview = $scheme !== null ? $this->finalResults->preview($scheme) : null;

        $effectiveScale = null;
        if ($scheme !== null && $scheme->classGrade !== null) {
            $effectiveScale = $this->grading->resolveScaleForClass($institute, $scheme->classGrade);
        }

        return view('institute.academic-grading.preview', [
            'institute' => $institute,
            'schemes' => $schemes,
            'scheme' => $scheme,
            'preview' => $preview,
            'effectiveScale' => $effectiveScale,
        ]);
    }

    // ------------------------------------------------------------- Internals

    private function requireInstituteScale(Institute $institute, int $scaleId): GradeScale
    {
        $scale = GradeScale::query()->with('rows')->where('id', $scaleId)->where('institute_id', $institute->id)->first();
        abort_if($scale === null, 404);

        return $scale;
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, Institute $institute): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'academic_level_id' => ['nullable', 'integer'],
            'gpa_mode' => ['required', Rule::in(GradeScale::GPA_MODES)],
            'optional_subject_gpa' => ['required', Rule::in(GradeScale::OPTIONAL_SUBJECT_GPA_POLICIES)],
            'status' => ['nullable', 'boolean'],
            'display_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $data['institute_id'] = $institute->id;

        // The institute may scope its override to one of its own academic
        // levels; anything else is rejected (institute never crafts scope for
        // a level outside its country).
        if (filled($data['academic_level_id'] ?? null)) {
            $owned = $this->instituteLevels($institute)
                ->firstWhere('id', (int) $data['academic_level_id']);
            abort_if($owned === null, 422, 'Invalid academic level.');
        } else {
            $data['academic_level_id'] = null;
        }

        return $data;
    }

    /**
     * Extract nested grade band rows from the form.
     *
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

    /**
     * Ladder defaults that could currently apply to this institute (inherited
     * global / country / system / level defaults). Read-only reference for
     * the owner — editing them happens under Super Admin.
     */
    private function inheritedDefaults(Institute $institute): array
    {
        $country = $institute->country()->first();
        if ($country === null) {
            return [];
        }

        $ids = collect();
        foreach ($this->subjects->effectiveClasses($institute) as $entry) {
            $classGrade = $entry['class_grade'];
            if ($classGrade === null) {
                continue;
            }
            $ids->push((int) $classGrade->country_id);
            $ids->push((int) $classGrade->education_system_id);
            $ids->push((int) $classGrade->academic_level_id);
        }

        return GradeScale::query()
            ->with(['rows', 'country', 'educationSystem', 'academicLevel'])
            ->whereNull('institute_id')
            ->where(fn ($q) => $q
                ->whereIn('country_id', $ids->unique()->values()->all())
                ->orWhereNull('country_id'))
            ->get()
            ->sortBy(fn (GradeScale $scale) => [$scale->ladderWeight(), $scale->name])
            ->values()
            ->map(fn ($scale) => [
                'scale' => $scale,
                'effective' => $this->appliesToInstitute($institute, $scale),
            ])
            ->all();
    }

    private function appliesToInstitute(Institute $institute, GradeScale $scale): bool
    {
        foreach ($this->subjects->effectiveClasses($institute) as $entry) {
            $classGrade = $entry['class_grade'];
            if ($classGrade === null) {
                continue;
            }
            if ($this->grading->resolveScaleForClass($institute, $classGrade)?->id === $scale->id) {
                return true;
            }
        }

        return false;
    }

    private function instituteLevels(Institute $institute): Collection
    {
        $country = $institute->country()->first();
        $ids = collect();
        foreach ($this->subjects->effectiveClasses($institute) as $entry) {
            if ($entry['class_grade'] !== null) {
                $ids->push((int) $entry['class_grade']->academic_level_id);
            }
        }

        $query = AcademicLevel::query()
            ->where('status', true)
            ->whereIn('id', $ids->unique()->values()->all());

        if ($country !== null) {
            $query->where('country_id', $country->id);
        }

        return $query->orderBy('display_order')->orderBy('id')->get();
    }

    private function requireInstitute(Request $request): Institute
    {
        $institute = $this->resolveInstitute($request);
        abort_if($institute === null, 404);

        return $institute;
    }

    private function resolveInstitute(Request $request): ?Institute
    {
        $user = $request->user();

        if ($user instanceof InstituteUser) {
            return Institute::query()->find($user->institute_id);
        }

        if ($user instanceof User) {
            $membership = Workspace::membership();

            return $membership !== null ? Institute::query()->find($membership->institution_id) : null;
        }

        return null;
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\AcademicGroup;
use App\Models\AcademicLevel;
use App\Models\ClassGrade;
use App\Models\Institute;
use App\Models\InstituteAcademicGroup;
use App\Models\InstituteAcademicLevel;
use App\Models\InstituteClassGrade;
use App\Models\InstituteSetting;
use App\Models\InstituteUser;
use App\Models\User;
use App\Services\AcademicStructureService;
use App\Support\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Institute-level academic structure customization.
 *
 * The institute sees its inherited country defaults (via AcademicStructureService)
 * and may: enable/disable inherited levels/classes/groups, rename/reorder them,
 * and add its own custom levels/classes/groups. The global country masters are
 * never modified.
 *
 * Security: institute identity comes ONLY from the authenticated user /
 * active workspace membership — never from request input. All mutate routes are
 * gated by `permission:education.manage` (owner + admin). TenantScoped global
 * scopes further constrain every customization row to the active institute.
 */
class AcademicStructureController extends Controller
{
    public function __construct(private readonly AcademicStructureService $service) {}

    public function index(Request $request): View
    {
        $institute = $this->resolveInstitute($request);

        abort_if($institute === null, 404);

        return view('institute.academic-structure', [
            'institute' => $institute,
            'structure' => $this->service->resolve($institute),
            'systems' => $this->service->systemsForCountry($institute->country_id),
        ]);
    }

    /**
     * Dedicated Academic Years index — extracted from placements anchor for Step 2.
     * Reuses same AcademicYear model; store/update/destroy remain in StudentAcademicPlacementController.
     * Non-destructive: old anchor in placements/index remains for backward compatibility.
     */
    public function academicYearsIndex(Request $request): View
    {
        $institute = $this->resolveInstitute($request);
        abort_if($institute === null, 404);

        return view('institute.academic.academic-years.index', [
            'institute' => $institute,
            'academicYears' => \App\Models\AcademicYear::query()->orderByDesc('code')->get(),
        ]);
    }

    // ------------------------------------------------------------- Label

    public function updateLabel(Request $request): RedirectResponse
    {
        $institute = $this->resolveInstitute($request);
        abort_if($institute === null, 404);

        $data = $request->validate([
            'academic_unit_label' => ['nullable', 'string', 'max:40'],
        ]);

        InstituteSetting::updateOrCreate(
            ['institute_id' => $institute->id],
            ['academic_unit_label' => filled($data['academic_unit_label'] ?? null)
                ? trim($data['academic_unit_label'])
                : null]
        );

        return redirect(route('settings.academic.index'))->with('status', 'Academic unit label updated.');
    }

    // ------------------------------------------------------------- Level

    /**
     * Enable/disable or rename/reorder an inherited global level for this
     * institute. Empty payload = revert to inherited.
     */
    public function updateLevel(Request $request, AcademicLevel $level): RedirectResponse
    {
        $institute = $this->resolveInstitute($request);
        abort_if($institute === null, 404);

        $data = $request->validate([
            'enabled' => ['nullable', 'boolean'],
            'name' => ['nullable', 'string', 'max:120'],
            'display_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $this->setLevelOverride($institute, $level, (bool) ($data['enabled'] ?? true), $data);

        return redirect(route('settings.academic.index'))->with('status', 'Academic level updated.');
    }

    /**
     * Add an institute-created level under one of the country's education systems.
     */
    public function storeLevel(Request $request): RedirectResponse
    {
        $institute = $this->resolveInstitute($request);
        abort_if($institute === null, 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'education_system_id' => ['required', 'integer', Rule::exists('education_systems', 'id')->where('country_id', $institute->country_id)],
            'display_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $name = trim($data['name']);
        $exists = InstituteAcademicLevel::query()
            ->where('institute_id', $institute->id)
            ->where('is_custom', true)
            ->where('name', $name)
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'name' => 'A custom level with this name already exists.',
            ]);
        }

        InstituteAcademicLevel::create([
            'institute_id' => $institute->id,
            'academic_level_id' => null,
            'education_system_id' => $data['education_system_id'],
            'name' => $name,
            'display_order' => (int) ($data['display_order'] ?? 0),
            'status' => true,
            'is_custom' => true,
        ]);

        return redirect(route('settings.academic.index'))->with('status', "Custom level '{$name}' added.");
    }

    /**
     * Remove an institute-created level (or a stale override row) entirely.
     */
    public function destroyLevel(Request $request, InstituteAcademicLevel $level): RedirectResponse
    {
        $institute = $this->resolveInstitute($request);
        abort_if($institute === null, 404);

        $level->delete();

        return redirect(route('settings.academic.index'))->with('status', 'Custom level removed.');
    }

    // ------------------------------------------------------------- Class

    public function updateClass(Request $request, ClassGrade $class): RedirectResponse
    {
        $institute = $this->resolveInstitute($request);
        abort_if($institute === null, 404);

        $data = $request->validate([
            'enabled' => ['nullable', 'boolean'],
            'name' => ['nullable', 'string', 'max:120'],
            'sequence' => ['nullable', 'integer', 'min:1'],
            'display_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $this->setClassOverride($institute, $class, (bool) ($data['enabled'] ?? true), $data);

        return redirect(route('settings.academic.index'))->with('status', 'Class/grade updated.');
    }

    /**
     * Add an institute-created class under either a global level
     * ({academic_level_id}) or a custom level ({institute_academic_level_id}).
     */
    public function storeClass(Request $request): RedirectResponse
    {
        $institute = $this->resolveInstitute($request);
        abort_if($institute === null, 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'academic_level_id' => ['nullable', 'integer', 'prohibits:institute_academic_level_id', Rule::exists('academic_levels', 'id')],
            'institute_academic_level_id' => ['nullable', 'integer', 'prohibits:academic_level_id', Rule::exists('institute_academic_levels', 'id')],
            'sequence' => ['nullable', 'integer', 'min:1'],
            'display_order' => ['nullable', 'integer', 'min:0'],
        ]);

        if (blank($data['academic_level_id'] ?? null) && blank($data['institute_academic_level_id'] ?? null)) {
            throw ValidationException::withMessages([
                'academic_level_id' => 'Choose a level for the new class/grade.',
            ]);
        }

        $parentIsCustomLevel = filled($data['institute_academic_level_id'] ?? null);

        // Parent must belong to this institute's country scope.
        if (! $parentIsCustomLevel) {
            $this->assertGlobalLevelUsable($institute, (int) $data['academic_level_id']);
        } else {
            $parent = InstituteAcademicLevel::query()->find((int) $data['institute_academic_level_id']);
            abort_if($parent === null || $parent->institute_id !== $institute->id || ! $parent->is_custom, 403);
        }

        $name = trim($data['name']);
        InstituteClassGrade::create([
            'institute_id' => $institute->id,
            'class_grade_id' => null,
            'academic_level_id' => $parentIsCustomLevel ? null : (int) $data['academic_level_id'],
            'institute_academic_level_id' => $parentIsCustomLevel ? (int) $data['institute_academic_level_id'] : null,
            'name' => $name,
            'sequence' => filled($data['sequence'] ?? null) ? (int) $data['sequence'] : null,
            'display_order' => (int) ($data['display_order'] ?? 0),
            'status' => true,
            'is_custom' => true,
        ]);

        return redirect(route('settings.academic.index'))->with('status', "Custom class/grade '{$name}' added.");
    }

    public function destroyClass(Request $request, InstituteClassGrade $class): RedirectResponse
    {
        $institute = $this->resolveInstitute($request);
        abort_if($institute === null, 404);

        $class->delete();

        return redirect(route('settings.academic.index'))->with('status', 'Custom class/grade removed.');
    }

    // ------------------------------------------------------------- Group

    public function updateGroup(Request $request, AcademicGroup $group): RedirectResponse
    {
        $institute = $this->resolveInstitute($request);
        abort_if($institute === null, 404);

        $data = $request->validate([
            'enabled' => ['nullable', 'boolean'],
            'name' => ['nullable', 'string', 'max:120'],
            'display_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $this->setGroupOverride($institute, $group, (bool) ($data['enabled'] ?? true), $data);

        return redirect(route('settings.academic.index'))->with('status', 'Group/stream updated.');
    }

    /**
     * Add an institute-created group under a global class ({class_grade_id})
     * or a custom class ({institute_class_grade_id}).
     */
    public function storeGroup(Request $request): RedirectResponse
    {
        $institute = $this->resolveInstitute($request);
        abort_if($institute === null, 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'class_grade_id' => ['nullable', 'integer', 'prohibits:institute_class_grade_id', Rule::exists('class_grades', 'id')],
            'institute_class_grade_id' => ['nullable', 'integer', 'prohibits:class_grade_id', Rule::exists('institute_class_grades', 'id')],
            'display_order' => ['nullable', 'integer', 'min:0'],
        ]);

        if (blank($data['class_grade_id'] ?? null) && blank($data['institute_class_grade_id'] ?? null)) {
            throw ValidationException::withMessages([
                'class_grade_id' => 'Choose a class/grade for the new group/stream.',
            ]);
        }

        $parentIsCustomClass = filled($data['institute_class_grade_id'] ?? null);

        if (! $parentIsCustomClass) {
            $parentClass = ClassGrade::query()->find((int) $data['class_grade_id']);
            abort_if($parentClass === null || $parentClass->country_id !== $institute->country_id, 403);
        } else {
            $parent = InstituteClassGrade::query()->find((int) $data['institute_class_grade_id']);
            abort_if($parent === null || $parent->institute_id !== $institute->id || ! $parent->is_custom, 403);
        }

        $name = trim($data['name']);
        InstituteAcademicGroup::create([
            'institute_id' => $institute->id,
            'academic_group_id' => null,
            'class_grade_id' => $parentIsCustomClass ? null : (int) $data['class_grade_id'],
            'institute_class_grade_id' => $parentIsCustomClass ? (int) $data['institute_class_grade_id'] : null,
            'name' => $name,
            'display_order' => (int) ($data['display_order'] ?? 0),
            'status' => true,
            'is_custom' => true,
        ]);

        return redirect(route('settings.academic.index'))->with('status', "Custom group/stream '{$name}' added.");
    }

    public function destroyGroup(Request $request, InstituteAcademicGroup $group): RedirectResponse
    {
        $institute = $this->resolveInstitute($request);
        abort_if($institute === null, 404);

        $group->delete();

        return redirect(route('settings.academic.index'))->with('status', 'Custom group/stream removed.');
    }

    // ------------------------------------------------------------- Internals

    /**
     * Upsert an override row for a global level. enabled=false → row status=false.
     * enabled=true with no custom values → revert (delete the override row);
     * enabled=true with values → create/update the customized row.
     */
    private function setLevelOverride(Institute $institute, AcademicLevel $level, bool $enabled, array $data): void
    {
        $override = InstituteAcademicLevel::query()
            ->where('institute_id', $institute->id)
            ->where('academic_level_id', $level->id)
            ->first();

        if (! $enabled) {
            if ($override === null) {
                InstituteAcademicLevel::create([
                    'institute_id' => $institute->id,
                    'academic_level_id' => $level->id,
                    'education_system_id' => $level->education_system_id,
                    'status' => false,
                    'is_custom' => false,
                ]);
            } else {
                $override->forceFill(['status' => false])->save();
            }

            return;
        }

        $hasCustom = filled($data['name'] ?? null) || filled($data['display_order'] ?? null);
        if (! $hasCustom) {
            if ($override !== null) {
                $override->delete();
            }

            return;
        }

        InstituteAcademicLevel::updateOrCreate(
            ['institute_id' => $institute->id, 'academic_level_id' => $level->id],
            [
                'education_system_id' => $level->education_system_id,
                'name' => filled($data['name'] ?? null) ? trim($data['name']) : null,
                'display_order' => filled($data['display_order'] ?? null) ? (int) $data['display_order'] : null,
                'status' => true,
                'is_custom' => false,
            ]
        );
    }

    private function setClassOverride(Institute $institute, ClassGrade $classGrade, bool $enabled, array $data): void
    {
        $override = InstituteClassGrade::query()
            ->where('institute_id', $institute->id)
            ->where('class_grade_id', $classGrade->id)
            ->first();

        if (! $enabled) {
            if ($override === null) {
                InstituteClassGrade::create([
                    'institute_id' => $institute->id,
                    'class_grade_id' => $classGrade->id,
                    'status' => false,
                    'is_custom' => false,
                ]);
            } else {
                $override->forceFill(['status' => false])->save();
            }

            return;
        }

        $hasCustom = filled($data['name'] ?? null) || filled($data['sequence'] ?? null) || filled($data['display_order'] ?? null);
        if (! $hasCustom) {
            if ($override !== null) {
                $override->delete();
            }

            return;
        }

        InstituteClassGrade::updateOrCreate(
            ['institute_id' => $institute->id, 'class_grade_id' => $classGrade->id],
            [
                'name' => filled($data['name'] ?? null) ? trim($data['name']) : null,
                'sequence' => filled($data['sequence'] ?? null) ? (int) $data['sequence'] : null,
                'display_order' => filled($data['display_order'] ?? null) ? (int) $data['display_order'] : null,
                'status' => true,
                'is_custom' => false,
            ]
        );
    }

    private function setGroupOverride(Institute $institute, AcademicGroup $academicGroup, bool $enabled, array $data): void
    {
        $override = InstituteAcademicGroup::query()
            ->where('institute_id', $institute->id)
            ->where('academic_group_id', $academicGroup->id)
            ->first();

        if (! $enabled) {
            if ($override === null) {
                InstituteAcademicGroup::create([
                    'institute_id' => $institute->id,
                    'academic_group_id' => $academicGroup->id,
                    'status' => false,
                    'is_custom' => false,
                ]);
            } else {
                $override->forceFill(['status' => false])->save();
            }

            return;
        }

        $hasCustom = filled($data['name'] ?? null) || filled($data['display_order'] ?? null);
        if (! $hasCustom) {
            if ($override !== null) {
                $override->delete();
            }

            return;
        }

        InstituteAcademicGroup::updateOrCreate(
            ['institute_id' => $institute->id, 'academic_group_id' => $academicGroup->id],
            [
                'name' => filled($data['name'] ?? null) ? trim($data['name']) : null,
                'display_order' => filled($data['display_order'] ?? null) ? (int) $data['display_order'] : null,
                'status' => true,
                'is_custom' => false,
            ]
        );
    }

    /**
     * A global level may only be used as a parent when it is enabled and part
     * of the institute's country structure.
     */
    private function assertGlobalLevelUsable(Institute $institute, int $globalLevelId): void
    {
        $level = AcademicLevel::query()->find($globalLevelId);
        abort_if($level === null || ! $level->status || $level->country_id !== $institute->country_id, 403);
    }

    /**
     * Resolve the active institute strictly from auth context, never from input.
     */
    private function resolveInstitute(Request $request): ?Institute
    {
        $user = $request->user();

        if ($user instanceof InstituteUser) {
            return Institute::query()->find($user->institute_id);
        }

        if ($user instanceof User) {
            $membership = Workspace::membership();
            if ($membership !== null) {
                $inst = Institute::query()->find($membership->institution_id);
                if (! $inst) {
                    $inst = Institute::withoutGlobalScopes()->find($membership->institution_id);
                }
                if ($inst) {
                    return $inst;
                }
            }
            // Fallback for stale session / tests: first active membership
            $wid = Workspace::id() ?? \App\Support\TenantContext::id();
            if ($wid !== null) {
                $direct = \App\Models\Membership::where('user_id', $user->id)->where('institution_id', $wid)->where('status', 'active')->first();
                if ($direct) {
                    $inst = Institute::query()->find($direct->institution_id);
                    if (! $inst) {
                        $inst = Institute::withoutGlobalScopes()->find($direct->institution_id);
                    }
                    if ($inst) {
                        return $inst;
                    }
                }
            }
            $first = \App\Models\Membership::where('user_id', $user->id)->where('status', 'active')->orderBy('institution_id')->first();
            if ($first) {
                $inst = Institute::query()->find($first->institution_id);
                if (! $inst) {
                    $inst = Institute::withoutGlobalScopes()->find($first->institution_id);
                }
                if ($inst) {
                    return $inst;
                }
            }

            return null;
        }

        return null;
    }
}

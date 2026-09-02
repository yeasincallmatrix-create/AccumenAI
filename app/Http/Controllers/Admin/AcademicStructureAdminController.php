<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AcademicGroup;
use App\Models\AcademicLevel;
use App\Models\ClassGrade;
use App\Models\Country;
use App\Models\EducationSystem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Super Admin manager for the global academic structure:
 *
 *   Country → Education System → Academic Level → Class/Grade → Group/Stream
 *
 * All countries resolved through the shared `countries` table. This area is a
 * definition manager only; institute-level customization lives in the tenant
 * AcademicStructureController behind the `education.manage` permission.
 *
 * Authorization: `auth:platform_admin` route group (implicit superuser).
 */
class AcademicStructureAdminController extends Controller
{
    // ------------------------------------------------------------------ Country

    public function index(Request $request): View
    {
        $query = Country::query()->withCount('educationSystems');

        if ($request->query('q') !== null && trim((string) $request->query('q')) !== '') {
            $query->where('name', 'like', '%'.trim((string) $request->query('q')).'%');
        }

        return view('admin.academic.index', [
            'countries' => $query->orderBy('name')->get(),
            'q' => $request->query('q'),
        ]);
    }

    public function country(Country $country): View
    {
        return view('admin.academic.country', [
            'country' => $country,
            'systems' => $country->educationSystems()->get(),
            'levelCounts' => DB::table('academic_levels')
                ->join('education_systems', 'academic_levels.education_system_id', '=', 'education_systems.id')
                ->where('education_systems.country_id', $country->id)
                ->selectRaw('education_system_id, count(*) as total')
                ->groupBy('education_system_id')
                ->pluck('total', 'education_system_id'),
        ]);
    }

    public function updateCountry(Request $request, Country $country): RedirectResponse
    {
        $data = $request->validate([
            'academic_unit_label' => ['nullable', 'string', 'max:40'],
        ]);

        $country->forceFill([
            'academic_unit_label' => filled($data['academic_unit_label'] ?? null)
                ? trim($data['academic_unit_label'])
                : null,
        ])->save();

        return redirect(route('admin.academic.country', $country))->with('status', 'Academic unit label updated.');
    }

    // ------------------------------------------------------------------ System

    public function storeSystem(Request $request, Country $country): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'code' => ['required', 'string', 'max:60', 'regex:/^[a-z0-9_-]+$/', Rule::unique('education_systems')->where('country_id', $country->id)],
            'display_order' => ['nullable', 'integer', 'min:0'],
        ]);

        EducationSystem::create([
            'country_id' => $country->id,
            'name' => $data['name'],
            'code' => $data['code'],
            'display_order' => (int) ($data['display_order'] ?? 0),
            'status' => true,
        ]);

        return redirect(route('admin.academic.country', $country))->with('status', "Education system '{$data['name']}' added.");
    }

    public function updateSystem(Request $request, EducationSystem $system): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'code' => ['required', 'string', 'max:60', 'regex:/^[a-z0-9_-]+$/', Rule::unique('education_systems')->where('country_id', $system->country_id)->ignore($system->id)],
            'display_order' => ['nullable', 'integer', 'min:0'],
            'status' => ['nullable', 'boolean'],
        ]);

        $system->forceFill([
            'name' => $data['name'],
            'code' => $data['code'],
            'display_order' => (int) ($data['display_order'] ?? 0),
            'status' => ! ($data['status'] ?? true) ? false : ($system->status ?? true),
        ])->save();

        return redirect(route('admin.academic.country', $system->country_id))->with('status', 'Education system updated.');
    }

    public function toggleSystem(Request $request, EducationSystem $system): RedirectResponse
    {
        $system->forceFill(['status' => ! $system->status])->save();

        return redirect(route('admin.academic.country', $system->country_id))
            ->with('status', "Education system '{$system->name}' ".($system->status ? 'enabled' : 'disabled').'.');
    }

    // ------------------------------------------------------------------ Level

    public function system(EducationSystem $system): View
    {
        return view('admin.academic.system', [
            'system' => $system,
            'country' => $system->country,
            'levels' => $system->levels()->get(),
            'classCounts' => DB::table('class_grades')
                ->join('academic_levels', 'class_grades.academic_level_id', '=', 'academic_levels.id')
                ->where('academic_levels.education_system_id', $system->id)
                ->selectRaw('academic_level_id, count(*) as total')
                ->groupBy('academic_level_id')
                ->pluck('total', 'academic_level_id'),
        ]);
    }

    public function storeLevel(Request $request, EducationSystem $system): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'code' => ['required', 'string', 'max:60', 'regex:/^[a-z0-9_-]+$/', Rule::unique('academic_levels')->where('education_system_id', $system->id)],
            'display_order' => ['nullable', 'integer', 'min:0'],
        ]);

        AcademicLevel::create([
            'country_id' => $system->country_id,
            'education_system_id' => $system->id,
            'name' => $data['name'],
            'code' => $data['code'],
            'display_order' => (int) ($data['display_order'] ?? 0),
            'status' => true,
        ]);

        return redirect(route('admin.academic.system', $system))->with('status', "Level '{$data['name']}' added.");
    }

    public function updateLevel(Request $request, AcademicLevel $level): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'code' => ['required', 'string', 'max:60', 'regex:/^[a-z0-9_-]+$/', Rule::unique('academic_levels')->where('education_system_id', $level->education_system_id)->ignore($level->id)],
            'display_order' => ['nullable', 'integer', 'min:0'],
            'status' => ['nullable', 'boolean'],
        ]);

        $level->forceFill([
            'name' => $data['name'],
            'code' => $data['code'],
            'display_order' => (int) ($data['display_order'] ?? 0),
            'status' => ! ($data['status'] ?? true) ? false : ($level->status ?? true),
        ])->save();

        return redirect(route('admin.academic.system', $level->education_system_id))->with('status', 'Level updated.');
    }

    public function toggleLevel(Request $request, AcademicLevel $level): RedirectResponse
    {
        $level->forceFill(['status' => ! $level->status])->save();

        return redirect(route('admin.academic.system', $level->education_system_id))
            ->with('status', "Level '{$level->name}' ".($level->status ? 'enabled' : 'disabled').'.');
    }

    // ------------------------------------------------------------------ Class / Grade

    public function level(AcademicLevel $level): View
    {
        return view('admin.academic.level', [
            'level' => $level,
            'system' => $level->educationSystem,
            'country' => $level->country,
            'classes' => $level->classes()->get(),
            'groupCounts' => DB::table('academic_groups')
                ->join('class_grades', 'academic_groups.class_grade_id', '=', 'class_grades.id')
                ->where('class_grades.academic_level_id', $level->id)
                ->selectRaw('class_grade_id, count(*) as total')
                ->groupBy('class_grade_id')
                ->pluck('total', 'class_grade_id'),
        ]);
    }

    public function storeClass(Request $request, AcademicLevel $level): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'code' => ['required', 'string', 'max:60', 'regex:/^[a-z0-9_-]+$/', Rule::unique('class_grades')->where('academic_level_id', $level->id)],
            'sequence' => ['nullable', 'integer', 'min:1'],
            'display_order' => ['nullable', 'integer', 'min:0'],
        ]);

        ClassGrade::create([
            'country_id' => $level->country_id,
            'education_system_id' => $level->education_system_id,
            'academic_level_id' => $level->id,
            'name' => $data['name'],
            'code' => $data['code'],
            'sequence' => filled($data['sequence'] ?? null) ? (int) $data['sequence'] : null,
            'display_order' => (int) ($data['display_order'] ?? 0),
            'status' => true,
        ]);

        return redirect(route('admin.academic.level', $level))->with('status', "Class/grade '{$data['name']}' added.");
    }

    public function updateClass(Request $request, ClassGrade $classGrade): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'code' => ['required', 'string', 'max:60', 'regex:/^[a-z0-9_-]+$/', Rule::unique('class_grades')->where('academic_level_id', $classGrade->academic_level_id)->ignore($classGrade->id)],
            'sequence' => ['nullable', 'integer', 'min:1'],
            'display_order' => ['nullable', 'integer', 'min:0'],
            'status' => ['nullable', 'boolean'],
        ]);

        $classGrade->forceFill([
            'name' => $data['name'],
            'code' => $data['code'],
            'sequence' => filled($data['sequence'] ?? null) ? (int) $data['sequence'] : null,
            'display_order' => (int) ($data['display_order'] ?? 0),
            'status' => ! ($data['status'] ?? true) ? false : ($classGrade->status ?? true),
        ])->save();

        return redirect(route('admin.academic.level', $classGrade->academic_level_id))->with('status', 'Class/grade updated.');
    }

    public function toggleClass(Request $request, ClassGrade $classGrade): RedirectResponse
    {
        $classGrade->forceFill(['status' => ! $classGrade->status])->save();

        return redirect(route('admin.academic.level', $classGrade->academic_level_id))
            ->with('status', "Class/grade '{$classGrade->name}' ".($classGrade->status ? 'enabled' : 'disabled').'.');
    }

    // ------------------------------------------------------------------ Group / Stream

    public function classGrade(ClassGrade $classGrade): View
    {
        return view('admin.academic.classGrade', [
            'classGrade' => $classGrade,
            'level' => $classGrade->academicLevel,
            'system' => $classGrade->educationSystem,
            'country' => $classGrade->country,
            'groups' => $classGrade->groups()->get(),
        ]);
    }

    public function storeGroup(Request $request, ClassGrade $classGrade): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'code' => ['required', 'string', 'max:60', 'regex:/^[a-z0-9_-]+$/', Rule::unique('academic_groups')->where('class_grade_id', $classGrade->id)],
            'display_order' => ['nullable', 'integer', 'min:0'],
        ]);

        AcademicGroup::create([
            'country_id' => $classGrade->country_id,
            'education_system_id' => $classGrade->education_system_id,
            'academic_level_id' => $classGrade->academic_level_id,
            'class_grade_id' => $classGrade->id,
            'name' => $data['name'],
            'code' => $data['code'],
            'display_order' => (int) ($data['display_order'] ?? 0),
            'status' => true,
        ]);

        return redirect(route('admin.academic.classGrade', $classGrade))->with('status', "Group/stream '{$data['name']}' added.");
    }

    public function updateGroup(Request $request, AcademicGroup $group): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'code' => ['required', 'string', 'max:60', 'regex:/^[a-z0-9_-]+$/', Rule::unique('academic_groups')->where('class_grade_id', $group->class_grade_id)->ignore($group->id)],
            'display_order' => ['nullable', 'integer', 'min:0'],
            'status' => ['nullable', 'boolean'],
        ]);

        $group->forceFill([
            'name' => $data['name'],
            'code' => $data['code'],
            'display_order' => (int) ($data['display_order'] ?? 0),
            'status' => ! ($data['status'] ?? true) ? false : ($group->status ?? true),
        ])->save();

        return redirect(route('admin.academic.classGrade', $group->class_grade_id))->with('status', 'Group/stream updated.');
    }

    public function toggleGroup(Request $request, AcademicGroup $group): RedirectResponse
    {
        $group->forceFill(['status' => ! $group->status])->save();

        return redirect(route('admin.academic.classGrade', $group->class_grade_id))
            ->with('status', "Group/stream '{$group->name}' ".($group->status ? 'enabled' : 'disabled').'.');
    }
}

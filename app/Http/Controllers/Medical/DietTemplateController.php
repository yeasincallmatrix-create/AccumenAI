<?php

namespace App\Http\Controllers\Medical;

use App\Models\Medical\ClinicalAuditLog;
use App\Models\Medical\DietPlan;
use App\Models\Medical\DietTemplate;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class DietTemplateController extends MedicalController implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:medical.diet.view', only: ['index', 'show']),
            new Middleware('permission:medical.diet.template.manage', only: ['create', 'store', 'edit', 'update', 'destroy']),
        ];
    }

    public function index(Request $request)
    {
        $instituteId = $this->instituteId();
        $query = DietTemplate::forInstitute($instituteId);

        if ($request->filled('diet_type')) {
            $query->byType($request->diet_type);
        }
        if ($request->filled('search')) {
            $query->where('diet_templates.name', 'like', "%{$request->search}%");
        }

        $templates = $query->orderBy('diet_templates.name')->paginate(25)->withQueryString();
        $types = DietPlan::DIET_TYPES;

        return view('medical.diet.templates.index', compact('templates', 'types'));
    }

    public function create()
    {
        $types = DietPlan::DIET_TYPES;

        return view('medical.diet.templates.create', compact('types'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:200',
            'diet_type' => 'required|string|in:' . implode(',', array_keys(DietPlan::DIET_TYPES)),
            'description' => 'nullable|string',
            'meal_items' => 'nullable|string',
            'total_calories' => 'nullable|integer|min:0',
            'is_global' => 'nullable|boolean',
        ]);

        $template = DietTemplate::create([
            'institute_id' => $request->boolean('is_global') ? null : $this->instituteId(),
            'name' => $request->name,
            'diet_type' => $request->diet_type,
            'description' => $request->description,
            'meal_items' => $request->filled('meal_items')
                ? array_map('trim', explode("\n", $request->meal_items))
                : null,
            'total_calories' => $request->total_calories,
            'is_active' => true,
        ]);

        ClinicalAuditLog::record($template, 'created');

        return redirect()
            ->route('medical.diet.templates.index')
            ->with('status', "Template created: {$template->name}.");
    }

    public function show(DietTemplate $template)
    {
        if ($template->institute_id !== null) {
            $this->ensureSameInstitute($template, 'template');
        }

        return view('medical.diet.templates.show', compact('template'));
    }

    public function edit(DietTemplate $template)
    {
        if ($template->institute_id !== null) {
            $this->ensureSameInstitute($template, 'template');
        }

        return view('medical.diet.templates.edit', [
            'template' => $template,
            'types' => DietPlan::DIET_TYPES,
        ]);
    }

    public function update(Request $request, DietTemplate $template)
    {
        if ($template->institute_id !== null) {
            $this->ensureSameInstitute($template, 'template');
        }

        $request->validate([
            'name' => 'required|string|max:200',
            'diet_type' => 'required|string|in:' . implode(',', array_keys(DietPlan::DIET_TYPES)),
            'description' => 'nullable|string',
            'meal_items' => 'nullable|string',
            'total_calories' => 'nullable|integer|min:0',
        ]);

        $original = ClinicalAuditLog::snapshot($template);
        $template->update([
            'name' => $request->name,
            'diet_type' => $request->diet_type,
            'description' => $request->description,
            'meal_items' => $request->filled('meal_items')
                ? array_map('trim', explode("\n", $request->meal_items))
                : null,
            'total_calories' => $request->total_calories,
            'is_active' => $request->boolean('is_active', true),
        ]);

        [$old, $new] = ClinicalAuditLog::diff($original, ClinicalAuditLog::snapshot($template->refresh()));
        if ($old !== [] || $new !== []) {
            ClinicalAuditLog::record($template, 'updated', ['old' => $old, 'new' => $new]);
        }

        return redirect()
            ->route('medical.diet.templates.index')
            ->with('status', 'Template updated.');
    }

    public function destroy(DietTemplate $template)
    {
        if ($template->institute_id !== null) {
            $this->ensureSameInstitute($template, 'template');
        }

        ClinicalAuditLog::record($template, 'deleted');
        $template->delete();

        return redirect()
            ->route('medical.diet.templates.index')
            ->with('status', 'Template deleted.');
    }
}

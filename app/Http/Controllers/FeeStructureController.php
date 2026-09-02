<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesInstitute;
use App\Models\AcademicYear;
use App\Models\Batch;
use App\Models\FeeHead;
use App\Models\FeeStructure;
use App\Models\InstituteCourse;
use App\Services\Education\FeeHeadService;
use App\Services\Education\FeeStructureService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Education fee catalogs (Step 37): fee heads and fee structures (targets +
 * items + installment plan). Reads need accounts.view, writes accounts.manage.
 */
class FeeStructureController extends Controller
{
    use ResolvesInstitute;

    public function __construct(
        private readonly FeeHeadService $heads,
        private readonly FeeStructureService $structures,
    ) {}

    // ------------------------------------------------------------- Fee heads

    public function feeHeadsIndex(Request $request): View
    {
        $institute = $this->requireInstitute($request);

        return view('institute.finance.education.fee-heads.index', [
            'institute' => $institute,
            'feeHeads' => FeeHead::query()
                ->where('institute_id', $institute->id)
                ->with('incomeAccount')
                ->orderBy('type')
                ->orderBy('name')
                ->get(),
            'types' => FeeHead::TYPES,
        ]);
    }

    public function feeHeadsStore(Request $request): RedirectResponse
    {
        $institute = $this->requireInstitute($request);

        $this->heads->create(
            $institute->id,
            $this->actingBranchId($request),
            $this->feeHeadValidated($request),
            (int) $this->actorId($request),
        );

        return redirect()->route('finance.education.fee-heads.index')
            ->with('status', 'Fee head created.');
    }

    public function feeHeadsUpdate(Request $request, FeeHead $feeHead): RedirectResponse
    {
        $this->requireInstitute($request);

        $this->heads->update(
            $feeHead,
            $this->feeHeadValidated($request),
            (int) $this->actorId($request),
        );

        return redirect()->route('finance.education.fee-heads.index')
            ->with('status', 'Fee head updated.');
    }

    public function feeHeadsToggle(Request $request, FeeHead $feeHead): RedirectResponse
    {
        $this->requireInstitute($request);

        $this->heads->toggle($feeHead);

        return redirect()->route('finance.education.fee-heads.index')
            ->with('status', $feeHead->is_active ? 'Fee head activated.' : 'Fee head deactivated.');
    }

    public function feeHeadsDestroy(Request $request, FeeHead $feeHead): RedirectResponse
    {
        $this->requireInstitute($request);

        $this->heads->destroy($feeHead);

        return redirect()->route('finance.education.fee-heads.index')
            ->with('status', 'Fee head deleted.');
    }

    // ------------------------------------------------------- Fee structures

    public function index(Request $request): View
    {
        $institute = $this->requireInstitute($request);

        return view('institute.finance.education.fee-structures.index', [
            'institute' => $institute,
            'structures' => FeeStructure::query()
                ->where('institute_id', $institute->id)
                ->with(['course', 'batch', 'academicYear', 'items.feeHead'])
                ->orderByDesc('id')
                ->get(),
        ]);
    }

    public function create(Request $request): View
    {
        $institute = $this->requireInstitute($request);

        return view('institute.finance.education.fee-structures.form', [
            'institute' => $institute,
            'structure' => null,
            'courses' => $this->assignedCourses($institute->id),
            'batches' => Batch::query()->with('course')->orderBy('name')->get(),
            'academicYears' => AcademicYear::query()->orderByDesc('id')->get(),
            'feeHeads' => FeeHead::query()->where('is_active', true)->orderBy('type')->orderBy('name')->get(),
            'statuses' => ['draft', 'active', 'archived'],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $institute = $this->requireInstitute($request);

        $structure = $this->structures->create(
            $institute->id,
            $this->actingBranchId($request),
            $this->structureValidated($request),
            (int) $this->actorId($request),
        );

        return redirect()
            ->route('finance.education.fee-structures.index')
            ->with('status', 'Fee structure "'.$structure->name.'" created.');
    }

    public function edit(Request $request, FeeStructure $structure): View
    {
        $institute = $this->requireInstitute($request);

        return view('institute.finance.education.fee-structures.form', [
            'institute' => $institute,
            'structure' => $structure->load(['items.feeHead']),
            'courses' => $this->assignedCourses($institute->id),
            'batches' => Batch::query()->with('course')->orderBy('name')->get(),
            'academicYears' => AcademicYear::query()->orderByDesc('id')->get(),
            'feeHeads' => FeeHead::query()->where('is_active', true)->orderBy('type')->orderBy('name')->get(),
            'statuses' => ['draft', 'active', 'archived'],
        ]);
    }

    public function update(Request $request, FeeStructure $structure): RedirectResponse
    {
        $this->requireInstitute($request);

        $structure = $this->structures->update(
            $structure,
            $this->structureValidated($request),
            (int) $this->actorId($request),
        );

        return redirect()
            ->route('finance.education.fee-structures.index')
            ->with('status', 'Fee structure "'.$structure->name.'" updated.');
    }

    public function destroy(Request $request, FeeStructure $structure): RedirectResponse
    {
        $this->requireInstitute($request);

        $this->structures->destroy($structure);

        return redirect()
            ->route('finance.education.fee-structures.index')
            ->with('status', 'Fee structure deleted.');
    }

    // ------------------------------------------------------------- Internals

    /**
     * @return array<string, mixed>
     */
    private function feeHeadValidated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'code' => ['nullable', 'string', 'max:40'],
            'type' => ['required', Rule::in(FeeHead::TYPES)],
            'default_amount' => ['nullable', 'numeric', 'min:0'],
            'income_coa_id' => ['nullable', 'integer'],
            'description' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function structureValidated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'course_id' => ['nullable', 'integer'],
            'batch_id' => ['nullable', 'integer'],
            'academic_year_id' => ['nullable', 'integer'],
            'installments_count' => ['required', 'integer', 'min:1', 'max:12'],
            'installments_interval_days' => ['required', 'integer', 'min:0', 'max:730'],
            'status' => ['required', Rule::in(['draft', 'active', 'archived'])],
            'items' => ['required', 'array', 'min:1'],
            'items.*.fee_head_id' => ['required', 'integer'],
            'items.*.amount' => ['required', 'numeric', 'gt:0'],
            'items.*.is_optional' => ['nullable', 'boolean'],
        ]);

        $data['items'] = array_values(array_filter(
            array_map(function ($item) {
                $item['fee_head_id'] = (int) $item['fee_head_id'];
                $item['amount'] = (float) $item['amount'];
                $item['is_optional'] = (bool) ($item['is_optional'] ?? false);

                return $item;
            }, $data['items']),
            fn ($item) => $item['amount'] > 0 && $item['fee_head_id'] > 0,
        ));

        if ($data['items'] === []) {
            throw ValidationException::withMessages([
                'items' => 'At least one valid fee item is required.',
            ]);
        }

        return $data;
    }

    private function assignedCourses(int $instituteId): Collection
    {
        return InstituteCourse::query()
            ->where('institute_id', $instituteId)
            ->with('course')
            ->get()
            ->map->course
            ->filter(fn ($course) => $course !== null)
            ->values();
    }
}

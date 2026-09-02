<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\CountryBatchRequest;
use App\Services\CountryBatchService;
use Illuminate\Http\JsonResponse;

class CountryBatchController extends Controller
{
    public function __construct(private readonly CountryBatchService $service) {}

    public function __invoke(CountryBatchRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $countryIds = $validated['country_ids'];
        $action = $validated['action'];

        $summary = match ($action) {
            'enable' => $this->service->enableCountries($countryIds),
            'disable' => $this->service->disableCountries($countryIds),
            'assign_grade_scale' => $this->service->assignGradeScales($countryIds),
            'assign_academic_structure' => $this->service->assignAcademicStructures($countryIds),
            'assign_default_modules' => $this->service->assignDefaultModules($countryIds),
            'sync_all' => $this->service->syncAll($countryIds),
            default => ['total' => 0, 'success' => 0, 'failed' => 0, 'skipped' => 0, 'details' => []],
        };

        return response()->json([
            'message' => 'Batch action completed',
            'action' => $action,
            'summary' => $summary,
        ]);
    }
}

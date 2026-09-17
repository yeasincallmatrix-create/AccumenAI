<?php

namespace App\Http\Controllers\Medical;

use App\Models\Medical\DentalChart;
use App\Models\Medical\Patient;
use App\Services\Medical\DentalService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class DentalChartController extends MedicalController implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:medical.dental.view', only: ['index', 'show']),
            new Middleware('permission:medical.dental.chart.edit', only: ['save', 'updateTooth']),
        ];
    }

    public function __construct(
        private readonly DentalService $dentalService,
    ) {}

    /**
     * Patient picker for dental charts (institute + branch scoped).
     *
     * Previously the sidebar linked to a hardcoded patient id (1), which 404s
     * whenever that patient doesn't exist in the current institute.
     */
    public function index(Request $request)
    {
        $instituteId = $this->instituteId();

        $query = Patient::where('institute_id', $instituteId)->active()->patients();
        $this->scopeBranch($query);

        if ($request->filled('search')) {
            $query->search($request->search);
        }

        $patients = $query->orderBy('first_name')->orderBy('last_name')
            ->paginate(25)->withQueryString();

        return view('medical.dental.charts', compact('patients'));
    }

    public function show(Patient $patient)
    {
        $this->ensureSameInstitute($patient, 'patient');
        $instituteId = $this->instituteId();

        $chart = $this->dentalService->getOrCreateChart($instituteId, $patient->id, auth()->id());
        $chart->load(['dentist', 'assessedBy', 'procedures' => function ($q) {
            $q->orderByDesc('performed_at');
        }]);

        $toothConditions = DentalChart::TOOTH_CONDITIONS;
        $oralHygiene = DentalChart::ORAL_HYGIENE;

        return view('medical.dental.chart', compact('chart', 'patient', 'toothConditions', 'oralHygiene'));
    }

    public function save(Request $request, Patient $patient)
    {
        $this->ensureSameInstitute($patient, 'patient');

        $request->validate([
            'general_notes' => 'nullable|string|max:2000',
            'oral_hygiene' => 'nullable|string|in:' . implode(',', array_keys(DentalChart::ORAL_HYGIENE)),
            'dentist_id' => 'nullable|exists:users,id',
        ]);

        $chart = $this->dentalService->getOrCreateChart($this->instituteId(), $patient->id);
        $chart->update([
            'general_notes' => $request->general_notes,
            'oral_hygiene' => $request->oral_hygiene,
            'dentist_id' => $request->dentist_id ?? $chart->dentist_id,
            'last_assessed_at' => now(),
            'assessed_by' => auth()->id(),
        ]);

        return redirect()
            ->route('medical.dental.chart.show', $patient)
            ->with('status', 'Dental chart updated.');
    }

    public function updateTooth(Request $request, Patient $patient)
    {
        $this->ensureSameInstitute($patient, 'patient');

        $request->validate([
            'tooth_number' => 'required|string|in:' . implode(',', DentalChart::TOOTH_NUMBERS),
            'condition' => 'required|string|in:' . implode(',', array_keys(DentalChart::TOOTH_CONDITIONS)),
            'notes' => 'nullable|string|max:500',
        ]);

        $chart = $this->dentalService->getOrCreateChart($this->instituteId(), $patient->id);
        $this->dentalService->updateTooth($chart, $request->tooth_number, $request->condition, $request->notes);

        return response()->json(['status' => 'ok', 'message' => 'Tooth updated.']);
    }
}

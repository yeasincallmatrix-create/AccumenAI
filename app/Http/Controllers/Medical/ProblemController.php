<?php

namespace App\Http\Controllers\Medical;

use App\Http\Requests\Medical\ProblemRequest;
use App\Models\Medical\ClinicalAuditLog;
use App\Models\Medical\Encounter;
use App\Models\Medical\EncounterDiagnosis;
use App\Models\Medical\Patient;
use App\Models\Medical\PatientProblem;
use App\Models\Membership;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;

/**
 * Phase 17 — Longitudinal problem list (documentation only).
 *
 * Problems are explicit clinician records, never auto-derived from
 * diagnoses: no conversion, no merging, no inference. Source links are
 * evidence/context and never mutate the source diagnosis or encounter.
 * Lifecycle moves through explicit actions (inactivate / reactivate /
 * resolve / link / unlink); every mutation is tenant + patient fenced
 * and audit-logged. There is no delete path.
 */
class ProblemController extends MedicalController implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:medical_problems.create', only: ['store']),
            new Middleware('permission:medical_problems.edit', only: [
                'inactivate', 'reactivate', 'resolve', 'link', 'unlink',
            ]),
        ];
    }

    public function store(ProblemRequest $request, Patient $patient)
    {
        $instituteId = $this->instituteId();
        $this->ensureSameInstitute($patient, 'patient');
        if (! $this->mayActOnPatient($patient)) {
            abort(403, 'You do not have permission to act for this patient.');
        }

        $data = $request->validated();
        $sources = $this->resolveSources($patient, $data);
        if ($sources instanceof \Illuminate\Http\RedirectResponse) {
            return $sources;
        }

        $problem = DB::transaction(function () use ($instituteId, $patient, $data, $sources) {
            return PatientProblem::create([
                'institute_id' => $instituteId,
                'patient_id' => $patient->id,
                'encounter_id' => $sources['encounter_id'],
                'encounter_diagnosis_id' => $sources['encounter_diagnosis_id'],
                'recorded_by' => $this->actorId(),
                'label' => $data['label'],
                'problem_type' => $data['problem_type'],
                'source' => $data['source'] ?? 'free_text',
                // Allowlist-only resolution; unknown systems stay UNRESOLVED.
                'mapping_status' => EncounterDiagnosis::resolvesCode($data['code_system'] ?? null)
                    ? EncounterDiagnosis::MAPPING_RESOLVED
                    : EncounterDiagnosis::MAPPING_UNRESOLVED,
                'code' => $data['code'] ?? null,
                'code_system' => $data['code_system'] ?? null,
                'onset_date' => $data['onset_date'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);
        });

        ClinicalAuditLog::record($problem, 'problem_created', [
            'new' => [
                'label' => $problem->label,
                'problem_type' => $problem->problem_type,
                'status' => $problem->status,
                'encounter_id' => $problem->encounter_id,
                'encounter_diagnosis_id' => $problem->encounter_diagnosis_id,
            ],
        ]);

        return redirect()->back()->with('status', 'Problem recorded.');
    }

    public function inactivate(ProblemRequest $request, PatientProblem $problem)
    {
        return $this->transition($request, $problem, PatientProblem::STATUS_INACTIVE, 'problem_status_changed');
    }

    public function reactivate(ProblemRequest $request, PatientProblem $problem)
    {
        return $this->transition($request, $problem, PatientProblem::STATUS_ACTIVE, 'problem_status_changed');
    }

    public function resolve(ProblemRequest $request, PatientProblem $problem)
    {
        return $this->transition(
            $request,
            $problem,
            PatientProblem::STATUS_RESOLVED,
            'problem_resolved',
            $request->validated()['resolved_date'] ?? null
        );
    }

    public function link(ProblemRequest $request, PatientProblem $problem)
    {
        $patient = $this->fencedPatient($problem);
        $data = $request->validated();
        if (empty($data['encounter_id']) && empty($data['encounter_diagnosis_id'])) {
            return redirect()->back()->with('error', 'Select an encounter or diagnosis to link.');
        }
        $sources = $this->resolveSources($patient, $data);
        if ($sources instanceof \Illuminate\Http\RedirectResponse) {
            return $sources;
        }

        $old = ['encounter_id' => $problem->encounter_id, 'encounter_diagnosis_id' => $problem->encounter_diagnosis_id];
        $problem->update([
            'encounter_id' => $sources['encounter_id'],
            'encounter_diagnosis_id' => $sources['encounter_diagnosis_id'],
        ]);
        ClinicalAuditLog::record($problem, 'problem_linked', [
            'old' => $old,
            'new' => ['encounter_id' => $problem->encounter_id, 'encounter_diagnosis_id' => $problem->encounter_diagnosis_id],
            'reason' => $data['reason'] ?? null,
        ]);

        return redirect()->back()->with('status', 'Problem linked.');
    }

    public function unlink(ProblemRequest $request, PatientProblem $problem)
    {
        $this->fencedPatient($problem);
        $data = $request->validated();

        $old = ['encounter_id' => $problem->encounter_id, 'encounter_diagnosis_id' => $problem->encounter_diagnosis_id];
        $problem->update(['encounter_id' => null, 'encounter_diagnosis_id' => null]);
        ClinicalAuditLog::record($problem, 'problem_unlinked', [
            'old' => $old,
            'new' => ['encounter_id' => null, 'encounter_diagnosis_id' => null],
            'reason' => $data['reason'] ?? null,
        ]);

        return redirect()->back()->with('status', 'Problem unlinked (source records untouched).');
    }

    private function transition(ProblemRequest $request, PatientProblem $problem, string $status, string $action, ?string $resolvedDate = null)
    {
        $this->fencedPatient($problem);
        $data = $request->validated();

        try {
            DB::transaction(function () use ($problem, $status, $resolvedDate) {
                $problem->lockForUpdate()->first();
                $problem->refresh()->transitionTo($status, $resolvedDate);
            });
        } catch (\RuntimeException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        ClinicalAuditLog::record($problem, $action, [
            'old' => ['status' => $problem->getOriginal('status')],
            'new' => ['status' => $problem->status, 'resolved_date' => $problem->resolved_date?->format('Y-m-d')],
            'reason' => $data['reason'] ?? null,
        ]);

        return redirect()->back()->with('status', 'Problem updated.');
    }

    /**
     * Establish problem → institute → patient → authorized actor.
     */
    private function fencedPatient(PatientProblem $problem): Patient
    {
        $this->ensureSameInstitute($problem, 'problem');
        $patient = $problem->patient;
        if (! $patient) {
            abort(404);
        }
        $this->ensureSameInstitute($patient, 'patient');
        $this->ensurePatientVisible($patient);

        return $patient;
    }

    /**
     * Validate optional source links: same institute, same patient, and a
     * diagnosis that belongs to the linked encounter. Returns ids or a
     * redirect-back error response.
     */
    private function resolveSources(Patient $patient, array $data): array|\Illuminate\Http\RedirectResponse
    {
        $instituteId = $this->instituteId();
        $encounterId = null;
        $diagnosisId = null;

        if (! empty($data['encounter_diagnosis_id'])) {
            $diagnosis = EncounterDiagnosis::where('institute_id', $instituteId)
                ->find($data['encounter_diagnosis_id']);
            if (! $diagnosis) {
                return redirect()->back()->with('error', 'The selected diagnosis is not available.')->withInput();
            }
            $diagnosis->loadMissing('encounter');
            if (! $diagnosis->encounter
                || (int) $diagnosis->encounter->patient_id !== (int) $patient->id
                || (int) $diagnosis->encounter->institute_id !== (int) $instituteId) {
                return redirect()->back()
                    ->with('error', 'The selected diagnosis belongs to a different patient.')
                    ->withInput();
            }
            $diagnosisId = $diagnosis->id;
            $encounterId = $diagnosis->encounter_id;
            // Phase 18: source context follows the encounter's branch fence.
            $this->ensureBranchAccess($diagnosis->encounter, 'branch_id', 'encounter');
        }

        if (! empty($data['encounter_id'])) {
            $encounter = Encounter::where('institute_id', $instituteId)->find($data['encounter_id']);
            if (! $encounter || (int) $encounter->patient_id !== (int) $patient->id) {
                return redirect()->back()
                    ->with('error', 'The selected encounter belongs to a different patient.')
                    ->withInput();
            }
            if ($diagnosisId !== null && (int) $encounter->id !== (int) $encounterId) {
                return redirect()->back()
                    ->with('error', 'The diagnosis does not belong to the selected encounter.')
                    ->withInput();
            }
            $this->ensureBranchAccess($encounter, 'branch_id', 'encounter');
            $encounterId = $encounter->id;
        }

        return ['encounter_id' => $encounterId, 'encounter_diagnosis_id' => $diagnosisId];
    }

    private function actorId(): ?int
    {
        $staff = auth('institute_user')->user() ?? auth('web')->user() ?? auth()->user();

        return $staff?->getKey();
    }
}

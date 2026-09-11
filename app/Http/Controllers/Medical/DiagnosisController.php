<?php

namespace App\Http\Controllers\Medical;

use App\Http\Requests\Medical\DiagnosisRequest;
use App\Models\Medical\ClinicalAuditLog;
use App\Models\Medical\Encounter;
use App\Models\Medical\EncounterDiagnosis;
use Illuminate\Database\QueryException;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;

/**
 * Phase 15 — Encounter diagnosis documentation.
 *
 * Every diagnosis is explicitly entered by a clinician; the controller
 * never infers codes, mappings or classifications. Codes are stored only
 * as clinician-supplied text, and mapping_status resolves ONLY against
 * RECOGNIZED_CODE_SYSTEMS (empty until an authoritative terminology
 * source exists) — raw labels otherwise stay UNRESOLVED.
 *
 * Open encounters accept add/remove directly. Closed (completed or
 * cancelled) encounters require an amendment reason, and every mutation
 * is audit-logged with before/after values. Removal preserves the row.
 */
class DiagnosisController extends MedicalController implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:medical_diagnoses.view', only: ['index']),
            new Middleware('permission:medical_diagnoses.create', only: ['store']),
            new Middleware('permission:medical_diagnoses.remove', only: ['remove']),
        ];
    }

    /**
     * Tenant-scoped, bounded diagnosis search for the encounter form.
     * Reuses the clinician's own prior labels only (never a global
     * dictionary, never cross-institute) to speed re-entry without
     * fabricating terminology. Resists wildcard abuse: short queries
     * return nothing, LIKE escapes applied, hard limit.
     */
    public function index(Encounter $encounter)
    {
        $this->ensureSameInstitute($encounter, 'encounter');
        $this->ensureDoctorOwns($encounter, 'doctor_id', 'encounter');

        $term = trim((string) request()->query('q', ''));
        if (mb_strlen($term) < 3) {
            return response()->json([]);
        }

        $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], mb_substr($term, 0, 60)).'%';

        $labels = EncounterDiagnosis::where('institute_id', $this->instituteId())
            ->where('label', 'like', $like)
            ->orderByDesc('id')
            ->limit(10)
            ->pluck('label')
            ->unique()
            ->values();

        return response()->json($labels);
    }

    public function store(DiagnosisRequest $request, Encounter $encounter)
    {
        $instituteId = $this->instituteId();
        $this->ensureSameInstitute($encounter, 'encounter');
        $this->ensureDoctorOwns($encounter, 'doctor_id', 'encounter');
        // Phase 18: diagnoses derive branch from their encounter.
        $this->ensureBranchAccess($encounter, 'branch_id', 'encounter');

        $data = $request->validated();
        $amending = ! $encounter->isEditable();
        if ($amending && empty($data['reason'])) {
            return redirect()->back()
                ->with('error', 'This encounter is closed. Provide an amendment reason to add a diagnosis.')
                ->withInput();
        }

        $data['institute_id'] = $instituteId;
        $data['encounter_id'] = $encounter->id;
        $data['recorded_by'] = $this->actorId();
        $data['source'] ??= EncounterDiagnosis::SOURCE_FREE_TEXT;
        // Resolution is allowlist-only; unknown systems stay UNRESOLVED.
        $data['mapping_status'] = EncounterDiagnosis::resolvesCode($data['code_system'] ?? null)
            ? EncounterDiagnosis::MAPPING_RESOLVED
            : EncounterDiagnosis::MAPPING_UNRESOLVED;
        $reason = $data['reason'] ?? null;
        unset($data['reason']);

        try {
            $diagnosis = DB::transaction(function () use ($data, $encounter) {
                // Re-check inside the transaction so concurrent requests
                // serialize on the encounter row instead of double-inserting.
                $encounter->lockForUpdate()->first();
                if (EncounterDiagnosis::where('encounter_id', $encounter->id)
                    ->where('label', $data['label'])
                    ->where('status', EncounterDiagnosis::STATUS_ACTIVE)
                    ->exists()) {
                    throw new \RuntimeException('This diagnosis is already recorded for the encounter.');
                }

                return EncounterDiagnosis::create($data);
            });
        } catch (QueryException $e) {
            // Unique backstop (encounter_id, label, status) won the race.
            return redirect()->back()
                ->with('error', 'This diagnosis is already recorded for the encounter.')
                ->withInput();
        } catch (\RuntimeException $e) {
            return redirect()->back()->with('error', $e->getMessage())->withInput();
        }

        ClinicalAuditLog::record($diagnosis, $amending ? 'diagnosis_added_completed' : 'diagnosis_added', [
            'new' => [
                'label' => $diagnosis->label,
                'diagnosis_type' => $diagnosis->diagnosis_type,
                'source' => $diagnosis->source,
                'mapping_status' => $diagnosis->mapping_status,
            ],
            'reason' => $reason,
        ]);

        return redirect()->route('medical.encounters.show', $encounter)
            ->with('status', 'Diagnosis recorded.');
    }

    public function remove(DiagnosisRequest $request, EncounterDiagnosis $diagnosis)
    {
        $diagnosis->loadMissing('encounter');
        $encounter = $diagnosis->encounter;
        if (! $encounter) {
            abort(404);
        }
        $this->ensureSameInstitute($diagnosis, 'diagnosis');
        $this->ensureSameInstitute($encounter, 'encounter');
        $this->ensureDoctorOwns($encounter, 'doctor_id', 'encounter');
        $this->ensureBranchAccess($encounter, 'branch_id', 'encounter');

        $data = $request->validated();
        $amending = ! $encounter->isEditable();
        if ($amending && empty($data['reason'])) {
            return redirect()->back()
                ->with('error', 'This encounter is closed. Provide an amendment reason to remove a diagnosis.');
        }

        try {
            DB::transaction(function () use ($diagnosis) {
                $diagnosis->lockForUpdate()->first();
                $diagnosis->refresh()->markRemoved();
            });
        } catch (\RuntimeException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        ClinicalAuditLog::record($diagnosis, $amending ? 'diagnosis_removed_completed' : 'diagnosis_removed', [
            'old' => ['label' => $diagnosis->label, 'status' => EncounterDiagnosis::STATUS_ACTIVE],
            'new' => ['status' => EncounterDiagnosis::STATUS_REMOVED],
            'reason' => $data['reason'] ?? null,
        ]);

        return redirect()->route('medical.encounters.show', $encounter)
            ->with('status', 'Diagnosis removed (history preserved).');
    }

    private function actorId(): ?int
    {
        $staff = auth('institute_user')->user() ?? auth('web')->user() ?? auth()->user();

        return $staff?->getKey();
    }
}

<?php

namespace App\Http\Controllers\Medical;

use App\Http\Requests\Medical\FollowUpRequest;
use App\Models\Medical\ClinicalAuditLog;
use App\Models\Medical\Encounter;
use App\Models\Medical\FollowUp;
use App\Models\Medical\Patient;
use App\Models\Medical\PatientProblem;
use App\Models\Membership;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;

/**
 * Phase 17 — Structured clinical follow-up (planning record only).
 *
 * Follow-ups record that clinical follow-up is planned; they never book
 * appointments and never duplicate scheduling. Lifecycle is
 * planned→completed/cancelled through explicit actions; terminal states
 * are final and rows persist. Cancellation requires a reason.
 */
class FollowUpController extends MedicalController implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:medical_followups.create', only: ['store']),
            new Middleware('permission:medical_followups.edit', only: ['complete', 'cancel']),
        ];
    }

    public function store(FollowUpRequest $request, Patient $patient)
    {
        $instituteId = $this->instituteId();
        $this->ensureSameInstitute($patient, 'patient');
        if (! $this->mayActOnPatient($patient)) {
            abort(403, 'You do not have permission to act for this patient.');
        }

        $data = $request->validated();
        $links = $this->resolveLinks($patient, $data);
        if ($links instanceof \Illuminate\Http\RedirectResponse) {
            return $links;
        }
        // Phase 18: follow-ups are branch-scoped planning records. A linked
        // encounter's branch wins (deterministic); otherwise validated
        // context. An explicit contradicting branch is rejected.
        $requestedBranch = $request->input('branch_id');
        if ($links['encounter'] && $links['encounter']->branch_id !== null) {
            if ($requestedBranch !== null && $requestedBranch !== ''
                && (int) $requestedBranch !== (int) $links['encounter']->branch_id) {
                return redirect()->back()
                    ->with('error', 'The follow-up branch must match its encounter branch.')
                    ->withInput();
            }
            $branchId = $links['encounter']->branch_id;
        } else {
            $branchId = $this->resolveBranchId($requestedBranch);
        }

        $followup = DB::transaction(function () use ($instituteId, $patient, $data, $links, $branchId) {
            return FollowUp::create([
                'institute_id' => $instituteId,
                'patient_id' => $patient->id,
                'encounter_id' => $links['encounter_id'],
                'problem_id' => $links['problem_id'],
                'assigned_to' => $links['assigned_to'],
                'created_by' => $this->actorId(),
                'branch_id' => $branchId,
                'planned_date' => $data['planned_date'],
                'reason' => $data['reason'],
                'notes' => $data['notes'] ?? null,
            ]);
        });

        ClinicalAuditLog::record($followup, 'followup_created', [
            'new' => [
                'planned_date' => $followup->planned_date->format('Y-m-d'),
                'status' => $followup->status,
                'encounter_id' => $followup->encounter_id,
                'problem_id' => $followup->problem_id,
                'assigned_to' => $followup->assigned_to,
            ],
        ]);

        return redirect()->back()->with('status', 'Follow-up planned.');
    }

    public function complete(FollowUpRequest $request, FollowUp $followup)
    {
        $this->fencedPatient($followup);
        $data = $request->validated();

        try {
            DB::transaction(function () use ($followup) {
                $followup->lockForUpdate()->first();
                $followup->refresh()->transitionTo(FollowUp::STATUS_COMPLETED);
            });
        } catch (\RuntimeException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        if (! empty($data['notes'])) {
            $followup->update(['notes' => $data['notes']]);
        }
        ClinicalAuditLog::record($followup, 'followup_completed', [
            'old' => ['status' => FollowUp::STATUS_PLANNED],
            'new' => ['status' => $followup->status],
        ]);

        return redirect()->back()->with('status', 'Follow-up completed.');
    }

    public function cancel(FollowUpRequest $request, FollowUp $followup)
    {
        $this->fencedPatient($followup);
        $data = $request->validated();
        if (empty($data['reason'])) {
            return redirect()->back()->with('error', 'A cancellation reason is required.');
        }

        try {
            DB::transaction(function () use ($followup, $data) {
                $followup->lockForUpdate()->first();
                $followup->refresh()->transitionTo(FollowUp::STATUS_CANCELLED, $data['reason']);
            });
        } catch (\RuntimeException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        ClinicalAuditLog::record($followup, 'followup_cancelled', [
            'old' => ['status' => FollowUp::STATUS_PLANNED],
            'new' => ['status' => $followup->status],
            'reason' => $data['reason'],
        ]);

        return redirect()->back()->with('status', 'Follow-up cancelled (history preserved).');
    }

    /**
     * Establish follow-up → institute → patient → authorized actor.
     */
    private function fencedPatient(FollowUp $followup): Patient
    {
        $this->ensureSameInstitute($followup, 'follow-up');
        // Phase 18: follow-ups are branch-scoped (legacy NULLs visible).
        $this->ensureBranchAccess($followup, 'branch_id', 'follow-up');
        $patient = $followup->patient;
        if (! $patient) {
            abort(404);
        }
        $this->ensureSameInstitute($patient, 'patient');
        $this->ensurePatientVisible($patient);

        return $patient;
    }

    /**
     * Validate optional links: encounter/problem belong to the same
     * institute + patient; assignee holds active membership here.
     */
    private function resolveLinks(Patient $patient, array $data): array|\Illuminate\Http\RedirectResponse
    {
        $instituteId = $this->instituteId();
        $encounter = null;
        $encounterId = null;
        $problemId = null;
        $assignedTo = null;

        if (! empty($data['encounter_id'])) {
            $encounter = Encounter::where('institute_id', $instituteId)->find($data['encounter_id']);
            if (! $encounter || (int) $encounter->patient_id !== (int) $patient->id) {
                return redirect()->back()
                    ->with('error', 'The selected encounter belongs to a different patient.')
                    ->withInput();
            }
            // Phase 18: source context follows the encounter's branch fence.
            $this->ensureBranchAccess($encounter, 'branch_id', 'encounter');
            $encounterId = $encounter->id;
        }

        if (! empty($data['problem_id'])) {
            $problem = PatientProblem::where('institute_id', $instituteId)->find($data['problem_id']);
            if (! $problem || (int) $problem->patient_id !== (int) $patient->id) {
                return redirect()->back()
                    ->with('error', 'The selected problem belongs to a different patient.')
                    ->withInput();
            }
            $problemId = $problem->id;
        }

        if (! empty($data['assigned_to'])) {
            // Assignee is any user of this institute: active membership OR
            // an institute Doctor profile (clinicians are referenced by
            // their user id across the HMS, e.g. encounter doctor_id).
            $belongs = Membership::where('user_id', $data['assigned_to'])
                ->where('institution_id', $instituteId)
                ->where('status', 'active')
                ->exists()
                || \App\Models\Medical\Doctor::where('user_id', $data['assigned_to'])
                    ->where('institute_id', $instituteId)
                    ->exists();
            if (! $belongs) {
                return redirect()->back()
                    ->with('error', 'The assigned clinician does not belong to this institute.')
                    ->withInput();
            }
            $assignedTo = (int) $data['assigned_to'];
        }

        return ['encounter' => $encounter, 'encounter_id' => $encounterId, 'problem_id' => $problemId, 'assigned_to' => $assignedTo];
    }

    private function actorId(): ?int
    {
        $staff = auth('institute_user')->user() ?? auth('web')->user() ?? auth()->user();

        return $staff?->getKey();
    }
}

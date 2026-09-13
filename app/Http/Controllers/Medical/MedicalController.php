<?php

namespace App\Http\Controllers\Medical;

use App\Http\Controllers\Controller;
use App\Models\Medical\Admission;
use App\Models\Medical\Patient;
use App\Support\MedicalScope;

/**
 * Phase 1 plumbing base — NOT business logic.
 *
 * Resolves the current institute for every medical action and guards
 * route-model-bound records against cross-institute access. Form requests
 * already validate institute scoping; this is the controller-side backstop.
 *
 * Doctor isolation: a logged-in user linked to a Doctor profile (and not an
 * owner) is fenced to their own queue/patients/fees. Owners, receptionists
 * and staff without a doctor profile keep full visibility.
 */
abstract class MedicalController extends Controller
{
    protected function instituteId(): int
    {
        return MedicalScope::instituteIdOrFail();
    }

    /**
     * Abort 403 unless the record belongs to the current institute.
     */
    protected function ensureSameInstitute(object $record, string $what = 'record'): void
    {
        if (($record->institute_id ?? null) !== $this->instituteId()) {
            abort(403, "You do not have permission to access this {$what}.");
        }
    }

    /**
     * Own doctor users.id when the current user is fenced to their own data,
     * or null when they keep full (institute-wide) visibility.
     */
    protected function doctorFenceId(): ?int
    {
        return MedicalScope::ownDoctorUserId($this->instituteId());
    }

    // ------------------------------------------------------------------
    // Phase 18 — branch fence layer (augments, never replaces, the
    // institute + doctor fences above). BranchContext is request-static
    // and synced from the actor's membership; null context means
    // institute-wide (owner/admin/platform/CLI) with zero constraints.
    // Legacy rule: branch_id NULL is a legitimate pre-branch state and
    // stays visible to branch-scoped readers (no backfilled guesses).
    // ------------------------------------------------------------------

    /**
     * Validated context branch id, or null when institute-wide. A context
     * branch outside the current institute fails closed (403).
     */
    protected function branchContextId(): ?int
    {
        $branchId = \App\Support\BranchContext::id();
        if ($branchId === null) {
            return null;
        }
        $belongs = \App\Models\Branch::where('id', $branchId)
            ->where('institute_id', $this->instituteId())
            ->exists();
        if (! $belongs) {
            abort(403, 'You do not have permission to access this branch.');
        }

        return (int) $branchId;
    }

    /**
     * Branch ids the actor may access, or null when institute-wide.
     *
     * @return int[]|null
     */
    protected function accessibleBranchIds(): ?array
    {
        $branchId = $this->branchContextId();

        return $branchId !== null ? [$branchId] : null;
    }

    /**
     * Constrain a branch-carrying query: context branch plus legacy NULLs,
     * grouped so tenant/branch predicates stay outside any OR group
     * (Phase 02 rule, repeated at branch level). No-op institute-wide.
     */
    protected function scopeBranch($query, string $column = 'branch_id')
    {
        $branchId = $this->branchContextId();
        if ($branchId === null) {
            return $query;
        }

        return $query->where(function ($q) use ($query, $column, $branchId) {
            // Qualify only for Eloquent builders so joins stay unambiguous;
            // plain query builders keep the bare column.
            $col = (is_object($query) && method_exists($query, 'getModel'))
                ? $query->getModel()->qualifyColumn($column)
                : $column;
            $q->where($col, $branchId)->orWhereNull($col);
        });
    }

    /**
     * Abort 403 unless a branch-carrying record is visible in this branch
     * context (exact match, or legacy NULL). No-op institute-wide.
     */
    protected function ensureBranchAccess(object $record, string $column = 'branch_id', string $what = 'record'): void
    {
        $branchId = $this->branchContextId();
        if ($branchId === null) {
            return;
        }
        $recordBranch = $record->{$column} ?? null;
        if ($recordBranch !== null && (int) $recordBranch !== (int) $branchId) {
            abort(403, "You do not have permission to access this {$what}.");
        }
    }

    /**
     * Resolve the branch for a new record: an explicit request value must
     * exist, belong to this institute and be accessible (never trusted
     * blindly); otherwise the actor's context branch, or legacy NULL for
     * institute-wide actors. Branch identity itself is never editable
     * afterwards (update paths must unset branch_id).
     */
    protected function resolveBranchId($requested): ?int
    {
        $instituteId = $this->instituteId();
        if ($requested !== null && $requested !== '') {
            $branch = \App\Models\Branch::where('id', (int) $requested)->first();
            if (! $branch || (int) $branch->institute_id !== (int) $instituteId) {
                abort(403, 'The selected branch is not available.');
            }
            // Phase 18.1: inactive (or trashed) branches accept no new
            // clinical records; history already on them stays intact.
            if (($branch->status ?? 'active') !== 'active') {
                abort(403, 'The selected branch is not active.');
            }
            $this->ensureBranchAccess($branch, 'id', 'branch');

            return (int) $branch->id;
        }

        return $this->branchContextId();
    }

    /**
     * Whether a clinician (users.id) may own records in the branch: doctors
     * with NO active assignments in this institute are legacy-compatible
     * (institute-wide); assigned doctors are restricted to their branches.
     * Null branch (legacy record) imposes no rule.
     */
    protected function doctorBranchOk(int $doctorUserId, ?int $branchId, ?int $instituteId = null): bool
    {
        if ($branchId === null || $doctorUserId <= 0) {
            return true;
        }
        $instituteId ??= $this->instituteId();

        $doctorIds = \App\Models\Medical\Doctor::where('institute_id', $instituteId)
            ->where('user_id', $doctorUserId)
            ->pluck('id');
        if ($doctorIds->isEmpty()) {
            return false;
        }
        $hasAssignments = \Illuminate\Support\Facades\DB::table('doctor_branch')
            ->where('institute_id', $instituteId)
            ->whereIn('doctor_id', $doctorIds)
            ->where('is_active', true)
            ->exists();
        if (! $hasAssignments) {
            return true;
        }

        return \Illuminate\Support\Facades\DB::table('doctor_branch')
            ->where('institute_id', $instituteId)
            ->where('branch_id', $branchId)
            ->whereIn('doctor_id', $doctorIds)
            ->where('is_active', true)
            ->exists();
    }

    /**
     * Clinician user ids selectable in a branch picker: doctors assigned to
     * the branch plus legacy doctors with no assignments anywhere in the
     * institute. Doctors assigned exclusively elsewhere are hidden.
     */
    protected function branchDoctorUserIds(?int $branchId, ?int $instituteId = null): array
    {
        $instituteId ??= $this->instituteId();
        $map = \App\Models\Medical\Doctor::where('institute_id', $instituteId)
            ->pluck('user_id', 'id');
        if ($map->isEmpty()) {
            return [];
        }
        if ($branchId === null) {
            return $map->values()->map(fn ($v) => (int) $v)->unique()->values()->all();
        }

        $assigned = \Illuminate\Support\Facades\DB::table('doctor_branch')
            ->where('institute_id', $instituteId)
            ->where('is_active', true)
            ->get(['branch_id', 'doctor_id'])
            ->groupBy('doctor_id');
        $visible = [];
        foreach ($map as $doctorId => $userId) {
            $rows = $assigned->get($doctorId, collect());
            if ($rows->isEmpty() || $rows->contains('branch_id', $branchId)) {
                $visible[] = (int) $userId;
            }
        }

        return array_values(array_unique($visible));
    }

    /**
     * Abort 403 unless a doctor-keyed record belongs to the fenced doctor
     * (no-op for unfenced users). Keys: doctor_id (default) or
     * admitting_doctor_id for admissions.
     */
    protected function ensureDoctorOwns(object $record, string $key = 'doctor_id', string $what = 'record'): void
    {
        $fence = $this->doctorFenceId();
        if ($fence !== null && (int) ($record->{$key} ?? 0) !== $fence) {
            abort(403, "You do not have permission to access this {$what}.");
        }
    }

    /**
     * Restrict a patient query to the fenced doctor's own patients
     * (patients with at least one of their appointments in this institute).
     * No-op unfenced.
     */
    protected function scopeOwnPatients($query, ?int $fence = null)
    {
        $fence ??= $this->doctorFenceId();
        if ($fence === null) {
            return $query;
        }
        $instituteId = $this->instituteId();

        return $query->whereHas('appointments', fn ($q) => $q
            ->where('institute_id', $instituteId)
            ->where('doctor_id', $fence));
    }

    /**
     * Abort 403 unless the patient is visible to the fenced doctor
     * (has at least one of their appointments here). No-op unfenced.
     */
    protected function ensurePatientVisible(Patient $patient): void
    {
        // Guardian placeholder rows carry no clinical data — linkable by all.
        if (! $patient->is_patient) {
            return;
        }
        $fence = $this->doctorFenceId();
        if ($fence !== null
            && ! $patient->appointments()
                ->where('institute_id', $this->instituteId())
                ->where('doctor_id', $fence)->exists()) {
            abort(403, 'You do not have permission to access this patient.');
        }
    }

    /**
     * Patient options for booking/select dropdowns: all active patients
     * unfenced; only own patients (with appointments) plus patients
     * actively admitted under this doctor for fenced doctors. New walk-in
     * patients are registered/assigned by front-desk (unfenced) staff or
     * the quick-add popup — they do not list for other doctors.
     */
    protected function ownPatientOptions(int $instituteId, ?int $fence = null)
    {
        $fence ??= $this->doctorFenceId();

        $query = Patient::where('institute_id', $instituteId)
            ->active()
            ->patients()
            ->orderBy('first_name');

        if ($fence !== null) {
            $query->where(function ($q) use ($instituteId, $fence) {
                $q->whereHas('appointments', fn ($qq) => $qq
                        ->where('institute_id', $instituteId)
                        ->where('doctor_id', $fence))
                    ->orWhereHas('admissions', fn ($qq) => $qq
                        ->where('institute_id', $instituteId)
                        ->where('status', 'active')
                        ->where('admitting_doctor_id', $fence));
            });
        }

        return $query->get();
    }

    /**
     * Restrict an invoice query to the fenced doctor's own billing (see
     * Invoice::scopeVisibleToDoctor). No-op unfenced.
     */
    protected function scopeOwnInvoices($query, ?int $fence = null)
    {
        $fence ??= $this->doctorFenceId();
        if ($fence === null) {
            return $query;
        }

        return $query->visibleToDoctor($this->instituteId(), $fence);
    }

    /**
     * Abort 403 unless the invoice is visible to the fenced doctor.
     * No-op unfenced.
     */
    protected function ensureInvoiceVisible(\App\Models\Medical\Invoice $invoice): void
    {
        $fence = $this->doctorFenceId();
        if ($fence === null) {
            return;
        }
        $admission = $invoice->relationLoaded('admission') ? $invoice->admission : $invoice->admission()->first();
        if ($admission && (int) $admission->admitting_doctor_id === $fence) {
            return;
        }
        $patient = $invoice->relationLoaded('patient') ? $invoice->patient : $invoice->patient()->first();
        if ($patient && $this->mayActOnPatient($patient, $fence)
            && ! $patient->appointments()->where('institute_id', $this->instituteId())->exists()) {
            return; // brand-new patient, nothing attributable to anyone yet
        }
        if ($patient) {
            $instituteId = $this->instituteId();
            $own = $patient->appointments()->where('institute_id', $instituteId)->where('doctor_id', $fence)->exists();
            $other = $patient->appointments()->where('institute_id', $instituteId)->where('doctor_id', '!=', $fence)->exists();
            if ($own && ! $other) {
                return;
            }
        }
        abort(403, 'You do not have permission to access this invoice.');
    }

    /**
     * Abort 403 unless the TPA claim's invoice is visible to the fenced
     * doctor. No-op unfenced.
     */
    protected function ensureClaimVisible(\App\Models\Medical\TpaClaim $claim): void
    {
        if ($this->doctorFenceId() === null) {
            return;
        }
        $invoice = $claim->relationLoaded('invoice') ? $claim->invoice : $claim->invoice()->first();
        if (! $invoice) {
            abort(403, 'You do not have permission to access this claim.');
        }
        $this->ensureInvoiceVisible($invoice);
    }

    /**
     * Whether the current user is a medical administrator (institute owner
     * or hospital-admin). Only admins may delete completed or paid
     * appointments — finalized records are protected for everyone else.
     */
    protected function isMedicalAdmin(?int $instituteId = null): bool
    {
        $instituteId ??= $this->instituteId();
        $staff = auth('institute_user')->user() ?? auth('web')->user() ?? auth()->user();

        if (! $staff) {
            return false;
        }

        if ($staff instanceof \App\Models\InstituteUser) {
            return $staff->isOwner() || ($staff->role?->slug === 'hospital-admin');
        }

        try {
            $slug = \App\Models\Membership::where('user_id', $staff->getKey())
                ->where('institution_id', $instituteId)
                ->where('status', 'active')
                ->with('role')
                ->first()
                ?->role?->slug;

            return in_array($slug, ['institute-owner', 'hospital-admin'], true);
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Whether the current user IS the given doctor (linked Doctor profile).
     * The patient's own doctor may delete their finalized appointments
     * (always audit-logged) — anyone else needs to be an administrator.
     */
    protected function isOwnDoctor(int $doctorUserId, ?int $instituteId = null): bool
    {
        $fence = MedicalScope::ownDoctorUserId($instituteId ?? $this->instituteId());

        return $fence !== null && (int) $fence === (int) $doctorUserId;
    }

    /**
     * Patient ids with an active admission (IPD tag for lists/dropdowns).
     * Single batched query — pass the map to views; Blade checks
     * `($ipdPatientIds[$patient->id] ?? null)`.
     *
     * @return array<int, true>
     */
    protected function activeAdmissionPatientIds(int $instituteId, $patientIds): array
    {
        $ids = collect($patientIds)->filter()->unique()->values()->all();
        if ($ids === []) {
            return [];
        }

        return Admission::where('institute_id', $instituteId)
            ->whereIn('patient_id', $ids)
            ->where('status', 'active')
            ->pluck('patient_id')
            ->flip()
            ->all();
    }

    /**
     * Whether a fenced doctor may act on a patient: own patient, a brand
     * new patient with no appointments yet in this institute, or a patient
     * actively admitted under this doctor. Always true unfenced. Used by
     * store paths so doctors can still take new/admitted patients.
     */
    protected function mayActOnPatient(Patient $patient, ?int $fence = null): bool
    {
        $fence ??= $this->doctorFenceId();
        if ($fence === null) {
            return true;
        }
        $instituteId = $this->instituteId();
        if ($patient->appointments()->where('institute_id', $instituteId)->where('doctor_id', $fence)->exists()) {
            return true;
        }
        if ($patient->admissions()->where('institute_id', $instituteId)->where('status', 'active')->where('admitting_doctor_id', $fence)->exists()) {
            return true;
        }

        return ! $patient->appointments()->where('institute_id', $instituteId)->exists();
    }
}

<?php

namespace App\Http\Controllers\Medical;

use App\Http\Controllers\Controller;
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
     * unfenced; own patients plus brand-new ones for fenced doctors.
     */
    protected function ownPatientOptions(int $instituteId, ?int $fence = null)
    {
        $fence ??= $this->doctorFenceId();

        $query = Patient::where('institute_id', $instituteId)
            ->active()
            ->orderBy('first_name');

        if ($fence !== null) {
            $query->where(function ($q) use ($instituteId, $fence) {
                $q->whereHas('appointments', fn ($qq) => $qq
                        ->where('institute_id', $instituteId)
                        ->where('doctor_id', $fence))
                    ->orWhereDoesntHave('appointments', fn ($qq) => $qq
                        ->where('institute_id', $instituteId));
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
     * Whether a fenced doctor may act on a patient: own patient, or a brand
     * new patient with no appointments yet in this institute. Always true
     * unfenced. Used by store paths so doctors can still take new patients.
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

        return ! $patient->appointments()->where('institute_id', $instituteId)->exists();
    }
}

<?php

namespace App\Livewire\Medical;

use App\Models\Medical\Admission;
use App\Models\Medical\Appointment;
use App\Models\Medical\ClinicalAuditLog;
use App\Models\Medical\VitalSign;
use App\Support\MedicalScope;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

/**
 * Record (and edit) vital signs against an appointment (OPD) or an
 * admission (IPD). One of the two context ids is required.
 *
 * Gates:
 * - viewing the latest card: `medical_vitals.view`
 * - saving: `medical_vitals.create`
 * - editing: `medical_vitals.update`
 *
 * Every create/update/delete is written to the clinical audit log.
 */
class VitalsRecorder extends Component
{
    public ?int $appointmentId = null;

    public ?int $admissionId = null;

    public ?int $patientId = null;

    public ?int $doctorId = null;

    public int $instituteId = 0;

    /** Row being edited (null = create mode). */
    public ?int $editingId = null;

    public ?string $temperature = null;

    public ?string $blood_pressure_systolic = null;

    public ?string $blood_pressure_diastolic = null;

    public ?string $pulse = null;

    public ?string $heart_rate = null;

    public ?string $respiratory_rate = null;

    public ?string $spo2 = null;

    public ?string $pain_score = null;

    public ?string $blood_sugar = null;

    public ?string $weight = null;

    public ?string $height = null;

    public ?string $notes = null;

    /** Latest record for the read-only card. */
    public ?VitalSign $latest = null;

    public bool $canView = false;

    public bool $canSave = false;

    public bool $canEdit = false;

    public string $statusMessage = '';

    public string $errorMessage = '';

    protected function rules(): array
    {
        return [
            'temperature' => 'nullable|numeric|between:35,42',
            'blood_pressure_systolic' => 'nullable|integer|min:60|max:250',
            'blood_pressure_diastolic' => 'nullable|integer|min:30|max:150',
            'pulse' => 'nullable|integer|min:30|max:250',
            'heart_rate' => 'nullable|integer|min:30|max:250',
            'respiratory_rate' => 'nullable|integer|min:5|max:60',
            'spo2' => 'nullable|integer|min:70|max:100',
            'pain_score' => 'nullable|integer|min:0|max:10',
            'blood_sugar' => 'nullable|numeric|min:20|max:500',
            'weight' => 'nullable|numeric|min:1|max:300',
            'height' => 'nullable|numeric|min:30|max:250',
            'notes' => 'nullable|string|max:5000',
        ];
    }

    public function mount(?int $appointmentId = null, ?int $admissionId = null): void
    {
        abort_unless($appointmentId || $admissionId, 400, 'Vitals need an appointment or an admission.');

        $this->instituteId = (int) (MedicalScope::instituteId() ?? 0);
        abort_unless($this->instituteId > 0, 403, 'No institute context.');

        if ($appointmentId) {
            $appointment = Appointment::where('institute_id', $this->instituteId)->findOrFail($appointmentId);
            abort_unless($this->mayActOnDoctor((int) $appointment->doctor_id), 403, 'You may not record vitals for this visit.');
            $this->appointmentId = $appointment->id;
            $this->patientId = $appointment->patient_id;
            $this->doctorId = (int) $appointment->doctor_id;
        } else {
            $admission = Admission::where('institute_id', $this->instituteId)->findOrFail($admissionId);
            abort_unless($this->mayActOnDoctor((int) $admission->admitting_doctor_id), 403, 'You may not record vitals for this admission.');
            abort_if($admission->status !== 'active', 422, 'Cannot record vitals for a discharged patient.');
            $this->admissionId = $admission->id;
            $this->patientId = $admission->patient_id;
            $this->doctorId = (int) $admission->admitting_doctor_id;
        }

        $this->canView = $this->hasPermission('medical_vitals.view');
        $this->canSave = $this->hasPermission('medical_vitals.create');
        $this->canEdit = $this->hasPermission('medical_vitals.update');

        $this->loadLatest();
    }

    /**
     * Save a new record, or — when given an id that is not already being
     * edited — load that row into the form for editing.
     */
    public function update(?int $vitalId = null): void
    {
        if ($vitalId !== null && $this->editingId !== $vitalId) {
            $this->edit($vitalId);

            return;
        }

        abort_unless($this->editingId !== null ? $this->canEdit : $this->canSave, 403, 'You may not save vitals.');

        $data = $this->validate();
        $this->errorMessage = '';
        $this->statusMessage = '';

        if ($this->editingId !== null) {
            $vital = $this->scopedQuery()->find($this->editingId);
            abort_if(! $vital, 404, 'Vitals entry not found.');
            $old = ClinicalAuditLog::snapshot($vital);
            $vital->update(array_filter($data, fn ($v) => $v !== null && $v !== ''));
            ClinicalAuditLog::record($vital, 'updated', [
                'old' => $old,
                'new' => ClinicalAuditLog::snapshot($vital->refresh()),
            ]);
            $this->statusMessage = 'Vitals updated.';
        } else {
            $vital = DB::transaction(function () use ($data) {
                return VitalSign::create(array_merge(
                    array_filter($data, fn ($v) => $v !== null && $v !== ''),
                    [
                        'appointment_id' => $this->appointmentId,
                        'admission_id' => $this->admissionId,
                        'patient_id' => $this->patientId,
                        'doctor_id' => $this->doctorId,
                        'recorded_by' => MedicalScope::recorderId(),
                        'recorded_at' => now(),
                    ]
                ));
            });
            ClinicalAuditLog::record($vital, 'created', ['new' => ClinicalAuditLog::snapshot($vital)]);
            $this->statusMessage = 'Vitals recorded.';
        }

        $this->resetForm();
        $this->loadLatest();
        $this->dispatch('vitalsSaved');
    }

    /** Load a row into the form for editing. */
    public function edit(int $vitalId): void
    {
        abort_unless($this->canEdit, 403, 'You may not edit vitals.');

        $vital = $this->scopedQuery()->find($vitalId);
        abort_if(! $vital, 404, 'Vitals entry not found.');

        $this->editingId = $vital->id;
        foreach (array_keys($this->rules()) as $field) {
            $value = $vital->getAttribute($field);
            $this->$field = $value !== null ? (string) $value : null;
        }
        $this->errorMessage = '';
        $this->statusMessage = '';
    }

    public function cancelEdit(): void
    {
        $this->resetForm();
        $this->errorMessage = '';
        $this->statusMessage = '';
    }

    public function render()
    {
        return view('livewire.medical.vitals-recorder');
    }

    // ---------- internals ----------

    private function resetForm(): void
    {
        $this->editingId = null;
        foreach (array_keys($this->rules()) as $field) {
            $this->$field = null;
        }
    }

    private function loadLatest(): void
    {
        if (! $this->canView) {
            $this->latest = null;

            return;
        }

        $this->latest = $this->scopedQuery()
            ->with('recordedBy')
            ->orderByDesc('recorded_at')
            ->orderByDesc('id')
            ->first();
    }

    private function scopedQuery()
    {
        // No institute_id column on vital_signs — tenant safety comes from
        // mount(), which resolves both context ids inside the institute.
        $query = VitalSign::query();
        if ($this->appointmentId) {
            return $query->where('appointment_id', $this->appointmentId);
        }

        return $query->where('admission_id', $this->admissionId);
    }

    /**
     * Fenced doctors act only on their own visits; everyone else needs
     * the manage-level appointments permission (same gate as the queue).
     */
    private function mayActOnDoctor(int $doctorUserId): bool
    {
        try {
            $fence = MedicalScope::ownDoctorUserId($this->instituteId);
            if ($fence !== null) {
                return (int) $fence === $doctorUserId;
            }
        } catch (\Throwable) {
            // fall through to the permission check
        }

        return $this->hasPermission('medical_appointments.edit');
    }

    private function hasPermission(string $slug): bool
    {
        try {
            $staff = auth('institute_user')->user() ?? auth('web')->user() ?? auth()->user();

            return (bool) ($staff && method_exists($staff, 'hasPermission') && $staff->hasPermission($slug));
        } catch (\Throwable) {
            return false;
        }
    }
}

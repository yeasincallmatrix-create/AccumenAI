<?php

namespace App\Services\Medical;

use App\Models\Medical\Admission;
use App\Models\Medical\Appointment;
use App\Models\Medical\BloodRequest;
use App\Models\Medical\DentalProcedure;
use App\Models\Medical\EmergencyVisit;
use App\Models\Medical\LabOrder;
use App\Models\Medical\Patient;
use App\Models\Medical\PatientTimelineEvent;
use App\Models\Medical\PhysiotherapyPlan;
use App\Models\Medical\PhysiotherapySession;
use App\Models\Medical\Prescription;
use App\Models\Medical\RadiologyOrder;
use App\Models\Medical\VaccinationRecord;
use App\Models\Medical\VitalSign;
use Illuminate\Support\Collection;

/**
 * EMR write-based timeline events (patient_timeline_events table).
 *
 * Complements (does NOT replace) the Phase 16 PatientTimelineService,
 * which remains the read-only live aggregation over authoritative source
 * records used by PatientController::history. This service persists
 * lightweight event rows so the EMR sub-module can render a unified,
 * filterable, paginated timeline without re-querying 11 source modules
 * on every page view (read-heavy path).
 */
class PatientTimelineEventService
{
    public function getTimeline(int $instituteId, int $patientId, array $filters = []): Collection
    {
        $events = PatientTimelineEvent::forInstitute($instituteId)
            ->forPatient($patientId);

        if (! empty($filters['event_types'])) {
            $events->whereIn('patient_timeline_events.event_type', (array) $filters['event_types']);
        } elseif (! empty($filters['event_type'])) {
            $events->where('patient_timeline_events.event_type', $filters['event_type']);
        }
        if (! empty($filters['from'])) {
            $events->where('patient_timeline_events.event_at', '>=', $filters['from']);
        }
        if (! empty($filters['to'])) {
            $events->where('patient_timeline_events.event_at', '<=', $filters['to']);
        }
        if (! empty($filters['severity'])) {
            $events->where('patient_timeline_events.severity', $filters['severity']);
        }

        return $events->with(['doctor'])->orderByDesc('patient_timeline_events.event_at')->limit(500)->get();
    }

    public function recordEvent(array $data): PatientTimelineEvent
    {
        $eventAt = $data['event_at'] ?? now();

        return PatientTimelineEvent::create([
            'institute_id' => $data['institute_id'],
            'branch_id' => $data['branch_id'] ?? null,
            'patient_id' => $data['patient_id'],
            'event_type' => $data['event_type'],
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'event_at' => $eventAt,
            'event_date' => $data['event_date'] ?? (now()->parse($eventAt)->toDateString()),
            'source_type' => $data['source_type'] ?? null,
            'source_id' => $data['source_id'] ?? null,
            'doctor_id' => $data['doctor_id'] ?? null,
            'department_id' => $data['department_id'] ?? null,
            'location' => $data['location'] ?? null,
            'metadata' => $data['metadata'] ?? null,
            'severity' => $data['severity'] ?? 'info',
            'icon' => $data['icon'] ?? null,
        ]);
    }

    public function alreadyRecorded(int $instituteId, string $sourceType, int $sourceId, string $eventType): bool
    {
        return PatientTimelineEvent::where('institute_id', $instituteId)
            ->where('source_type', $sourceType)
            ->where('source_id', $sourceId)
            ->where('event_type', $eventType)
            ->exists();
    }

    public function backfillForPatient(int $instituteId, int $patientId): int
    {
        $count = 0;
        $patient = Patient::find($patientId);
        if (! $patient) {
            return 0;
        }
        if ((int) $patient->institute_id !== (int) $instituteId) {
            return 0;
        }

        // Prescriptions
        Prescription::where('institute_id', $instituteId)
            ->where('patient_id', $patientId)
            ->orderBy('id')
            ->chunk(200, function ($rows) use ($instituteId, $patientId, &$count) {
                foreach ($rows as $rx) {
                    if ($this->alreadyRecorded($instituteId, Prescription::class, $rx->id, 'prescription')) {
                        continue;
                    }
                    $this->recordEvent([
                        'institute_id' => $instituteId,
                        'branch_id' => $rx->branch_id,
                        'patient_id' => $patientId,
                        'event_type' => 'prescription',
                        'title' => "Prescription {$rx->prescription_number}",
                        'description' => $rx->diagnosis,
                        'event_at' => $rx->created_at ?? now(),
                        'event_date' => $rx->prescription_date ?? today(),
                        'source_type' => Prescription::class,
                        'source_id' => $rx->id,
                        'doctor_id' => $rx->doctor_id,
                        'icon' => 'bi-file-medical',
                    ]);
                    $count++;
                }
            });

        // Appointments (OPD visits)
        Appointment::where('institute_id', $instituteId)
            ->where('patient_id', $patientId)
            ->orderBy('id')
            ->chunk(200, function ($rows) use ($instituteId, $patientId, &$count) {
                foreach ($rows as $appt) {
                    if ($this->alreadyRecorded($instituteId, Appointment::class, $appt->id, 'appointment')) {
                        continue;
                    }
                    $this->recordEvent([
                        'institute_id' => $instituteId,
                        'branch_id' => $appt->branch_id,
                        'patient_id' => $patientId,
                        'event_type' => 'appointment',
                        'title' => "OPD Visit — {$appt->status}",
                        'description' => $appt->complaints,
                        'event_at' => $appt->created_at ?? now(),
                        'event_date' => $appt->appointment_date ?? today(),
                        'source_type' => Appointment::class,
                        'source_id' => $appt->id,
                        'doctor_id' => $appt->doctor_id,
                        'location' => 'OPD',
                        'icon' => 'bi-calendar-check',
                    ]);
                    $count++;
                }
            });

        // Admissions (IPD) + discharge events
        Admission::where('institute_id', $instituteId)
            ->where('patient_id', $patientId)
            ->orderBy('id')
            ->chunk(200, function ($rows) use ($instituteId, $patientId, &$count) {
                foreach ($rows as $adm) {
                    if (! $this->alreadyRecorded($instituteId, Admission::class, $adm->id, 'admission')) {
                        $this->recordEvent([
                            'institute_id' => $instituteId,
                            'branch_id' => $adm->branch_id,
                            'patient_id' => $patientId,
                            'event_type' => 'admission',
                            'title' => 'Admitted (IPD)',
                            'description' => $adm->primary_diagnosis,
                            'event_at' => $adm->created_at ?? now(),
                            'event_date' => $adm->admission_date ?? today(),
                            'source_type' => Admission::class,
                            'source_id' => $adm->id,
                            'doctor_id' => $adm->admitting_doctor_id,
                            'location' => 'IPD',
                            'icon' => 'bi-hospital',
                        ]);
                        $count++;
                    }
                    if ($adm->discharge_date && ! $this->alreadyRecorded($instituteId, Admission::class, $adm->id, 'discharge')) {
                        $this->recordEvent([
                            'institute_id' => $instituteId,
                            'branch_id' => $adm->branch_id,
                            'patient_id' => $patientId,
                            'event_type' => 'discharge',
                            'title' => 'Discharged (IPD)',
                            'description' => $adm->discharge_summary,
                            'event_at' => $adm->updated_at ?? now(),
                            'event_date' => $adm->discharge_date,
                            'source_type' => Admission::class,
                            'source_id' => $adm->id,
                            'doctor_id' => $adm->admitting_doctor_id,
                            'location' => 'IPD',
                            'icon' => 'bi-box-arrow-right',
                        ]);
                        $count++;
                    }
                }
            });

        // Vitals (patient-direct + admission/appointment linked)
        VitalSign::where('patient_id', $patientId)
            ->orderBy('id')
            ->chunk(200, function ($rows) use ($instituteId, $patientId, &$count) {
                foreach ($rows as $v) {
                    if ($this->alreadyRecorded($instituteId, VitalSign::class, $v->id, 'vitals')) {
                        continue;
                    }
                    $institute = $instituteId;
                    $branch = null;
                    try {
                        if ($v->admission) {
                            if ((int) $v->admission->institute_id !== (int) $instituteId) {
                                continue;
                            }
                            $branch = $v->admission->branch_id;
                        } elseif ($v->appointment) {
                            if ((int) $v->appointment->institute_id !== (int) $instituteId) {
                                continue;
                            }
                            $branch = $v->appointment->branch_id;
                        }
                    } catch (\Throwable) {
                        continue;
                    }
                    $this->recordEvent([
                        'institute_id' => $institute,
                        'branch_id' => $branch,
                        'patient_id' => $patientId,
                        'event_type' => 'vitals',
                        'title' => "Vitals — BP {$v->blood_pressure_systolic}/{$v->blood_pressure_diastolic}, SpO2 {$v->spo2}%",
                        'description' => $v->notes,
                        'event_at' => $v->recorded_at ?? $v->created_at ?? now(),
                        'source_type' => VitalSign::class,
                        'source_id' => $v->id,
                        'doctor_id' => $v->doctor_id,
                        'icon' => 'bi-heart-pulse',
                    ]);
                    $count++;
                }
            });

        // Lab orders (+ lab_result when completed)
        LabOrder::where('institute_id', $instituteId)
            ->where('patient_id', $patientId)
            ->orderBy('id')
            ->chunk(200, function ($rows) use ($instituteId, $patientId, &$count) {
                foreach ($rows as $order) {
                    if (! $this->alreadyRecorded($instituteId, LabOrder::class, $order->id, 'lab_order')) {
                        $this->recordEvent([
                            'institute_id' => $instituteId,
                            'branch_id' => $order->branch_id,
                            'patient_id' => $patientId,
                            'event_type' => 'lab_order',
                            'title' => "Lab Order {$order->order_number} ({$order->status})",
                            'description' => $order->clinical_notes,
                            'event_at' => $order->created_at ?? now(),
                            'event_date' => $order->order_date ?? today(),
                            'source_type' => LabOrder::class,
                            'source_id' => $order->id,
                            'doctor_id' => $order->doctor_id,
                            'location' => 'Laboratory',
                            'icon' => 'bi-eyedropper',
                        ]);
                        $count++;
                    }
                    if ($order->completed_at && ! $this->alreadyRecorded($instituteId, LabOrder::class, $order->id, 'lab_result')) {
                        $this->recordEvent([
                            'institute_id' => $instituteId,
                            'branch_id' => $order->branch_id,
                            'patient_id' => $patientId,
                            'event_type' => 'lab_result',
                            'title' => "Lab Result {$order->order_number}",
                            'description' => $order->result_notes,
                            'event_at' => $order->completed_at,
                            'source_type' => LabOrder::class,
                            'source_id' => $order->id,
                            'doctor_id' => $order->doctor_id,
                            'location' => 'Laboratory',
                            'severity' => 'success',
                            'icon' => 'bi-eyedropper',
                        ]);
                        $count++;
                    }
                }
            });

        // Radiology orders (+ report when reported)
        RadiologyOrder::where('institute_id', $instituteId)
            ->where('patient_id', $patientId)
            ->orderBy('id')
            ->chunk(200, function ($rows) use ($instituteId, $patientId, &$count) {
                foreach ($rows as $order) {
                    if (! $this->alreadyRecorded($instituteId, RadiologyOrder::class, $order->id, 'radiology_order')) {
                        $this->recordEvent([
                            'institute_id' => $instituteId,
                            'branch_id' => $order->branch_id,
                            'patient_id' => $patientId,
                            'event_type' => 'radiology_order',
                            'title' => "Radiology {$order->modality} — {$order->body_part} ({$order->status})",
                            'description' => $order->clinical_indication,
                            'event_at' => $order->created_at ?? now(),
                            'source_type' => RadiologyOrder::class,
                            'source_id' => $order->id,
                            'doctor_id' => $order->doctor_id,
                            'location' => 'Radiology',
                            'icon' => 'bi-radioactive',
                        ]);
                        $count++;
                    }
                    if ($order->reported_at && ! $this->alreadyRecorded($instituteId, RadiologyOrder::class, $order->id, 'radiology_report')) {
                        $this->recordEvent([
                            'institute_id' => $instituteId,
                            'branch_id' => $order->branch_id,
                            'patient_id' => $patientId,
                            'event_type' => 'radiology_report',
                            'title' => "Radiology Report {$order->order_number}",
                            'description' => $order->impression,
                            'event_at' => $order->reported_at,
                            'source_type' => RadiologyOrder::class,
                            'source_id' => $order->id,
                            'doctor_id' => $order->doctor_id,
                            'location' => 'Radiology',
                            'severity' => 'success',
                            'icon' => 'bi-radioactive',
                        ]);
                        $count++;
                    }
                }
            });

        // Emergency visits
        EmergencyVisit::where('institute_id', $instituteId)
            ->where('patient_id', $patientId)
            ->orderBy('id')
            ->chunk(200, function ($rows) use ($instituteId, $patientId, &$count) {
                foreach ($rows as $visit) {
                    if ($this->alreadyRecorded($instituteId, EmergencyVisit::class, $visit->id, 'emergency')) {
                        continue;
                    }
                    $this->recordEvent([
                        'institute_id' => $instituteId,
                        'branch_id' => $visit->branch_id,
                        'patient_id' => $patientId,
                        'event_type' => 'emergency',
                        'title' => "Emergency {$visit->visit_number} — {$visit->triage_level}",
                        'description' => $visit->chief_complaint,
                        'event_at' => $visit->arrived_at ?? $visit->created_at ?? now(),
                        'source_type' => EmergencyVisit::class,
                        'source_id' => $visit->id,
                        'doctor_id' => $visit->attending_doctor_id,
                        'location' => 'Emergency',
                        'severity' => in_array($visit->triage_level, ['red', 'critical', 'P1']) ? 'critical' : 'warning',
                        'icon' => 'bi-lightning',
                    ]);
                    $count++;
                }
            });

        // Blood requests (transfusions)
        BloodRequest::where('institute_id', $instituteId)
            ->where('patient_id', $patientId)
            ->orderBy('id')
            ->chunk(200, function ($rows) use ($instituteId, $patientId, &$count) {
                foreach ($rows as $req) {
                    if ($this->alreadyRecorded($instituteId, BloodRequest::class, $req->id, 'blood_transfusion')) {
                        continue;
                    }
                    $this->recordEvent([
                        'institute_id' => $instituteId,
                        'branch_id' => $req->branch_id,
                        'patient_id' => $patientId,
                        'event_type' => 'blood_transfusion',
                        'title' => "Blood Request {$req->request_number} — {$req->blood_group} ({$req->status})",
                        'description' => $req->clinical_indication,
                        'event_at' => $req->fulfilled_at ?? $req->created_at ?? now(),
                        'source_type' => BloodRequest::class,
                        'source_id' => $req->id,
                        'doctor_id' => $req->doctor_id,
                        'location' => 'Blood Bank',
                        'icon' => 'bi-droplet-fill',
                    ]);
                    $count++;
                }
            });

        // Vaccinations
        VaccinationRecord::where('institute_id', $instituteId)
            ->where('patient_id', $patientId)
            ->orderBy('id')
            ->chunk(200, function ($rows) use ($instituteId, $patientId, &$count) {
                foreach ($rows as $rec) {
                    if ($this->alreadyRecorded($instituteId, VaccinationRecord::class, $rec->id, 'vaccination')) {
                        continue;
                    }
                    $this->recordEvent([
                        'institute_id' => $instituteId,
                        'branch_id' => $rec->branch_id,
                        'patient_id' => $patientId,
                        'event_type' => 'vaccination',
                        'title' => "Vaccination {$rec->record_number} — Dose {$rec->dose_number}",
                        'description' => $rec->post_vaccination_notes,
                        'event_at' => $rec->administered_at ?? now(),
                        'event_date' => $rec->administered_date ?? today(),
                        'source_type' => VaccinationRecord::class,
                        'source_id' => $rec->id,
                        'severity' => $rec->adverse_event !== null && $rec->adverse_event !== 'none' ? 'warning' : 'success',
                        'icon' => 'bi-shield-plus',
                    ]);
                    $count++;
                }
            });

        // Dental procedures
        DentalProcedure::where('institute_id', $instituteId)
            ->where('patient_id', $patientId)
            ->orderBy('id')
            ->chunk(200, function ($rows) use ($instituteId, $patientId, &$count) {
                foreach ($rows as $proc) {
                    if ($this->alreadyRecorded($instituteId, DentalProcedure::class, $proc->id, 'dental_procedure')) {
                        continue;
                    }
                    $this->recordEvent([
                        'institute_id' => $instituteId,
                        'branch_id' => $proc->branch_id,
                        'patient_id' => $patientId,
                        'event_type' => 'dental_procedure',
                        'title' => "Dental: {$proc->procedure_name} ({$proc->procedure_number})",
                        'description' => $proc->diagnosis,
                        'event_at' => $proc->performed_at ?? $proc->created_at ?? now(),
                        'source_type' => DentalProcedure::class,
                        'source_id' => $proc->id,
                        'doctor_id' => $proc->dentist_id,
                        'location' => 'Dental',
                        'icon' => 'bi-emoji-smile',
                    ]);
                    $count++;
                }
            });

        // Physiotherapy sessions (via plan's patient)
        PhysiotherapySession::where('institute_id', $instituteId)
            ->whereHas('plan', fn ($q) => $q->where('patient_id', $patientId))
            ->orderBy('id')
            ->chunk(200, function ($rows) use ($instituteId, $patientId, &$count) {
                foreach ($rows as $sess) {
                    if ($this->alreadyRecorded($instituteId, PhysiotherapySession::class, $sess->id, 'physiotherapy_session')) {
                        continue;
                    }
                    $this->recordEvent([
                        'institute_id' => $instituteId,
                        'branch_id' => $sess->branch_id,
                        'patient_id' => $patientId,
                        'event_type' => 'physiotherapy_session',
                        'title' => "Physio Session {$sess->session_number} ({$sess->status})",
                        'description' => $sess->progress_notes,
                        'event_at' => $sess->attended_at ?? $sess->created_at ?? now(),
                        'event_date' => $sess->session_date ?? today(),
                        'source_type' => PhysiotherapySession::class,
                        'source_id' => $sess->id,
                        'doctor_id' => $sess->therapist_id,
                        'location' => 'Physiotherapy',
                        'icon' => 'bi-activity',
                    ]);
                    $count++;
                }
            });

        // Physiotherapy plans
        PhysiotherapyPlan::where('institute_id', $instituteId)
            ->where('patient_id', $patientId)
            ->orderBy('id')
            ->chunk(200, function ($rows) use ($instituteId, $patientId, &$count) {
                foreach ($rows as $plan) {
                    if ($this->alreadyRecorded($instituteId, PhysiotherapyPlan::class, $plan->id, 'physiotherapy_session')) {
                        continue;
                    }
                    $this->recordEvent([
                        'institute_id' => $instituteId,
                        'branch_id' => $plan->branch_id,
                        'patient_id' => $patientId,
                        'event_type' => 'physiotherapy_session',
                        'title' => "Physio Plan {$plan->plan_number} ({$plan->status})",
                        'description' => $plan->diagnosis,
                        'event_at' => $plan->created_at ?? now(),
                        'event_date' => $plan->start_date ?? today(),
                        'source_type' => PhysiotherapyPlan::class,
                        'source_id' => $plan->id,
                        'doctor_id' => $plan->therapist_id,
                        'location' => 'Physiotherapy',
                        'icon' => 'bi-activity',
                    ]);
                    $count++;
                }
            });

        return $count;
    }
}

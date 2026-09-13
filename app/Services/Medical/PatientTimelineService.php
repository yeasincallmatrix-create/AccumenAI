<?php

namespace App\Services\Medical;

use App\Models\Medical\Admission;
use App\Models\Medical\ClinicalAuditLog;
use App\Models\Medical\Encounter;
use App\Models\Medical\EncounterDiagnosis;
use App\Models\Medical\LabOrder;
use App\Models\Medical\LabResult;
use App\Models\Medical\FollowUp;
use App\Models\Medical\NursingNote;
use App\Models\Medical\Patient;
use App\Models\Medical\PatientProblem;
use App\Models\Medical\Prescription;
use App\Models\Medical\VitalSign;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Phase 16 — Patient longitudinal timeline (read-only aggregation).
 *
 * The timeline is a VIEW over authoritative source records, never a
 * second source of truth: no timeline table exists, nothing is copied,
 * and this service performs zero writes. Each event carries its source
 * identity (source_type + source_id) and links to the existing source
 * page. If a relationship cannot be established deterministically, the
 * record is simply not shown as that event type — never guessed.
 *
 * Bounds: every source query is institute + patient scoped, date-filtered
 * in SQL, and hard-capped (PER_TYPE_LIMIT). Merging, sorting and paging
 * happen over that bounded set — full history is never loaded.
 *
 * Deterministic order: occurred_at DESC, then type rank, then source id
 * DESC, so identical timestamps sort stably.
 *
 * Date semantics (Phase 09): clinical DATE/DATETIME fields as stored
 * (application timezone); no UTC conversion, no JS date logic.
 */
class PatientTimelineService
{
    public const PER_TYPE_LIMIT = 100;

    public const PER_PAGE = 15;

    public const TYPE_ADMISSION = 'admission';

    public const TYPE_DISCHARGE = 'discharge';

    public const TYPE_TRANSFER = 'transfer';

    public const TYPE_ENCOUNTER = 'encounter';

    public const TYPE_DIAGNOSIS = 'diagnosis';

    public const TYPE_LAB_ORDER = 'lab_order';

    public const TYPE_LAB_RESULT = 'lab_result';

    public const TYPE_PRESCRIPTION = 'prescription';

    public const TYPE_VITAL = 'vital';

    public const TYPE_NURSING_NOTE = 'nursing_note';

    public const TYPE_PROBLEM = 'problem';

    public const TYPE_FOLLOW_UP = 'follow_up';

    public const TYPES = [
        self::TYPE_ADMISSION,
        self::TYPE_DISCHARGE,
        self::TYPE_TRANSFER,
        self::TYPE_ENCOUNTER,
        self::TYPE_DIAGNOSIS,
        self::TYPE_PROBLEM,
        self::TYPE_LAB_ORDER,
        self::TYPE_LAB_RESULT,
        self::TYPE_PRESCRIPTION,
        self::TYPE_FOLLOW_UP,
        self::TYPE_VITAL,
        self::TYPE_NURSING_NOTE,
    ];

    /**
     * Stable tie-break rank for identical timestamps. Phase 17 inserted
     * problem/follow_up without disturbing the relative order of the
     * Phase 16 types.
     */
    private const TYPE_RANK = [
        self::TYPE_DISCHARGE => 0,
        self::TYPE_TRANSFER => 1,
        self::TYPE_ADMISSION => 2,
        self::TYPE_ENCOUNTER => 3,
        self::TYPE_DIAGNOSIS => 4,
        self::TYPE_PROBLEM => 5,
        self::TYPE_LAB_RESULT => 6,
        self::TYPE_LAB_ORDER => 7,
        self::TYPE_PRESCRIPTION => 8,
        self::TYPE_FOLLOW_UP => 9,
        self::TYPE_VITAL => 10,
        self::TYPE_NURSING_NOTE => 11,
    ];

    /**
     * @param  array{from?: ?string, to?: ?string, type?: ?string}  $filters
     * @param  string[]  $visibleTypes  event types the actor may see
     * @param  int|null  $fence  fenced doctor user id (null = full visibility)
     */
    public function paginate(Patient $patient, array $filters, array $visibleTypes, ?int $fence, int $page = 1, int $perPage = self::PER_PAGE): LengthAwarePaginator
    {
        $events = $this->collect($patient, $filters, $visibleTypes, $fence);

        usort($events, function (array $a, array $b): int {
            $byDate = strcmp($b['occurred_at'], $a['occurred_at']);
            if ($byDate !== 0) {
                return $byDate;
            }
            $byType = (self::TYPE_RANK[$a['type']] ?? 99) <=> (self::TYPE_RANK[$b['type']] ?? 99);
            if ($byType !== 0) {
                return $byType;
            }

            return $b['source_id'] <=> $a['source_id'];
        });

        $total = count($events);
        $items = array_slice($events, ($page - 1) * $perPage, $perPage);

        return new LengthAwarePaginator($items, $total, $perPage, $page, [
            'path' => route('medical.patients.history', $patient),
            'query' => array_filter([
                'from' => $filters['from'] ?? null,
                'to' => $filters['to'] ?? null,
                'type' => $filters['type'] ?? null,
            ]),
        ]);
    }

    /**
     * @return array<int, array{type: string, occurred_at: string, title: string, summary: string, source_type: string, source_id: int, route_name: string, route_param: int, badge: string}>
     */
    public function collect(Patient $patient, array $filters, array $visibleTypes, ?int $fence): array
    {
        $wanted = $this->wantedTypes($filters, $visibleTypes);
        if ($wanted === []) {
            return [];
        }

        $from = $filters['from'] ?? null;
        $to = $filters['to'] ?? null;
        // Phase 18: branch limitation shared by all branch-owned types.
        $branch = $this->branchLimit($patient);
        $events = [];

        if (in_array(self::TYPE_ADMISSION, $wanted, true)
            || in_array(self::TYPE_DISCHARGE, $wanted, true)
            || in_array(self::TYPE_TRANSFER, $wanted, true)) {
            $admissions = $this->admissions($patient, $fence, $from, $to, $branch);
            foreach ($admissions as $admission) {
                if (in_array(self::TYPE_ADMISSION, $wanted, true)) {
                    $events[] = $this->event(
                        self::TYPE_ADMISSION,
                        $this->dateTime($admission->admission_date, $admission->admission_time),
                        'Admission — '.($admission->bed?->bed_number ?? 'unassigned bed'),
                        trim(($admission->primary_diagnosis ?? '').' · Status: '.$admission->status, ' ·'),
                        Admission::class,
                        $admission->id,
                        'medical.admissions.show',
                        'admission',
                        $admission->status
                    );
                }
                if (in_array(self::TYPE_DISCHARGE, $wanted, true) && $admission->discharge_date) {
                    $events[] = $this->event(
                        self::TYPE_DISCHARGE,
                        $this->dateTime($admission->discharge_date, $admission->discharge_time),
                        'Discharge',
                        trim(substr((string) $admission->discharge_summary, 0, 140)),
                        Admission::class,
                        $admission->id,
                        'medical.admissions.show',
                        'discharged',
                        $admission->status
                    );
                }
            }
            if (in_array(self::TYPE_TRANSFER, $wanted, true)) {
                foreach ($this->transfers($patient, $admissions, $branch) as $transfer) {
                    $events[] = $transfer;
                }
            }
        }

        if (in_array(self::TYPE_ENCOUNTER, $wanted, true)) {
            foreach ($this->encounters($patient, $fence, $from, $to, $branch) as $encounter) {
                $events[] = $this->event(
                    self::TYPE_ENCOUNTER,
                    $this->coalesceDateTime([$encounter->started_at, $encounter->created_at]),
                    ucfirst(str_replace('_', ' ', strtolower($encounter->encounter_type))).' Encounter — '.clinical_no($encounter->encounter_number),
                    'Status: '.str_replace('_', ' ', $encounter->status),
                    Encounter::class,
                    $encounter->id,
                    'medical.encounters.show',
                    'completed',
                    $encounter->status
                );
            }
        }

        if (in_array(self::TYPE_DIAGNOSIS, $wanted, true)) {
            foreach ($this->diagnoses($patient, $fence, $from, $to, $branch) as $diagnosis) {
                $unresolved = $diagnosis->mapping_status === EncounterDiagnosis::MAPPING_UNRESOLVED;
                $events[] = $this->event(
                    self::TYPE_DIAGNOSIS,
                    $this->coalesceDateTime([$diagnosis->created_at]),
                    'Diagnosis ('.$diagnosis->diagnosis_type.') — '.$diagnosis->label,
                    'Status: '.$diagnosis->status.' · Terminology: '.($unresolved ? 'Unresolved' : $diagnosis->code_system.' '.$diagnosis->code)
                        .' · '.clinical_no($diagnosis->encounter->encounter_number),
                    EncounterDiagnosis::class,
                    $diagnosis->id,
                    'medical.encounters.show',
                    'diagnosis',
                    $diagnosis->status
                );
            }
        }

        if (in_array(self::TYPE_LAB_ORDER, $wanted, true)
            || in_array(self::TYPE_LAB_RESULT, $wanted, true)) {
            $orders = $this->labOrders($patient, $fence, $from, $to, $branch);
            if (in_array(self::TYPE_LAB_ORDER, $wanted, true)) {
                foreach ($orders as $order) {
                    $events[] = $this->event(
                        self::TYPE_LAB_ORDER,
                        $this->coalesceDateTime([$order->order_date, $order->created_at]),
                        'Lab Order — '.clinical_no($order->order_number),
                        'Status: '.$order->status.' · Priority: '.$order->priority,
                        LabOrder::class,
                        $order->id,
                        'medical.lab.orders.show',
                        'lab',
                        $order->status
                    );
                }
            }
            if (in_array(self::TYPE_LAB_RESULT, $wanted, true)) {
                foreach ($this->labResults($patient, $fence, $orders, $from, $to) as $result) {
                    $events[] = $this->event(
                        self::TYPE_LAB_RESULT,
                        $this->coalesceDateTime([$result->created_at]),
                        'Lab Result — '.($result->labTest->name ?? 'test'),
                        'Value: '.($result->result_value ?? $result->result_text ?? '—')
                            .' · Status: '.$result->status,
                        LabResult::class,
                        $result->id,
                        'medical.lab.orders.show',
                        'result',
                        $result->status
                    );
                }
            }
        }

        if (in_array(self::TYPE_PRESCRIPTION, $wanted, true)) {
            foreach ($this->prescriptions($patient, $fence, $from, $to, $branch) as $rx) {
                $events[] = $this->event(
                    self::TYPE_PRESCRIPTION,
                    $this->coalesceDateTime([$rx->prescription_date, $rx->created_at]),
                    'Prescription — '.clinical_no($rx->prescription_number),
                    'Status: '.$rx->statusLabel(),
                    Prescription::class,
                    $rx->id,
                    'medical.prescriptions.show',
                    'rx',
                    $rx->is_finalized ? 'finalized' : 'draft'
                );
            }
        }

        if (in_array(self::TYPE_VITAL, $wanted, true)) {
            foreach ($this->vitals($patient, $fence, $from, $to, $branch) as $vital) {
                $events[] = $this->event(
                    self::TYPE_VITAL,
                    $this->coalesceDateTime([$vital->recorded_at, $vital->created_at]),
                    'Vitals',
                    'Temp '.($vital->temperature ?? '—').' · BP '.($vital->blood_pressure ?? '—')
                        .' · Pulse '.($vital->pulse ?? '—'),
                    VitalSign::class,
                    $vital->id,
                    $vital->admission_id ? 'medical.admissions.show' : 'medical.appointments.show',
                    $vital->admission_id ? 'vital-admission' : 'vital-appointment',
                    'recorded'
                );
                // Link target differs from the source: point at the parent.
                $events[array_key_last($events)]['route_param'] = (int) ($vital->admission_id ?? $vital->appointment_id);
            }
        }

        if (in_array(self::TYPE_NURSING_NOTE, $wanted, true)) {
            foreach ($this->nursingNotes($patient, $fence, $from, $to, $branch) as $note) {
                $events[] = $this->event(
                    self::TYPE_NURSING_NOTE,
                    $this->coalesceDateTime([$note->recorded_at, $note->created_at]),
                    'Nursing Note',
                    trim(substr((string) $note->note, 0, 140)),
                    NursingNote::class,
                    $note->id,
                    'medical.admissions.show',
                    'note',
                    'recorded'
                );
                $events[array_key_last($events)]['route_param'] = (int) $note->admission_id;
            }
        }

        // Phase 17 — longitudinal layer. Problems/follow-ups have no
        // dedicated detail pages (per §25); events link to the patient
        // profile where both lists live, with source identity preserved.
        // Record-level doctor fencing is intentionally patient-gated (the
        // controller's ensurePatientVisible is the boundary; recorder-based
        // fencing would hide shared longitudinal context from the treating
        // doctor, including owner-recorded rows).
        if (in_array(self::TYPE_PROBLEM, $wanted, true)) {
            // Problems are institute-level longitudinal context (§23).
            foreach ($this->problems($patient, $from, $to) as $problem) {
                $events[] = $this->event(
                    self::TYPE_PROBLEM,
                    $this->coalesceDateTime([$problem->created_at]),
                    'Problem recorded — '.$problem->label,
                    'Type: '.$problem->problem_type.' · Status: '.$problem->status
                        .' · Terminology: '.($problem->mapping_status === 'unresolved'
                            ? 'Unresolved' : $problem->code_system.' '.$problem->code),
                    PatientProblem::class,
                    $problem->id,
                    'medical.patients.show',
                    'problem',
                    $problem->status
                );
                $events[array_key_last($events)]['route_param'] = (int) $patient->id;
                if ($problem->status === PatientProblem::STATUS_RESOLVED && $problem->resolved_date) {
                    $events[] = $this->event(
                        self::TYPE_PROBLEM,
                        $this->coalesceDateTime([$problem->resolved_date]),
                        'Problem resolved — '.$problem->label,
                        'Resolved: '.$problem->resolved_date->format('Y-m-d'),
                        PatientProblem::class,
                        $problem->id,
                        'medical.patients.show',
                        'resolved',
                        $problem->status
                    );
                    $events[array_key_last($events)]['route_param'] = (int) $patient->id;
                }
            }
        }

        if (in_array(self::TYPE_FOLLOW_UP, $wanted, true)) {
            foreach ($this->followUps($patient, $from, $to, $branch) as $followup) {
                $events[] = $this->event(
                    self::TYPE_FOLLOW_UP,
                    $this->coalesceDateTime([$followup->planned_date]),
                    'Follow-up planned — '.substr((string) $followup->reason, 0, 80),
                    'Status: '.$followup->status,
                    FollowUp::class,
                    $followup->id,
                    'medical.patients.show',
                    'planned',
                    $followup->status
                );
                $events[array_key_last($events)]['route_param'] = (int) $patient->id;
                $terminalAt = $followup->completed_at ?? $followup->cancelled_at;
                if ($terminalAt) {
                    $events[] = $this->event(
                        self::TYPE_FOLLOW_UP,
                        $this->coalesceDateTime([$terminalAt]),
                        'Follow-up '.$followup->status.' — '.substr((string) $followup->reason, 0, 80),
                        $followup->status === FollowUp::STATUS_CANCELLED
                            ? 'Reason: '.substr((string) $followup->cancel_reason, 0, 140) : '',
                        FollowUp::class,
                        $followup->id,
                        'medical.patients.show',
                        $followup->status,
                        $followup->status
                    );
                    $events[array_key_last($events)]['route_param'] = (int) $patient->id;
                }
            }
        }

        return $events;
    }

    /**
     * Phase 18 — branch limitation for this patient, or null when
     * institute-wide. A context branch from another institute fails closed
     * (-1 denies everything). Legacy NULL-branch rows stay visible to
     * branch-scoped readers (documented legacy rule, no backfilled guesses).
     */
    private function branchLimit(Patient $patient): ?int
    {
        $ctx = \App\Support\BranchContext::id();
        if ($ctx === null) {
            return null;
        }
        $ok = \App\Models\Branch::whereKey($ctx)
            ->where('institute_id', $patient->institute_id)
            ->exists();

        return $ok ? (int) $ctx : -1;
    }

    /**
     * Branch condition for a PARENT-scoped whereHas (admission/appointment
     * behind vitals/notes): context branch + legacy NULLs, or deny-all.
     */
    private function constrainParentBranch($query, ?int $branch)
    {
        if ($branch === null) {
            return $query;
        }
        if ($branch < 0) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function ($q) use ($branch) {
            $q->where('branch_id', $branch)->orWhereNull('branch_id');
        });
    }

    /**
     * Constrain a branch-carrying query (context branch + legacy NULLs),
     * grouped so tenant/branch predicates stay outside any OR group.
     */
    private function applyBranch($query, string $column, ?int $branch)
    {
        if ($branch === null) {
            return $query;
        }
        if ($branch < 0) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function ($q) use ($column, $branch) {
            $q->where($column, $branch)->orWhereNull($column);
        });
    }

    /** Intersect requested type filter with what the actor may see. */
    private function wantedTypes(array $filters, array $visibleTypes): array
    {
        $requested = $filters['type'] ?? null;
        if ($requested !== null && $requested !== '') {
            return in_array($requested, self::TYPES, true) && in_array($requested, $visibleTypes, true)
                ? [$requested]
                : [];
        }

        return array_values(array_intersect(self::TYPES, $visibleTypes));
    }

    private function applyDateRange($query, string $column, ?string $from, ?string $to)
    {
        if ($from) {
            $query->whereDate($column, '>=', $from);
        }
        if ($to) {
            $query->whereDate($column, '<=', $to);
        }

        return $query;
    }

    private function admissions(Patient $patient, ?int $fence, ?string $from, ?string $to, ?int $branch = null)
    {
        $query = Admission::where('institute_id', $patient->institute_id)
            ->where('patient_id', $patient->id)
            ->with('bed');
        $this->applyBranch($query, 'branch_id', $branch);
        if ($fence !== null) {
            $query->where('admitting_doctor_id', $fence);
        }
        $this->applyDateRange($query, 'admission_date', $from, $to);

        return $query->orderBy('admission_date', 'desc')->orderBy('id', 'desc')
            ->limit(self::PER_TYPE_LIMIT)->get();
    }

    /** Bed transfers are deterministic audit rows on the admission itself. */
    private function transfers(Patient $patient, $admissions, ?int $branch = null): array
    {
        if ($admissions->isEmpty()) {
            return [];
        }
        $rows = ClinicalAuditLog::where('institute_id', $patient->institute_id)
            ->where('auditable_type', Admission::class)
            ->whereIn('auditable_id', $admissions->pluck('id'))
            ->where('action', 'transferred');
        $this->applyBranch($rows, 'branch_id', $branch);
        $rows = $rows
            ->orderByDesc('id')
            ->limit(self::PER_TYPE_LIMIT)
            ->get();

        $events = [];
        foreach ($rows as $row) {
            $events[] = $this->event(
                self::TYPE_TRANSFER,
                $this->coalesceDateTime([$row->created_at]),
                'Bed Transfer',
                trim(substr((string) ($row->new_values['to_bed'] ?? $row->reason ?? ''), 0, 140)),
                Admission::class,
                (int) $row->auditable_id,
                'medical.admissions.show',
                'transfer',
                'transferred'
            );
        }

        return $events;
    }

    private function encounters(Patient $patient, ?int $fence, ?string $from, ?string $to, ?int $branch = null)
    {
        $query = Encounter::where('institute_id', $patient->institute_id)
            ->where('patient_id', $patient->id);
        $this->applyBranch($query, 'branch_id', $branch);
        if ($fence !== null) {
            $query->where('doctor_id', $fence);
        }
        // Started_at is nullable (open encounters); fall back to creation.
        if ($from) {
            $query->where(function ($q) use ($from) {
                $q->whereDate('started_at', '>=', $from)->orWhere(function ($qq) use ($from) {
                    $qq->whereNull('started_at')->whereDate('created_at', '>=', $from);
                });
            });
        }
        if ($to) {
            $query->where(function ($q) use ($to) {
                $q->whereDate('started_at', '<=', $to)->orWhere(function ($qq) use ($to) {
                    $qq->whereNull('started_at')->whereDate('created_at', '<=', $to);
                });
            });
        }

        return $query->orderByDesc('started_at')->orderByDesc('id')
            ->limit(self::PER_TYPE_LIMIT)->get();
    }

    private function diagnoses(Patient $patient, ?int $fence, ?string $from, ?string $to, ?int $branch = null)
    {
        // Patient scope flows through the encounter (no patient_id by design);
        // Phase 18: branch likewise follows the encounter.
        $query = EncounterDiagnosis::where('institute_id', $patient->institute_id)
            ->whereHas('encounter', function ($q) use ($patient, $fence, $branch) {
                $q->where('institute_id', $patient->institute_id)
                    ->where('patient_id', $patient->id)
                    ->when($fence !== null, fn ($qq) => $qq->where('doctor_id', $fence));
                if ($branch !== null) {
                    if ($branch < 0) {
                        $q->whereRaw('1 = 0');
                    } else {
                        $q->where(function ($qq) use ($branch) {
                            $qq->where('branch_id', $branch)->orWhereNull('branch_id');
                        });
                    }
                }
            })
            ->with('encounter');
        $this->applyDateRange($query, 'created_at', $from, $to);

        return $query->orderByDesc('id')->limit(self::PER_TYPE_LIMIT)->get();
    }

    private function labOrders(Patient $patient, ?int $fence, ?string $from, ?string $to, ?int $branch = null)
    {
        $query = LabOrder::where('institute_id', $patient->institute_id)
            ->where('patient_id', $patient->id);
        $this->applyBranch($query, 'branch_id', $branch);
        if ($fence !== null) {
            $query->where('doctor_id', $fence);
        }
        $this->applyDateRange($query, 'order_date', $from, $to);

        return $query->orderBy('order_date', 'desc')->orderBy('id', 'desc')
            ->limit(self::PER_TYPE_LIMIT)->get();
    }

    private function labResults(Patient $patient, ?int $fence, $orders, ?string $from, ?string $to)
    {
        // Results are scoped through their parent order: order institute,
        // patient and fence must ALL agree — never an unscoped lookup.
        $query = LabResult::whereIn('lab_order_id', $orders->pluck('id'))
            ->whereHas('labOrder', fn ($q) => $q
                ->where('institute_id', $patient->institute_id)
                ->where('patient_id', $patient->id)
                ->when($fence !== null, fn ($qq) => $qq->where('doctor_id', $fence)))
            ->with('labTest');
        if ($orders->isEmpty()) {
            return collect();
        }
        $this->applyDateRange($query, 'created_at', $from, $to);

        return $query->orderByDesc('id')->limit(self::PER_TYPE_LIMIT)->get();
    }

    private function prescriptions(Patient $patient, ?int $fence, ?string $from, ?string $to, ?int $branch = null)
    {
        $query = Prescription::where('institute_id', $patient->institute_id)
            ->where('patient_id', $patient->id);
        $this->applyBranch($query, 'branch_id', $branch);
        if ($fence !== null) {
            $query->where('doctor_id', $fence);
        }
        $this->applyDateRange($query, 'prescription_date', $from, $to);

        return $query->orderBy('prescription_date', 'desc')->orderBy('id', 'desc')
            ->limit(self::PER_TYPE_LIMIT)->get();
    }

    private function vitals(Patient $patient, ?int $fence, ?string $from, ?string $to, ?int $branch = null)
    {
        $query = VitalSign::where('patient_id', $patient->id)
            ->where(function ($q) use ($patient, $branch) {
                // Institute scope must wrap the OR branches so tenant scope
                // can never be bypassed by an orWhere escape (Phase 02).
                // Phase 18: branch follows the admission/appointment parent.
                $q->whereHas('admission', function ($a) use ($patient, $branch) {
                    $a->where('institute_id', $patient->institute_id);
                    $this->constrainParentBranch($a, $branch);
                })->orWhereHas('appointment', function ($a) use ($patient, $branch) {
                    $a->where('institute_id', $patient->institute_id);
                    $this->constrainParentBranch($a, $branch);
                });
            });
        if ($fence !== null) {
            $query->where(function ($q) use ($fence, $patient) {
                $q->where('doctor_id', $fence)
                    ->orWhereHas('admission', fn ($a) => $a
                        ->where('institute_id', $patient->institute_id)
                        ->where('admitting_doctor_id', $fence))
                    ->orWhereHas('appointment', fn ($a) => $a
                        ->where('institute_id', $patient->institute_id)
                        ->where('doctor_id', $fence));
            });
        }
        $this->applyDateRange($query, 'recorded_at', $from, $to);

        return $query->orderBy('recorded_at', 'desc')->orderBy('id', 'desc')
            ->limit(self::PER_TYPE_LIMIT)->get();
    }

    private function nursingNotes(Patient $patient, ?int $fence, ?string $from, ?string $to, ?int $branch = null)
    {
        // Notes link to admissions only; patient + fence flow through it.
        // Phase 18: branch likewise follows the admission.
        $query = NursingNote::whereHas('admission', function ($q) use ($patient, $fence, $branch) {
            $q->where('institute_id', $patient->institute_id)
                ->where('patient_id', $patient->id)
                ->when($fence !== null, fn ($qq) => $qq->where('admitting_doctor_id', $fence));
            $this->constrainParentBranch($q, $branch);
        });
        $this->applyDateRange($query, 'recorded_at', $from, $to);

        return $query->orderBy('recorded_at', 'desc')->orderBy('id', 'desc')
            ->limit(self::PER_TYPE_LIMIT)->get();
    }

    private function problems(Patient $patient, ?string $from, ?string $to)
    {
        $query = PatientProblem::where('institute_id', $patient->institute_id)
            ->where('patient_id', $patient->id);
        $this->applyDateRange($query, 'created_at', $from, $to);

        return $query->orderByDesc('id')->limit(self::PER_TYPE_LIMIT)->get();
    }

    private function followUps(Patient $patient, ?string $from, ?string $to, ?int $branch = null)
    {
        $query = FollowUp::where('institute_id', $patient->institute_id)
            ->where('patient_id', $patient->id);
        $this->applyBranch($query, 'branch_id', $branch);
        $this->applyDateRange($query, 'planned_date', $from, $to);

        return $query->orderBy('planned_date', 'desc')->orderBy('id', 'desc')
            ->limit(self::PER_TYPE_LIMIT)->get();
    }

    private function event(string $type, string $occurredAt, string $title, string $summary, string $sourceType, int $sourceId, string $routeName, string $badge, string $status): array
    {
        return [
            'type' => $type,
            'occurred_at' => $occurredAt,
            'title' => $title,
            'summary' => $summary,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'route_name' => $routeName,
            'route_param' => $sourceId,
            'badge' => $badge,
            'status' => $status,
        ];
    }

    /**
     * Normalize to "Y-m-d H:i:s" for deterministic lexicographic sorting.
     * Date-cast fields stringify with a midnight time component, so the
     * day is sliced explicitly instead of concatenating blindly.
     */
    private function coalesceDateTime(array $candidates): string
    {
        foreach ($candidates as $candidate) {
            if ($candidate) {
                $text = (string) $candidate;
                if (strlen($text) === 10) {
                    return $text.' 00:00:00';
                }

                return substr($text, 0, 19);
            }
        }

        // Unreachable in practice (created_at always set); deterministic
        // fallback rather than fabricated clinical dates.
        return '1970-01-01 00:00:00';
    }

    private function dateTime($date, $time): string
    {
        $day = substr((string) $date, 0, 10);
        $time = $time ? substr((string) $time, 0, 8) : '00:00:00';
        if (strlen($time) === 5) {
            $time .= ':00';
        }

        return $day.' '.$time;
    }
}

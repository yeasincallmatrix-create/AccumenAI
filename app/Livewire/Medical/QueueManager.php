<?php

namespace App\Livewire\Medical;

use App\Models\Branch;
use App\Models\InstituteUser;
use App\Models\Medical\Appointment;
use App\Models\Medical\Doctor;
use App\Models\Medical\QueueAuditLog;
use App\Models\Medical\VitalSign;
use App\Support\BranchContext;
use App\Support\MedicalScope;
use App\Support\Workspace;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

/**
 * Drag-and-drop queue reordering for checked-in / in-progress appointments.
 *
 * Authorization:
 * - requires `medical_queue.reorder` permission (doctors + receptionists;
 *   institute owners bypass via hasPermission);
 * - staff holding a Doctor profile may reorder ONLY their own queue;
 *   receptionists (permission holders without a doctor profile) and owners
 *   may reorder any doctor's queue.
 *
 * Every changed position is written to queue_audit_logs inside the same
 * transaction as the reorder.
 */
class QueueManager extends Component
{
    public int $doctorUserId;

    public string $date;

    public int $instituteId;

    /** Lightweight rows for rendering. */
    public array $items = [];

    /** Live counters for the queue header (updated without page refresh). */
    public array $status = [
        'total' => 0,
        'checked_in' => 0,
        'in_progress' => 0,
        'estimated_wait_minutes' => 0,
    ];

    public bool $canReorder = false;

    public string $readonlyReason = '';

    /** May check in / complete queue items (separate from reorder permission). */
    public bool $canManage = false;

    public string $statusMessage = '';

    public string $errorMessage = '';

    public function mount(int $doctorUserId, string $date, int $instituteId): void
    {
        $this->doctorUserId = $doctorUserId;
        $this->instituteId = $instituteId;

        try {
            $this->date = Carbon::parse($date)->format('Y-m-d');
        } catch (\Throwable) {
            $this->date = today()->format('Y-m-d');
        }

        // Fenced doctors may only open their own queue.
        $fence = MedicalScope::ownDoctorUserId($this->instituteId);
        abort_if($fence !== null && (int) $fence !== (int) $this->doctorUserId,
            403, 'You may only view your own queue.');

        // Phase 18.1: branch-scoped actors may only open queues of doctors
        // visible in their branch (legacy unassigned doctors included).
        if (($branchId = $this->branchId()) !== null) {
            abort_unless($this->doctorVisibleInBranch($this->doctorUserId, $branchId),
                403, 'You do not have permission to access this queue.');
        }

        $this->canReorder = $this->resolveCanReorder($reason);
        $this->readonlyReason = $reason;
        $this->canManage = $this->resolveCanManage();

        $this->loadQueue();
    }

    public function loadQueue(): void
    {
        // Lean columns only (payload + serialization stay small on 1.5GB
        // shared hosting); patient carries just what the cards render.
        $queueQuery = Appointment::where('institute_id', $this->instituteId)
            ->where('doctor_id', $this->doctorUserId)
            ->whereDate('appointment_date', $this->date)
            ->inQueue();
        // Phase 18.1: queue rows follow the branch fence (legacy NULLs stay
        // visible, exactly like the appointments list).
        $this->scopeBranch($queueQuery);
        $appointments = $queueQuery
            ->select(['id', 'patient_id', 'serial_number', 'status', 'appointment_time', 'queue_order', 'fee_collected_at'])
            ->with('patient:id,first_name,last_name,phone')
            ->get()
            ->values();

        // One profile lookup for the whole (single-doctor) queue; the fee
        // popup only triggers when the doctor has a billing profile.
        $profile = Doctor::resolveForUser($this->doctorUserId, $this->instituteId);

        // Batch the follow-up lookup: last completed visit date per queued
        // patient in ONE query (was one query per card — N+1).
        $lastVisitDates = collect();
        $patientIds = $appointments->pluck('patient_id')->filter()->unique()->values();
        if ($profile && $patientIds->isNotEmpty()) {
            $lastVisitQuery = Appointment::where('institute_id', $this->instituteId)
                ->where('doctor_id', $this->doctorUserId)
                ->whereIn('patient_id', $patientIds)
                ->where('status', 'completed');
            $this->scopeBranch($lastVisitQuery);
            $lastVisitDates = $lastVisitQuery
                ->selectRaw('patient_id, MAX(appointment_date) as last_date')
                ->groupBy('patient_id')
                ->pluck('last_date', 'patient_id');
        }
        $followUpWindow = $profile ? max(1, (int) ($profile->follow_up_days ?? 30)) : 30;

        // Fee timing for the whole (single-doctor) queue: drives the
        // Prescription shortcut — right after payment (pre) or once the
        // visit is in progress (post).
        $feeTiming = ! $profile ? 'none' : ((bool) $profile->collect_fee_before_visit ? 'pre' : 'post');

        // Batch the latest vitals per queued appointment in ONE query so
        // the Record Vitals popup opens pre-filled from the server.
        $latestVitals = VitalSign::whereIn('appointment_id', $appointments->pluck('id')->all())
            ->orderByDesc('recorded_at')
            ->orderByDesc('id')
            ->get()
            ->groupBy('appointment_id')
            ->map(fn ($rows) => $rows->first()->only([
                'temperature', 'blood_pressure_systolic', 'blood_pressure_diastolic',
                'pulse', 'heart_rate', 'respiratory_rate', 'spo2', 'pain_score',
                'blood_sugar', 'weight', 'height', 'notes',
            ]));

        $this->items = $appointments
            ->map(function (Appointment $a, int $i) use ($profile, $lastVisitDates, $followUpWindow, $latestVitals, $feeTiming) {
                $feeRequired = false;
                $feeAmount = 0.0;
                $feeType = '';
                if ($profile && $a->patient) {
                    $preVisit = (bool) $profile->collect_fee_before_visit;
                    $feeRequired = ($a->status === 'checked_in' && $preVisit)
                        || ($a->status === 'in_progress' && ! $preVisit);
                    if ($feeRequired) {
                        // Same rule as Doctor::getApplicableFee() /
                        // hasFollowUpRateFor(), resolved from the batched
                        // dates above — zero extra queries per card.
                        $lastDate = $lastVisitDates[$a->patient_id] ?? null;
                        $days = $lastDate !== null && $lastDate !== ''
                            ? Carbon::parse($lastDate)->diffInDays(now())
                            : null;
                        $isFollowUp = $days !== null && $days <= $followUpWindow;
                        $feeAmount = $isFollowUp
                            ? (float) ($profile->follow_up_fee ?? 500)
                            : $profile->firstVisitFee();
                        $feeType = $isFollowUp ? 'follow-up' : 'first visit';
                    }
                }

                return [
                    'id' => $a->id,
                    'position' => $i + 1,
                    'serial' => $a->serial_number,
                    'patient_id' => $a->patient_id,
                    'patient_name' => $a->patient->full_name ?? 'N/A',
                    'patient_phone' => $a->patient->phone ?? null,
                    'status' => $a->status,
                    'time' => $a->appointment_time
                        ? Carbon::parse($a->appointment_time)->format('h:i A')
                        : '',
                    'queue_order' => $a->queue_order,
                    'fee_required' => $feeRequired,
                    'fee_collected' => $a->fee_collected_at !== null,
                    'fee_timing' => $feeTiming,
                    'latest_vitals' => $latestVitals->get($a->id),
                    'fee_amount' => $feeAmount,
                    'fee_type' => $feeType,
                ];
            })
            ->all();

        $checkedIn = collect($this->items)->where('status', 'checked_in')->count();
        $inProgress = collect($this->items)->where('status', 'in_progress')->count();
        $this->status = [
            'total' => count($this->items),
            'checked_in' => $checkedIn,
            'in_progress' => $inProgress,
            // Same 10-minutes-per-patient rule as the queue service.
            'estimated_wait_minutes' => $checkedIn * 10 + ($inProgress > 0 ? 10 : 0),
        ];

        // Notify the page header (badges + wait time live outside this
        // component) so counts refresh in place — no manual reload needed.
        $this->dispatch('queue-updated', status: $this->status);
    }

    /**
     * Persist a new manual order (array of appointment ids, top to bottom).
     */
    public function updateQueueOrder(array $orderedIds): void
    {
        $this->assertCanReorder();

        $orderedIds = array_values(array_unique(array_map('intval', $orderedIds)));

        $reorderQuery = Appointment::where('institute_id', $this->instituteId)
            ->where('doctor_id', $this->doctorUserId)
            ->whereDate('appointment_date', $this->date)
            ->inQueue();
        // Foreign-branch ids stay ignored (existing stale-id behavior).
        $this->scopeBranch($reorderQuery);
        $current = $reorderQuery
            ->get()
            ->keyBy('id');

        // Keep only ids that are genuinely in this queue right now (stale or
        // foreign ids are ignored); append any current rows missing from the
        // submission (concurrent check-ins) at the end, preserving order.
        $ordered = array_values(array_intersect($orderedIds, $current->keys()->all()));
        foreach ($current->keys() as $id) {
            if (! in_array($id, $ordered, true)) {
                $ordered[] = $id;
            }
        }

        $actor = $this->actorSnapshot();
        $changed = 0;

        // One bulk audit insert (same rows logQueueReorder() would create
        // one-by-one) so a 20-card reorder costs 1 audit query, not 20.
        DB::transaction(function () use ($ordered, $current, $actor, &$changed) {
            $auditRows = [];
            $now = now();
            foreach ($ordered as $index => $id) {
                $appointment = $current->get($id);
                $newOrder = $index + 1;
                if ((int) ($appointment->queue_order ?? 0) === $newOrder) {
                    continue;
                }
                $oldOrder = $appointment->queue_order;
                $appointment->update(['queue_order' => $newOrder]);
                $auditRows[] = [
                    'institute_id' => $appointment->institute_id,
                    'appointment_id' => $appointment->id,
                    'user_id' => $actor['id'],
                    'user_type' => $actor['type'],
                    'actor_name' => $actor['name'],
                    'old_order' => $oldOrder,
                    'new_order' => $newOrder,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
                $changed++;
            }
            if ($auditRows !== []) {
                QueueAuditLog::insert($auditRows);
            }
        });

        $this->errorMessage = '';
        $this->statusMessage = $changed > 0
            ? "Queue reordered — {$changed} position(s) updated and audited."
            : 'Queue order unchanged.';

        $this->loadQueue();
    }

    /** Step a card up/down (touch & keyboard fallback for drag-and-drop). */
    public function move(int $id, string $direction): void
    {
        $this->assertCanReorder();

        $ids = array_column($this->items, 'id');
        $pos = array_search($id, $ids, true);
        if ($pos === false) {
            return;
        }

        $swap = $direction === 'up' ? $pos - 1 : $pos + 1;
        if (! isset($ids[$swap])) {
            return;
        }

        [$ids[$pos], $ids[$swap]] = [$ids[$swap], $ids[$pos]];
        $this->updateQueueOrder($ids);
    }

    /** Complete a queue item (mirrors the appointments list Complete action). */
    public function complete(int $id): void
    {
        abort_unless($this->resolveCanManage(), 403, 'You may not complete queue appointments.');

        $appointment = Appointment::where('institute_id', $this->instituteId)
            ->where('doctor_id', $this->doctorUserId)
            ->whereDate('appointment_date', $this->date)
            ->inQueue()
            ->find($id);

        if (! $appointment) {
            return;
        }
        // Phase 18.1: cross-branch queue mutation is refused server-side.
        $this->assertBranchVisible($appointment);

        if (in_array($appointment->status, ['checked_in', 'in_progress'], true)) {
            $appointment->update(['status' => 'completed']);
            $this->errorMessage = '';
            $this->statusMessage = 'Appointment completed!';
        }

        $this->loadQueue();
    }

    /** Move a checked-in visit to In Progress (stepwise, avoids skipping stages). */
    public function startProgress(int $id): void
    {
        abort_unless($this->resolveCanManage(), 403, 'You may not update queue appointments.');

        $appointment = Appointment::where('institute_id', $this->instituteId)
            ->where('doctor_id', $this->doctorUserId)
            ->whereDate('appointment_date', $this->date)
            ->inQueue()
            ->find($id);

        if (! $appointment) {
            return;
        }
        $this->assertBranchVisible($appointment);

        if ($appointment->status === 'checked_in') {
            // Pre-visit doctors: consultation cannot start before the fee
            // is on record (Collect Visit Fee button first).
            if ($this->isPreVisitUnpaid($appointment)) {
                $this->errorMessage = 'Collect the visit fee first — consultation cannot start before payment.';
                $this->loadQueue();

                return;
            }
            $appointment->update(['status' => 'in_progress']);
            $this->errorMessage = '';
            $this->statusMessage = 'Consultation started!';
        }

        $this->loadQueue();

        // Treating doctor starting the consultation continues straight
        // into writing the prescription (both fee timings — the pre-visit
        // fee step already happened via the Collect gate above). Saving it
        // brings up the fee popup; confirming completes the visit.
        if ($appointment->wasChanged('status')
            && $this->isTreatingDoctorClick($appointment)) {
            $this->redirect(route('medical.prescriptions.create', [
                'patient_id' => $appointment->patient_id,
                'fee_appointment_id' => $appointment->id,
            ]));
        }
    }

    /**
     * Pre-visit doctor whose fee is not yet on record: consultation must
     * wait for the Collect Visit Fee step.
     */
    private function isPreVisitUnpaid(Appointment $appointment): bool
    {
        $profile = Doctor::resolveForUser((int) $appointment->doctor_id, (int) $appointment->institute_id);
        if ($profile === null || ! (bool) $profile->collect_fee_before_visit) {
            return false;
        }

        return $appointment->fee_collected_at === null;
    }

    /**
     * Whether the appointment's doctor collects post-visit (fee due at
     * completion, not at start). No billing profile = no fee flow.
     */    private function isPostVisitDoctor(Appointment $appointment): bool
    {
        $profile = Doctor::resolveForUser((int) $appointment->doctor_id, (int) $appointment->institute_id);

        return $profile !== null && ! (bool) $profile->collect_fee_before_visit;
    }

    /**
     * Whether the click came from the treating doctor themselves (web user
     * id match, or staff linked to the doctor's user). Owners/managers
     * acting on someone else's queue count as non-doctor clicks.
     */
    private function isTreatingDoctorClick(Appointment $appointment): bool
    {
        $doctorId = (int) $appointment->doctor_id;

        try {
            $webUser = auth('web')->user();
            if ($webUser && (int) $webUser->getKey() === $doctorId) {
                return true;
            }
        } catch (\Throwable) {
            // fall through to profile resolution
        }

        try {
            return MedicalScope::ownDoctorUserId((int) $appointment->institute_id) === $doctorId;
        } catch (\Throwable) {
            return false;
        }
    }

    /** Cancel a queue item (confirm dialog in the view guards accidents). */
    public function cancel(int $id): void
    {
        abort_unless($this->resolveCanManage(), 403, 'You may not cancel queue appointments.');

        $appointment = Appointment::where('institute_id', $this->instituteId)
            ->where('doctor_id', $this->doctorUserId)
            ->whereDate('appointment_date', $this->date)
            ->inQueue()
            ->find($id);

        if (! $appointment) {
            return;
        }
        $this->assertBranchVisible($appointment);

        // Paid appointments are finalized records — the queue's own doctor
        // or an admin may cancel (payment reversed + audit entry).
        if ($appointment->fee_collected_at !== null) {
            $actor = $this->actorSnapshot();
            $isAdmin = in_array($actor['type'], ['owner', 'hospital-admin'], true);
            $fence = MedicalScope::ownDoctorUserId($this->instituteId);
            $isOwnDoctor = $fence !== null && (int) $fence === (int) $this->doctorUserId;
            if (! $isAdmin && ! $isOwnDoctor) {
                $this->statusMessage = '';
                $this->errorMessage = 'Only the treating doctor or an administrator may cancel a paid appointment.';

                return;
            }

            $reversedAmount = (float) ($appointment->fee_collected_amount ?? 0);
            $appointment->update([
                'status' => 'cancelled',
                'fee_collected_amount' => null,
                'fee_collected_by_id' => null,
                'fee_collected_by_name' => null,
                'fee_collected_at' => null,
            ]);
            QueueAuditLog::create([
                'institute_id' => $appointment->institute_id,
                'appointment_id' => $appointment->id,
                'user_id' => $actor['id'],
                'user_type' => $actor['type'],
                'actor_name' => $actor['name'],
                'action' => 'fee_reversed',
                'new_order' => $appointment->queue_order ?? $appointment->serial_number ?? 0,
                'amount' => $reversedAmount,
            ]);
            $this->errorMessage = '';
            $this->statusMessage = 'Appointment cancelled and payment of ৳'.number_format($reversedAmount, 2).' reversed.';
            $this->loadQueue();

            return;
        }

        $appointment->update(['status' => 'cancelled']);
        $this->errorMessage = '';
        $this->statusMessage = 'Appointment cancelled.';

        $this->loadQueue();
    }

    public function render()
    {
        return view('livewire.medical.queue-manager');
    }

    // ---------- authorization ----------

    private function assertCanReorder(): void
    {
        $this->canReorder = $this->resolveCanReorder($reason);
        $this->readonlyReason = $reason;

        abort_unless($this->canReorder, 403, $reason ?: 'You may not reorder this queue.');
    }

    private function resolveCanReorder(?string &$reason = null): bool
    {
        $reason = '';
        $staff = auth('institute_user')->user() ?? auth('web')->user() ?? auth()->user();

        if (! $staff || ! method_exists($staff, 'hasPermission')
            || ! $staff->hasPermission('medical_queue.reorder')) {
            $reason = 'Reordering the queue requires the Reorder Patient Queue permission.';

            return false;
        }

        // Doctors may reorder only their own queue.
        $ownDoctorUserId = MedicalScope::ownDoctorUserId($this->instituteId);
        if ($ownDoctorUserId !== null && (int) $ownDoctorUserId !== (int) $this->doctorUserId) {
            // Institute owners may still manage any queue.
            if ($staff instanceof InstituteUser && $staff->isOwner()) {
                return true;
            }
            $reason = 'Doctors may reorder only their own queue.';

            return false;
        }

        return true;
    }

    /**
     * May manage queue items (check in / complete) — same gate as the
     * appointments list routes (medical_appointments.edit).
     */
    private function resolveCanManage(): bool
    {
        try {
            $staff = auth('institute_user')->user() ?? auth('web')->user() ?? auth()->user();
            if ($staff instanceof InstituteUser) {
                return $staff->hasPermission('medical_appointments.edit');
            }
            $membership = Workspace::membershipFor($staff);
            if ($membership) {
                return (bool) $membership->hasPermission('medical_appointments.edit');
            }
        } catch (\Throwable) {
            // fall through to false
        }

        return false;
    }

    /**
     * Phase 18.1 — validated context branch (null when institute-wide). A
     * context branch outside this component's institute fails closed.
     */
    private function branchId(): ?int
    {
        $branchId = BranchContext::id();
        if ($branchId === null) {
            return null;
        }
        $belongs = Branch::whereKey($branchId)
            ->where('institute_id', $this->instituteId)
            ->exists();
        abort_unless($belongs, 403, 'You do not have permission to access this branch.');

        return (int) $branchId;
    }

    /**
     * Phase 18.1 — constrain a queue query to the context branch plus
     * legacy NULLs (grouped; no-op institute-wide). Mirrors the
     * MedicalController::scopeBranch fence for Livewire context.
     */
    private function scopeBranch($query)
    {
        if (($branchId = $this->branchId()) === null) {
            return $query;
        }

        return $query->where(function ($q) use ($branchId) {
            $q->where('branch_id', $branchId)->orWhereNull('branch_id');
        });
    }

    /**
     * Phase 18.1 — refuse cross-branch queue rows server-side (legacy
     * NULLs pass; every direct action calls this after loading).
     */
    private function assertBranchVisible(Appointment $appointment): void
    {
        if (($branchId = $this->branchId()) === null) {
            return;
        }
        abort_if($appointment->branch_id !== null && (int) $appointment->branch_id !== $branchId,
            403, 'You do not have permission to access this queue item.');
    }

    /**
     * Phase 18.1 — whether a clinician may appear in this branch's queue
     * scope: legacy doctors (no assignments) plus assigned ones.
     */
    private function doctorVisibleInBranch(int $doctorUserId, int $branchId): bool
    {
        $doctorIds = Doctor::where('institute_id', $this->instituteId)
            ->where('user_id', $doctorUserId)
            ->pluck('id');
        if ($doctorIds->isEmpty()) {
            return false;
        }
        $hasAssignments = DB::table('doctor_branch')
            ->where('institute_id', $this->instituteId)
            ->whereIn('doctor_id', $doctorIds)
            ->where('is_active', true)
            ->exists();
        if (! $hasAssignments) {
            return true;
        }

        return DB::table('doctor_branch')
            ->where('institute_id', $this->instituteId)
            ->where('branch_id', $branchId)
            ->whereIn('doctor_id', $doctorIds)
            ->where('is_active', true)
            ->exists();
    }

    /**
     * Snapshot of the acting staff member for audit rows.
     *
     * The designation is wired to the account — never a hardcoded default:
     * institute owner → owner, linked doctor profile → doctor, otherwise the
     * staff member's actual role slug (e.g. receptionist, hospital-admin).
     *
     * @return array{id: ?int, type: string, name: ?string}
     */
    private function actorSnapshot(): array
    {
        $staff = auth('institute_user')->user() ?? auth('web')->user() ?? auth()->user();

        $type = 'staff';
        $id = null;
        $name = null;

        if ($staff) {
            $id = $staff->getKey();
            $name = $staff->name ?? trim(($staff->first_name ?? '').' '.($staff->last_name ?? '')) ?: null;

            if ($staff instanceof InstituteUser && $staff->isOwner()) {
                $type = 'owner';
            } elseif (MedicalScope::ownDoctorUserId($this->instituteId) !== null) {
                $type = 'doctor';
            } else {
                $type = $this->staffRoleSlug($staff) ?? 'staff';
            }
        }

        return ['id' => $id, 'type' => $type, 'name' => $name];
    }

    /**
     * The staff member's real role slug for this institute (e.g.
     * receptionist, hospital-admin, institute-owner), or null when the
     * account holds no role here.
     */
    private function staffRoleSlug(mixed $staff): ?string
    {
        try {
            if ($staff instanceof InstituteUser) {
                $slug = $staff->role?->slug;
            } else {
                $slug = \App\Models\Membership::where('user_id', $staff->getKey())
                    ->where('institution_id', $this->instituteId)
                    ->where('status', 'active')
                    ->with('role')
                    ->first()
                    ?->role?->slug;
            }

            if ($slug === 'institute-owner') {
                return 'owner';
            }

            return is_string($slug) && $slug !== '' ? $slug : null;
        } catch (\Throwable) {
            return null;
        }
    }
}

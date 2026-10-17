<?php

namespace App\Console\Commands;

use App\Models\Institute;
use App\Models\Medical\Appointment;
use App\Models\Medical\Doctor;
use App\Models\Medical\QueueAuditLog;
use App\Services\Medical\MedicalFeePostingService;
use App\Services\Medical\QueueManager as QueueManagerService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * End-of-day queue rollover (per doctor).
 *
 * - checked_in (and in_progress) appointments move to the doctor's next
 *   working day with a fresh serial, restarted as plain appointments
 *   (status scheduled — yesterday's check-in never travels forward).
 *   One audit row (action=rollover) each.
 * - EXCEPTION: a post-visit doctor's paid in_progress row (fee_collected_at
 *   set, collect_fee_before_visit = false) is CLOSED as completed instead —
 *   the consultation ran and the money is on record, so there is nothing to
 *   carry and nothing to reverse (audit action=rollover_completed). Pre-visit
 *   paid rows keep the reverse+carry path (money in, service not delivered).
 * - scheduled appointments that never checked in are auto-cancelled with an
 *   audit row (action=auto_cancelled).
 * - completed / cancelled / no_show rows are never touched.
 * - Next working day = next date with an available DoctorAvailability row
 *   for the doctor's profile; doctors without any availability fall back to
 *   the next calendar day.
 *
 *   php artisan medical:queue-rollover --date=2026-09-09 --dry-run
 *   php artisan medical:queue-rollover --institute=189
 */
class QueueRollover extends Command
{
    protected $signature = 'medical:queue-rollover
                            {--date= : Rollover date Y-m-d (default today, never a future date)}
                            {--institute= : Limit to one institute id}
                            {--dry-run : Preview without writing}';

    protected $description = 'Carry checked-in visits to the next working day and auto-cancel no-shows.';

    public function handle(QueueManagerService $queues): int
    {
        try {
            $date = $this->option('date') ? Carbon::parse($this->option('date'))->format('Y-m-d') : today()->format('Y-m-d');
        } catch (\Throwable) {
            $this->error('Invalid --date, expected Y-m-d.');

            return self::FAILURE;
        }

        if ($date > today()->format('Y-m-d')) {
            $this->error('Rollover date cannot be in the future.');

            return self::FAILURE;
        }

        $dry = (bool) $this->option('dry-run');
        $onlyInstitute = $this->option('institute') !== null && trim((string) $this->option('institute')) !== ''
            ? (int) $this->option('institute')
            : null;

        $instituteQuery = Institute::whereNull('deleted_at');
        if ($onlyInstitute !== null) {
            $instituteQuery->whereKey($onlyInstitute);
        }
        $instituteIds = $instituteQuery->pluck('id')->all();
        if ($instituteIds === []) {
            $this->info('Nothing to roll over: no institutes in scope.');

            return self::SUCCESS;
        }

        $moved = 0;
        $cancelled = 0;
        $completed = 0;
        $doctorProfiles = [];

        foreach ($instituteIds as $instituteId) {
            $rows = Appointment::where('institute_id', $instituteId)
                ->whereDate('appointment_date', $date)
                ->whereIn('status', ['checked_in', 'in_progress', 'scheduled'])
                ->orderBy('doctor_id')
                ->orderBy('serial_number')
                ->get();

            foreach ($rows as $appointment) {
                // Phase 1b: a post-visit doctor's paid in_progress row means
                // the consultation happened and the money is on record —
                // close it as completed instead of carrying it forward (or
                // auto-cancelling it in the duplicate-slot branch below) and
                // never reverse its fee. Pre-visit paid rows keep the old
                // reverse+carry / cancel+reverse path: money in, service not
                // delivered. Hoisted above the duplicate check on purpose.
                if ($appointment->fee_collected_at !== null
                    && $appointment->status === 'in_progress'
                    && ! $this->collectsFeeBeforeVisit($appointment, $doctorProfiles)) {
                    $this->line("complete #{$appointment->serial_number} (in_progress, fee on record, post-visit doctor) — service rendered, fee kept");

                    if (! $dry) {
                        DB::transaction(function () use ($appointment) {
                            $appointment->update(['status' => 'completed']);
                            QueueAuditLog::create([
                                'institute_id' => $appointment->institute_id,
                                'appointment_id' => $appointment->id,
                                'user_id' => null,
                                'user_type' => 'system',
                                'actor_name' => 'End-of-day rollover',
                                'action' => 'rollover_completed',
                                'old_order' => $appointment->queue_order ?? $appointment->serial_number ?? 0,
                                'new_order' => $appointment->serial_number ?? 0,
                            ]);
                        });
                    }
                    $completed++;

                    continue;
                }

                if (in_array($appointment->status, ['checked_in', 'in_progress'], true)) {
                    $target = $this->nextWorkingDay(
                        (int) $appointment->institute_id,
                        (int) $appointment->doctor_id,
                        $date
                    );

                    // One individual = one serial per doctor per day: a
                    // patient who already holds a serial on the target day
                    // keeps that one — the carried visit is auto-cancelled
                    // instead of being issued a second serial.
                    $duplicate = $queues->duplicateFor(
                        (int) $appointment->institute_id,
                        (int) $appointment->doctor_id,
                        (int) $appointment->patient_id,
                        $target,
                        $appointment->id
                    );

                    if ($duplicate !== null) {
                        $this->line("skip #{$appointment->serial_number} ({$appointment->status}): patient already holds ".
                            "serial #{$duplicate->serial_number} on {$target} — auto-cancelled, not carried");

                        if (! $dry) {
                            DB::transaction(function () use ($appointment) {
                                $reversedAmount = null;
                                if ($appointment->fee_collected_at !== null) {
                                    $reversedAmount = (float) ($appointment->fee_collected_amount ?? 0);

                                    app(MedicalFeePostingService::class)->reverseFeeJournals(
                                        $appointment,
                                        null,
                                        'Queue rollover — duplicate slot on target day, appointment #'.$appointment->id,
                                    );
                                }
                                $appointment->update(array_merge([
                                    'status' => 'cancelled',
                                ], $reversedAmount !== null ? [
                                    'fee_collected_amount' => null,
                                    'fee_collected_by_id' => null,
                                    'fee_collected_by_name' => null,
                                    'fee_collected_at' => null,
                                ] : []));
                                if ($reversedAmount !== null) {
                                    QueueAuditLog::create([
                                        'institute_id' => $appointment->institute_id,
                                        'appointment_id' => $appointment->id,
                                        'user_id' => null,
                                        'user_type' => 'system',
                                        'actor_name' => 'End-of-day rollover',
                                        'action' => 'fee_reversed',
                                        'old_order' => $appointment->serial_number,
                                        'new_order' => $appointment->serial_number,
                                        'amount' => $reversedAmount,
                                    ]);
                                }
                                QueueAuditLog::create([
                                    'institute_id' => $appointment->institute_id,
                                    'appointment_id' => $appointment->id,
                                    'user_id' => null,
                                    'user_type' => 'system',
                                    'actor_name' => 'End-of-day rollover',
                                    'action' => 'auto_cancelled',
                                    'old_order' => $appointment->queue_order ?? $appointment->serial_number ?? 0,
                                    'new_order' => 0,
                                ]);
                            });
                        }
                        $cancelled++;

                        continue;
                    }

                    // Carried visits restart as plain appointments — a new day
                    // means a new check-in, so yesterday's queue state (and
                    // any collected fee) must not travel forward.
                    $status = 'scheduled';
                    $serial = $queues->getNextSerial(
                        (int) $appointment->institute_id,
                        (int) $appointment->doctor_id,
                        $target
                    );

                    $this->line("carry #{$appointment->serial_number} ({$appointment->status}) ".
                        "doctor {$appointment->doctor_id} {$date} → {$target} serial #{$serial}");

                    if (! $dry) {
                        $oldSerial = $appointment->serial_number;
                        DB::transaction(function () use ($appointment, $target, $serial, $status, $oldSerial) {
                            // A new day means a new payment: yesterday's stamp
                            // must not travel forward (pre-visit rows paid
                            // but unseen). Reverse the posted fee journals,
                            // clear the stamp and audit the reversal so the
                            // cash trail stays intact (Phase D).
                            $reversedAmount = null;
                            if ($appointment->fee_collected_at !== null) {
                                $reversedAmount = (float) ($appointment->fee_collected_amount ?? 0);

                                app(MedicalFeePostingService::class)->reverseFeeJournals(
                                    $appointment,
                                    null,
                                    'Queue rollover — appointment #'.$appointment->id,
                                );
                            }
                            $appointment->update(array_merge([
                                'appointment_date' => $target,
                                'serial_number' => $serial,
                                'status' => $status,
                                'queue_order' => null,
                            ], $reversedAmount !== null ? [
                                'fee_collected_amount' => null,
                                'fee_collected_by_id' => null,
                                'fee_collected_by_name' => null,
                                'fee_collected_at' => null,
                            ] : []));
                            if ($reversedAmount !== null) {
                                QueueAuditLog::create([
                                    'institute_id' => $appointment->institute_id,
                                    'appointment_id' => $appointment->id,
                                    'user_id' => null,
                                    'user_type' => 'system',
                                    'actor_name' => 'End-of-day rollover',
                                    'action' => 'fee_reversed',
                                    'old_order' => $serial,
                                    'new_order' => $serial,
                                    'amount' => $reversedAmount,
                                ]);
                            }
                            QueueAuditLog::create([
                                'institute_id' => $appointment->institute_id,
                                'appointment_id' => $appointment->id,
                                'user_id' => null,
                                'user_type' => 'system',
                                'actor_name' => 'End-of-day rollover',
                                'action' => 'rollover',
                                'old_order' => $oldSerial,
                                'new_order' => $serial,
                            ]);
                        });
                    }
                    $moved++;
                } else {
                    $this->line("cancel serial #{$appointment->serial_number} (scheduled, never checked in)");

                    if (! $dry) {
                        DB::transaction(function () use ($appointment) {
                            $appointment->update(['status' => 'cancelled']);
                            QueueAuditLog::create([
                                'institute_id' => $appointment->institute_id,
                                'appointment_id' => $appointment->id,
                                'user_id' => null,
                                'user_type' => 'system',
                                'actor_name' => 'End-of-day rollover',
                                'action' => 'auto_cancelled',
                                'old_order' => $appointment->queue_order ?? $appointment->serial_number ?? 0,
                                'new_order' => 0,
                            ]);
                        });
                    }
                    $cancelled++;
                }
            }
        }

        $this->info(($dry ? '[dry-run] ' : '')."Done: {$moved} carried, {$completed} completed (paid, post-visit), {$cancelled} auto-cancelled for {$date}.");

        return self::SUCCESS;
    }

    /**
     * Whether the appointment's doctor takes the fee before the visit
     * (profile memoised per doctor for the run). Missing profile → treat as
     * post-visit (the safe default here: a paid in_progress row with no
     * profile still holds rendered-service money, so it completes).
     */
    private function collectsFeeBeforeVisit(Appointment $appointment, array &$cache): bool
    {
        $key = $appointment->institute_id.':'.$appointment->doctor_id;
        if (! array_key_exists($key, $cache)) {
            $profile = Doctor::resolveForUser((int) $appointment->doctor_id, (int) $appointment->institute_id);
            $cache[$key] = $profile;
            if ($profile === null) {
                Log::warning('queue-rollover: no doctor profile for visit; defaulting to post-visit fee handling', [
                    'institute_id' => $appointment->institute_id,
                    'doctor_id' => $appointment->doctor_id,
                    'appointment_id' => $appointment->id,
                ]);
            }
        }

        $profile = $cache[$key];

        return $profile !== null && (bool) $profile->collect_fee_before_visit;
    }

    /**
     * Next date after $from with an available slot row for the doctor's
     * profile; next calendar day when the doctor defines no availability.
     */
    private function nextWorkingDay(int $instituteId, int $doctorUserId, string $from): string
    {
        $profile = Doctor::resolveForUser($doctorUserId, $instituteId);
        if (! $profile) {
            return Carbon::parse($from)->addDay()->format('Y-m-d');
        }

        $days = $profile->availabilities()
            ->where('is_available', true)
            ->pluck('day_of_week')
            ->map(fn ($d) => strtolower((string) $d))
            ->unique()
            ->all();

        if ($days === []) {
            return Carbon::parse($from)->addDay()->format('Y-m-d');
        }

        $cursor = Carbon::parse($from);
        for ($i = 0; $i < 30; $i++) {
            $cursor = $cursor->copy()->addDay();
            if (in_array(strtolower($cursor->format('l')), $days, true)) {
                return $cursor->format('Y-m-d');
            }
        }

        return Carbon::parse($from)->addDay()->format('Y-m-d');
    }
}

<?php

namespace App\Console\Commands;

use App\Models\Institute;
use App\Models\Medical\Appointment;
use App\Models\Medical\Doctor;
use App\Models\Medical\QueueAuditLog;
use App\Services\Medical\QueueManager as QueueManagerService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * End-of-day queue rollover (per doctor).
 *
 * - checked_in (and in_progress, downgraded to checked_in) appointments move
 *   to the doctor's next working day with a fresh serial (status kept in
 *   queue, queue_order reset). One audit row (action=rollover) each.
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

        foreach ($instituteIds as $instituteId) {
            $rows = Appointment::where('institute_id', $instituteId)
                ->whereDate('appointment_date', $date)
                ->whereIn('status', ['checked_in', 'in_progress', 'scheduled'])
                ->orderBy('doctor_id')
                ->orderBy('serial_number')
                ->get();

            foreach ($rows as $appointment) {
                if (in_array($appointment->status, ['checked_in', 'in_progress'], true)) {
                    $target = $this->nextWorkingDay(
                        (int) $appointment->institute_id,
                        (int) $appointment->doctor_id,
                        $date
                    );
                    // Downgrade a midnight in-progress visit back to waiting.
                    $status = $appointment->status === 'in_progress' ? 'checked_in' : $appointment->status;
                    $serial = $queues->getNextSerial(
                        (int) $appointment->institute_id,
                        (int) $appointment->doctor_id,
                        $target
                    );

                    $this->line("carry #{$appointment->serial_number} ({$appointment->status}) ".
                        "doctor {$appointment->doctor_id} {$date} → {$target} serial #{$serial}");

                    if (! $dry) {
                        DB::transaction(function () use ($appointment, $target, $serial, $status, $date) {
                            // A new day means a new payment: yesterday's stamp
                            // must not travel forward (pre-visit rows paid
                            // but unseen). Clear it and audit the reversal
                            // so the cash trail stays intact.
                            $reversedAmount = null;
                            if ($appointment->fee_collected_at !== null) {
                                $reversedAmount = (float) ($appointment->fee_collected_amount ?? 0);
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
                                'old_order' => $appointment->serial_number,
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

        $this->info(($dry ? '[dry-run] ' : '')."Done: {$moved} carried, {$cancelled} auto-cancelled for {$date}.");

        return self::SUCCESS;
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

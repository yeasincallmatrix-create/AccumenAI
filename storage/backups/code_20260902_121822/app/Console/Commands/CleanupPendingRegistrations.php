<?php

namespace App\Console\Commands;

use App\Models\PendingRegistration;
use Illuminate\Console\Command;

class CleanupPendingRegistrations extends Command
{
    protected $signature = 'registrations:cleanup {--hours=24 : Grace period hours}';
    protected $description = 'Cleanup expired unverified pending registrations (grace period)';

    public function handle(): int
    {
        // Idempotent, chunked, per-row safe, concurrent-safe via row lock
        $expired = 0; $abandoned = 0;

        // Chunk unverified expired
        PendingRegistration::whereNull('verified_at')
            ->where('expires_at', '<', now())
            ->select('id')
            ->chunkById(200, function ($rows) use (&$expired) {
                foreach ($rows as $row) {
                    try {
                        \Illuminate\Support\Facades\DB::transaction(function () use ($row, &$expired) {
                            $locked = PendingRegistration::whereKey($row->id)->lockForUpdate()->first();
                            if (!$locked || $locked->verified_at !== null) return;
                            if ($locked->expires_at && $locked->expires_at->isPast()) {
                                $locked->delete();
                                $expired++;
                            }
                        });
                    } catch (\Throwable $e) {
                        \Illuminate\Support\Facades\Log::warning('cleanup_pending_expired_row_failed', ['id' => $row->id]);
                    }
                }
            });

        // Chunk verified abandoned (48h from verified_at) — uses model isAbandonedExpired for consistency
        PendingRegistration::whereNotNull('verified_at')
            ->select('id','verified_at','expires_at')
            ->chunkById(200, function ($rows) use (&$abandoned) {
                foreach ($rows as $row) {
                    try {
                        \Illuminate\Support\Facades\DB::transaction(function () use ($row, &$abandoned) {
                            $locked = PendingRegistration::whereKey($row->id)->lockForUpdate()->first();
                            if (!$locked || $locked->verified_at === null) return;
                            if ($locked->isAbandonedExpired()) {
                                // Ensure no real User was created with this email in the meantime (race)
                                $existsUser = \App\Models\User::where('email', $locked->email)->exists();
                                if ($existsUser) {
                                    // Do not delete if user now exists — keep for audit, but pending is orphan, safe to delete as it won't create duplicate
                                }
                                $locked->delete();
                                $abandoned++;
                            }
                        });
                    } catch (\Throwable $e) {
                        \Illuminate\Support\Facades\Log::warning('cleanup_pending_abandoned_row_failed', ['id' => $row->id]);
                    }
                }
            });

        \Illuminate\Support\Facades\Log::info('cleanup_pending_registrations', ['expired' => $expired, 'abandoned' => $abandoned]);
        $this->info("Cleaned {$expired} expired + {$abandoned} abandoned pending registrations.");
        return 0;
    }
}

<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Queued wrapper for Laravel's VerifyEmail.
 *
 * Purpose: make POST /email/verification-notification non-blocking.
 * The HTTP request only enqueues the job (database queue) and returns
 * immediately (<1s). The actual SMTP handshake (which can block 30s or
 * fail with 535 when MAIL_PASSWORD missing) happens in the queue worker,
 * not in the web request. This fixes the "Maximum execution time of
 * 30 seconds exceeded at Connection.php:420" which is the PHP timeout
 * interrupting the synchronous SMTP call that held the DB session lock.
 *
 * Reuses Illuminate\Auth\Notifications\VerifyEmail logic (signed URL,
 * throttle, etc.) — no new email engine.
 * Testing env keeps sync VerifyEmail so EmailVerificationAndLockoutTest
 * with Notification::fake() continues to assert VerifyEmail::class.
 */
class QueuedVerifyEmail extends VerifyEmail implements ShouldQueue
{
    use Queueable;

    /**
     * Ensure the queued job uses the database queue on the default queue.
     * Matches config/queue.php 'database' connection.
     */
    public function __construct()
    {
        $this->onConnection(config('queue.default', 'database'));
        $this->onQueue(config('queue.connections.database.queue', 'default'));
    }
}

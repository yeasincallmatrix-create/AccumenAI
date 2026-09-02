<?php

use App\Jobs\DepreciationRunJob;
use App\Jobs\FxRevaluationJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::job(new DepreciationRunJob)->dailyAt('02:00');
Schedule::job(new FxRevaluationJob)->dailyAt('03:00');

Schedule::command('health:check')->daily();
Schedule::command('metrics:snapshot')->dailyAt('01:00');

// Step 124-L — Safe scheduled monitoring (read-only)
Schedule::command('database:query-stats --json')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('database:slow-queries --json')->everyFifteenMinutes()->withoutOverlapping();
Schedule::command('database:monitor --json')->hourly()->withoutOverlapping();
Schedule::command('database:certify --json')->dailyAt('04:00')->withoutOverlapping();
Schedule::command('database:index-audit --json')->dailyAt('04:30')->withoutOverlapping();

// Step 125-M — Backup & Recovery Operations (safe, read-only by default)
Schedule::command('database:backup-health --json')->daily()->withoutOverlapping();
Schedule::command('database:backup-inventory --json')->daily()->withoutOverlapping();
Schedule::command('database:backup-retention --dry-run --json')->daily()->withoutOverlapping();
Schedule::command('backup:disaster-test --drill --json')->weekly()->sundays()->at('03:00')->withoutOverlapping();
Schedule::command('database:recovery-status --json')->daily()->withoutOverlapping();

// Step 127 — Monthly recurring fee generation
Schedule::command('finance:generate-monthly-fees')->cron('0 6 1 * *')->withoutOverlapping();
Schedule::command('registrations:cleanup')->daily()->withoutOverlapping();
Schedule::command('users:cleanup-inactive')->dailyAt('03:30')->withoutOverlapping();
Schedule::command('users:purge-soft-deleted')->dailyAt('04:00')->withoutOverlapping();

Schedule::command('files:cleanup-orphans --dry-run')->dailyAt('02:00');

// P1 — Automated database backups (RPO < 24h)
Schedule::command('database:backup --type=daily --verify')->dailyAt('01:00');
Schedule::command('database:backup --type=weekly --verify')->weeklyOn(0, '02:00');

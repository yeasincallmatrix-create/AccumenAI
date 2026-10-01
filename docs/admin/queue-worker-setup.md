# Queue Worker Setup (Backup / Restore — Phase 2C)

Backup and restore jobs run **asynchronously** on Laravel's queue. Without a
running worker, queued backups/restores will sit in the `jobs` table and never
execute.

## 1. Configuration (already set in `.env`)

```env
QUEUE_CONNECTION=database     # jobs stored in the jobs table
DB_QUEUE_RETRY_AFTER=3660     # MUST exceed BackupJob timeout (3600s)
```

> `DB_QUEUE_RETRY_AFTER` prevents a second worker from picking up a job that is
> still legitimately running (default is 90s, which is too low for backups).

Tests use `QUEUE_CONNECTION=sync` (jobs run inline) — never change this in
`.env.testing`.

## 2. Starting the worker

Run from the project root:

```bash
php artisan queue:work --queue=default,notifications --sleep=3 --tries=2 --timeout=3600
```

- `default` — `BackupJob`, `RestoreJob`
- `notifications` — email mailables (`EmailOtpMail`, backup/restore mails)
- `--timeout=3600` — matches the longest job (BackupJob `timeout = 3600`)

### Recommended: keep it running

- **Development (XAMPP):** run the command above in a separate terminal window.
- **Production (Supervisor):**

```ini
[program:accumenai-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/artisan queue:work --queue=default,notifications --sleep=3 --tries=2 --timeout=3600
autostart=true
autorestart=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/path/to/storage/logs/worker.log
stopwaitsecs=3600
```

## 3. Verifying the pipeline

```bash
# should be empty while a worker is idle
php artisan queue:monitor database:default,notifications,backup 2>/dev/null || php artisan queue:table

# clear stale jobs (e.g. after a crash)
php artisan queue:clear
php artisan queue:clear --queue=notifications

# process pending jobs once and exit (smoke test)
php artisan queue:work --queue=default,notifications --stop-when-empty
```

Failed jobs land in `failed_jobs`:

```bash
php artisan queue:failed
php artisan queue:retry all      # or a specific UUID
php artisan queue:flush          # drop all failed jobs
```

## 4. How the flow works

1. User clicks **Backup Now** → `BackupController@store` pre-flights the Drive
   connection, creates a **pending** `backups` row, dispatches `BackupJob`,
   returns `{success, backup_id}`.
2. The UI polls `GET /tenant/backup/{id}/progress` every 2 s and renders the
   progress modal (percent/stage/message/chunk counters).
3. `BackupJob` runs `BackupService::executeBackup()` (status `uploading` →
   `completed` / `failed`), then queues the completion/failure email.
4. Restore: OTP is verified **synchronously** (`RestoreService::verifyOtpToken`),
   a `restore_logs` row is created, `RestoreJob` is dispatched, and the UI polls
   `GET /tenant/backup/restore/{logId}/progress`.
5. Emails are queued to the `notifications` queue; a mail failure never fails
   or retries an otherwise successful job.

## 5. Troubleshooting

| Symptom | Cause / fix |
|---|---|
| Backup stuck at "Queued..." | No worker running — start it (section 2). |
| Backup stuck at a % with chunks | Worker died mid-job — after `timeout` the job retries (tries=2); check `failed_jobs`. |
| Duplicate backups appearing | `DB_QUEUE_RETRY_AFTER` too low — must be > 3600. |
| `The [x] queue connection has not been configured` | Wrong `queue:clear` syntax — use `--queue=<name>`, not a positional argument. |
| Emails never arrive | Worker not consuming the `notifications` queue — add it to `--queue=`. |

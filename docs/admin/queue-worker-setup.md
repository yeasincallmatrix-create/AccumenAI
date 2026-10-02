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

- **Development (Windows):** do **not** run `queue:work` bare — use
  `.\scripts\run-worker.ps1` (section 7), it detects and restarts a hung worker.
- **Production (Supervisor):**

```ini
[program:accumenai-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/artisan queue:work --queue=default,notifications --sleep=3 --tries=2 --timeout=3600 --max-time=3600
autostart=true
autorestart=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/path/to/storage/logs/worker.log
stopwaitsecs=3600
```

- `--max-time=3600` — worker self-restarts hourly (prevents a long-lived process)
- `numprocs=2` — two parallel workers
- `stopwaitsecs=3600` — graceful shutdown waits up to the job timeout

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
| Backup stuck at "Queued..." | No worker running — start it (section 2/7). |
| Backup stuck at a % with chunks | Worker died mid-job — after `timeout` the job retries (tries=2); check `failed_jobs`. |
| Duplicate backups appearing | `DB_QUEUE_RETRY_AFTER` too low — must be > 3600. |
| `The [x] queue connection has not been configured` | Wrong `queue:clear` syntax — use `--queue=<name>`, not a positional argument. |
| Emails never arrive | Worker not consuming the `notifications` queue — add it to `--queue=`. |
| Worker alive but nothing is picked up | Case C hang — see section 6; use `run-worker.ps1`. |

## 6. ⚠️ Windows development limitation

Two protections Laravel relies on do not exist on Windows PHP:

| Mechanism | Linux (prod) | Windows (PHP 8.2.12) |
|---|---|---|
| `pcntl` → `pcntl_alarm()` → `--timeout` | works | **ABSENT** — `Worker::registerTimeoutHandler()` never runs, so `--timeout=3600` is a no-op |
| PDO read/write timeout | mysqlnd read timeout | **not exposed by pdo_mysql** — `PDO::ATTR_TIMEOUT` bounds the CONNECT handshake only |

Empirical proof (2026-10-02, PHP 8.2.12 + MariaDB 10.4.32):

```
SELECT SLEEP(45)                → returned after 45s   (no read timeout)
SELECT SLEEP(90)                → returned after 90s   (mysqlnd.net_read_timeout = 86400)
SELECT @@session.wait_timeout   → 60                   (INIT_COMMAND applied) ✅
```

Consequences when the DB connection drops mid-query (the observed "Case C" hang):

- the PDO read blocks with no timeout and no exception → nothing is logged
- `--timeout` never fires (no pcntl) → nothing kills the job
- `queue:restart` never fires (the flag is only read *between* loop iterations)
- result: process alive, CPU frozen, jobs stuck at `attempts=0, reserved_at=NULL`

**Mitigation stack on Windows:**

1. `scripts/run-worker.ps1` — **mandatory**, external stall detector (section 7)
2. `wait_timeout=60` via `PDO::MYSQL_ATTR_INIT_COMMAND` — closes idle connections (not mid-query)
3. `ReconnectDatabase` middleware — fresh PDO handle before every job
4. `worker_heartbeats` + `queue:health` — visibility (section 8)
5. `--max-time=3600` — hourly self-restart (only reached between iterations)

**Production (Linux):** `pcntl` is present, so `--timeout=3600` works as designed;
Supervisor plus the `queue:health` cron is sufficient.

## 7. Dev runner (Windows) — `scripts/run-worker.ps1`

Run in a dedicated PowerShell window and leave it running:

```powershell
Set-ExecutionPolicy -Scope Process Bypass -Force   # only if blocked
.\scripts\run-worker.ps1
```

| Flag | Default | Meaning |
|---|---|---|
| `-HealthCheckInterval` | 30 | seconds between checks |
| `-StallSeconds` | 60 | stdout silent for this long = suspicious |
| `-JobStallAfter` | 3900 | reserved longer than this (> BackupJob 3600) = stuck |
| `-MaxRestarts` | 50 | give up after N restarts |
| `-MaxTime` | 3600 | passed through to `queue:work --max-time` |

Restart rules (each requires the child to be **alive** and stdout **static** for
`-StallSeconds`):

```
poll-hang : available_jobs > 0 AND reserved_jobs = 0       → restart
job-hang  : stuck_jobs > 0 (reserved longer than JobStallAfter) → restart
crashed   : child process already exited                    → restart (exit code)
```

A job that is legitimately running keeps its row **reserved**, so a long silent
backup is never mistaken for a hang (`reserved_jobs > 0` blocks the poll-hang rule).

Logs: worker stdout `storage/logs/worker-run.log`, worker stderr
`storage/logs/worker-run.log.err`, runner status on its own console
(restart count + reason per detection).

## 8. Health monitoring — `php artisan queue:health`

```bash
php artisan queue:health                      # human output, exit 0/1
php artisan queue:health --json               # machine readable
php artisan queue:health --stale-after=300    # tune staleness window
```

**FAIL (exit 1)** when any of:
- pending jobs > 0 and no worker seen within `--stale-after`
- a job reserved older than `--stale-after`
- no heartbeats exist while jobs are pending

**PASS (exit 0)** when the queue is empty — an idle worker with an old heartbeat is
fine: heartbeats record **activity**, not liveness, so an idle worker is never
falsely reported stale.

Cron (Linux, every 5 minutes):

```
*/5 * * * * cd /path/to/app && php artisan queue:health --stale-after=300 >> /var/log/queue-health.log 2>&1
```

Heartbeat cleanup (7 days), e.g. from a scheduled command:

```php
\App\Models\WorkerHeartbeat::purgeOld(7);
```

## 9. Rank of protections

| Protection | Works on Windows? | Scope |
|---|---|---|
| `wait_timeout=60` (INIT_COMMAND) | ✅ proven | idle connections only |
| `PDO::ATTR_TIMEOUT=30` | ⚠️ connect handshake only | slow/unreachable DB fails fast |
| `retry_after=3660` / `after_commit=false` | ✅ | dispatch + visibility |
| `ReconnectDatabase` middleware | ✅ | between jobs |
| `WorkerHeartbeat` + `queue:health` | ✅ | detection / visibility |
| `--timeout=3600` | ❌ | requires pcntl |
| `--max-time=3600` | ⚠️ | between iterations only |
| **`run-worker.ps1` stall detector** | ✅ | **the only guard that catches a mid-query block** |

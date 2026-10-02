<#
.SYNOPSIS
    Robust queue worker runner for Windows development.

.DESCRIPTION
    Starts `php artisan queue:work` and supervises it from outside the
    process. Because Windows PHP has no pcntl, Laravel's --timeout watchdog
    never fires, so a worker blocked inside a PDO read can hang forever with
    zero log output. This script detects that state externally and restarts.

    Stall = (worker process alive) AND (stdout has not grown for $StallSeconds)
            AND (jobs are waiting to be picked up / a job is stuck reserved)

    Jobs that are legitimately running keep a row reserved, so a long silent
    job is NOT treated as a stall (reserved_jobs > 0 blocks the poll-hang rule).

.USAGE
    .\scripts\run-worker.ps1
    .\scripts\run-worker.ps1 -StallSeconds 45 -HealthCheckInterval 15
#>

param(
    [int]$HealthCheckInterval = 30,
    [int]$StallSeconds = 60,
    [int]$JobStallAfter = 3900,
    [int]$MaxRestarts = 50,
    [int]$MaxTime = 3600,
    [string]$Queue = 'default,notifications'
)

$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path -Parent $PSScriptRoot
Set-Location $projectRoot

$logFile = Join-Path $projectRoot 'storage\logs\worker-run.log'
$errFile = Join-Path $projectRoot 'storage\logs\worker-run.log.err'

$restartCount = 0
$lastLogSize = 0
$lastGrowthAt = Get-Date

function Log-Message {
    param([string]$msg, [string]$color = 'White')
    $ts = Get-Date -Format 'HH:mm:ss'
    Write-Host "[$ts] $msg" -ForegroundColor $color
}

function Start-WorkerProcess {
    Log-Message "Starting worker (queues=$Queue, max-time=${MaxTime}s)..." 'Green'
    $script:lastLogSize = 0
    $script:lastGrowthAt = Get-Date
    $argList = @(
        'artisan', 'queue:work',
        "--queue=$Queue",
        '--sleep=3',
        '--tries=2',
        "--timeout=$MaxTime",
        "--max-time=$MaxTime"
    )
    return Start-Process -FilePath 'php' `
        -ArgumentList $argList `
        -WorkingDirectory $projectRoot `
        -RedirectStandardOutput $logFile `
        -RedirectStandardError $errFile `
        -PassThru -NoNewWindow
}

function Stop-WorkerProcess {
    param($proc)
    if ($proc -and -not $proc.HasExited) {
        Log-Message "Killing worker PID $($proc.Id)" 'Yellow'
        Stop-Process -Id $proc.Id -Force -ErrorAction SilentlyContinue
        Start-Sleep -Seconds 1
    }
}

function Get-LogSize {
    if (Test-Path $logFile) { return (Get-Item $logFile).Length }
    return 0
}

function Get-QueueHealth {
    $json = & php artisan queue:health --json --stale-after=$JobStallAfter 2>$null
    if ($LASTEXITCODE -ne 0 -and -not $json) { return $null }
    try {
        return ($json | Out-String | ConvertFrom-Json)
    } catch {
        return $null
    }
}

function Test-WorkerStalled {
    param($proc)

    if ($proc.HasExited) {
        return @{ Stalled = $true; Reason = "process-exited:code=$($proc.ExitCode)" }
    }

    $size = Get-LogSize
    if ($size -gt $lastLogSize) {
        $script:lastLogSize = $size
        $script:lastGrowthAt = Get-Date
    }

    $quietSeconds = [int]((Get-Date) - $lastGrowthAt).TotalSeconds
    $health = Get-QueueHealth

    if (-not $health) {
        return @{ Stalled = $false; Reason = 'health-check-unavailable' }
    }

    $available = [int]$health.available_jobs
    $reserved  = [int]$health.reserved_jobs
    $stuck     = [int]$health.stuck_jobs

    if ($quietSeconds -ge $StallSeconds) {
        if ($available -gt 0 -and $reserved -eq 0) {
            return @{
                Stalled = $true
                Reason  = "poll-hang: $available job(s) waiting, no stdout for ${quietSeconds}s"
            }
        }
        if ($stuck -gt 0) {
            return @{
                Stalled = $true
                Reason  = "job-hang: $stuck job reserved > ${JobStallAfter}s, no stdout for ${quietSeconds}s"
            }
        }
    }

    return @{
        Stalled = $false
        Reason  = "ok quiet=${quietSeconds}s available=$available reserved=$reserved"
    }
}

Log-Message '=== AccumenAI Worker Runner ===' 'Cyan'
Log-Message "Project:   $projectRoot"
Log-Message "Log:       $logFile"
Log-Message "Interval:  ${HealthCheckInterval}s   Stall: ${StallSeconds}s   Job-stall: ${JobStallAfter}s"

$proc = $null

try {
    $proc = Start-WorkerProcess

    while ($restartCount -lt $MaxRestarts) {
        Start-Sleep -Seconds $HealthCheckInterval

        $check = Test-WorkerStalled -proc $proc

        if ($check.Stalled) {
            $restartCount++
            Log-Message "RESTART #$restartCount - reason: $($check.Reason)" 'Red'
            Stop-WorkerProcess $proc
            Start-Sleep -Seconds 2
            $proc = Start-WorkerProcess
        } else {
            Log-Message "worker healthy - $($check.Reason)" 'DarkGray'
        }
    }

    Log-Message "Max restarts ($MaxRestarts) reached - exiting" 'Red'
} finally {
    Stop-WorkerProcess $proc
}

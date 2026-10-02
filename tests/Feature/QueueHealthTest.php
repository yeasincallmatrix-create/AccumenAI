<?php

namespace Tests\Feature;

use App\Models\WorkerHeartbeat;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class QueueHealthTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        WorkerHeartbeat::query()->delete();
        DB::table('jobs')->delete();
    }

    private function insertPendingJob(): void
    {
        DB::table('jobs')->insert([
            'queue'        => 'default',
            'payload'      => '{}',
            'attempts'     => 0,
            'reserved_at'  => null,
            'available_at' => now()->getTimestamp(),
            'created_at'   => now()->getTimestamp(),
        ]);
    }

    public function test_health_fails_when_jobs_pending_and_no_heartbeat(): void
    {
        $this->insertPendingJob();

        $this->artisan('queue:health')->assertExitCode(1);
    }

    public function test_health_passes_when_idle_even_without_worker(): void
    {
        $this->artisan('queue:health')->assertExitCode(0);
    }

    public function test_health_passes_with_active_worker(): void
    {
        WorkerHeartbeat::create([
            'worker_id'      => gethostname() . ':' . getmypid(),
            'hostname'       => gethostname(),
            'pid'            => getmypid(),
            'queue'          => 'default',
            'started_at'     => now(),
            'last_seen_at'   => now(),
            'jobs_processed' => 5,
        ]);

        $this->artisan('queue:health', ['--stale-after' => 300])->assertExitCode(0);
    }

    public function test_health_fails_with_stale_worker_and_pending(): void
    {
        WorkerHeartbeat::create([
            'worker_id'      => 'old:99999',
            'hostname'       => 'test',
            'pid'            => 99999,
            'queue'          => 'default',
            'started_at'     => now()->subHour(),
            'last_seen_at'   => now()->subMinutes(10),
            'jobs_processed' => 100,
        ]);

        $this->insertPendingJob();

        $this->artisan('queue:health', ['--stale-after' => 300])->assertExitCode(1);
    }

    public function test_middleware_classes_exist(): void
    {
        $this->assertTrue(class_exists(\App\Jobs\Middleware\ReconnectDatabase::class));
        $this->assertTrue(class_exists(\App\Jobs\Middleware\WorkerHeartbeat::class));
    }

    public function test_backup_job_has_middleware(): void
    {
        $job = new \App\Jobs\BackupJob(1, 1, 1, 'test@example.com');
        $middleware = $job->middleware();

        $this->assertCount(2, $middleware);
        $this->assertInstanceOf(\App\Jobs\Middleware\ReconnectDatabase::class, $middleware[0]);
        $this->assertInstanceOf(\App\Jobs\Middleware\WorkerHeartbeat::class, $middleware[1]);
    }

    public function test_heartbeat_records_activity(): void
    {
        WorkerHeartbeat::recordJob();

        $workerId = gethostname() . ':' . getmypid();
        $row = WorkerHeartbeat::where('worker_id', $workerId)->first();

        $this->assertNotNull($row);
        $this->assertEquals(1, $row->jobs_processed);

        WorkerHeartbeat::recordJob();

        $this->assertEquals(2, $row->fresh()->jobs_processed);
    }
}

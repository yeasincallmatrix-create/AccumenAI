<?php

namespace Tests\Feature;

use App\Models\Institute;
use App\Services\System\ArchiveService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ArchiveSystemTest extends TestCase
{
    use DatabaseTransactions;

    public function test_archive_keeps_original_ids(): void
    {
        $service = app(ArchiveService::class);

        // Create old attendance record
        $institute = Institute::first() ?? Institute::create(['name' => 'Archive Test', 'slug' => 'archive-'.uniqid(), 'country' => 'Bangladesh', 'status' => 'active']);
        $batch = \App\Models\Batch::first();
        $student = \App\Models\Student::first();

        if ($batch && $student) {
            $oldDate = now()->subYears(4)->toDateString();
            $id = DB::table('attendance')->insertGetId([
                'institute_id' => $institute->id,
                'batch_id' => $batch->id,
                'student_id' => $student->id,
                'class_date' => $oldDate,
                'status' => 'present',
                'created_at' => $oldDate,
                'updated_at' => $oldDate,
            ]);

            $result = $service->archive('attendance', dryRun: false);

            $this->assertGreaterThanOrEqual(1, $result['archived']);

            $this->assertDatabaseHas('attendance_archive', [
                'original_id' => $id,
            ]);

            // Original still exists (never auto-delete)
            $this->assertDatabaseHas('attendance', ['id' => $id]);

            // Cleanup
            DB::table('attendance')->where('id', $id)->delete();
            DB::table('attendance_archive')->where('original_id', $id)->delete();
        } else {
            $this->markTestSkipped('No batch/student for archive test');
        }
    }

    public function test_restore_archive_data(): void
    {
        $service = app(ArchiveService::class);

        // Create and archive
        $institute = Institute::first() ?? Institute::create(['name' => 'Archive Restore', 'slug' => 'arch-restore-'.uniqid(), 'country' => 'Bangladesh', 'status' => 'active']);
        $batch = \App\Models\Batch::first();
        $student = \App\Models\Student::first();

        if ($batch && $student) {
            $oldDate = now()->subYears(4)->toDateString();
            $id = DB::table('attendance')->insertGetId([
                'institute_id' => $institute->id,
                'batch_id' => $batch->id,
                'student_id' => $student->id,
                'class_date' => $oldDate,
                'status' => 'present',
                'created_at' => $oldDate,
                'updated_at' => $oldDate,
            ]);

            $service->archive('attendance', dryRun: false);

            // Simulate deletion then restore
            DB::table('attendance')->where('id', $id)->delete();
            $restored = $service->restore('attendance', [$id]);

            $this->assertEquals(1, $restored);
            $this->assertDatabaseHas('attendance', ['id' => $id]);

            // Cleanup
            DB::table('attendance')->where('id', $id)->delete();
            DB::table('attendance_archive')->where('original_id', $id)->delete();
        } else {
            $this->markTestSkipped('No batch/student');
        }
    }

    public function test_dry_run_does_not_archive(): void
    {
        $service = app(ArchiveService::class);
        $before = DB::table('attendance_archive')->count();
        $service->archive('attendance', dryRun: true);
        $after = DB::table('attendance_archive')->count();
        $this->assertEquals($before, $after);
    }

    public function test_rules_match_spec(): void
    {
        $this->assertEquals(3, ArchiveService::RULES['attendance']['years']);
        $this->assertEquals(1, ArchiveService::RULES['notifications']['years']);
        $this->assertEquals(5, ArchiveService::RULES['audit_logs']['years']);
    }

    public function test_artisan_command(): void
    {
        $this->artisan('system:archive', ['--dry-run' => true])
            ->assertExitCode(0);
    }

    public function test_never_auto_deletes(): void
    {
        $service = app(ArchiveService::class);
        $institute = Institute::first() ?? Institute::create(['name' => 'Never Delete', 'slug' => 'never-'.uniqid(), 'country' => 'Bangladesh', 'status' => 'active']);
        $batch = \App\Models\Batch::first();
        $student = \App\Models\Student::first();

        if ($batch && $student) {
            $oldDate = now()->subYears(4)->toDateString();
            $countBefore = DB::table('attendance')->count();
            $service->archive('attendance', dryRun: false);
            $countAfter = DB::table('attendance')->count();
            $this->assertEquals($countBefore, $countAfter, 'Archive must not delete originals automatically');
        } else {
            $this->markTestSkipped('No data');
        }
    }
}

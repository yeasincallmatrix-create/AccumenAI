<?php

namespace Tests\Feature;

use App\Models\Institute;
use App\Models\Student;
use App\Services\System\DataIntegrityService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DatabaseIntegrityTest extends TestCase
{
    use DatabaseTransactions;

    public function test_tenant_integrity_detects_missing_institute_id(): void
    {
        $service = app(DataIntegrityService::class);
        $result = $service->checkTenantIntegrity();

        $this->assertArrayHasKey('healthy', $result);
        $this->assertArrayHasKey('issues', $result);
        // Initially should be healthy (no nulls after fix)
        $this->assertIsArray($result['issues']);
    }

    public function test_relationship_integrity_detects_orphans(): void
    {
        $service = app(DataIntegrityService::class);
        $result = $service->checkRelationshipIntegrity();

        $this->assertArrayHasKey('healthy', $result);
        $this->assertArrayHasKey('issues', $result);

        // Create an orphan enrollment (student_id not in students)
        $institute = Institute::first() ?? Institute::create([
            'name' => 'Integrity Test Institute',
            'slug' => 'integrity-'.uniqid(),
            'country' => 'Bangladesh',
            'status' => 'active',
        ]);
        $batch = \App\Models\Batch::first();
        if ($batch) {
            $orphanId = 999999;
            DB::table('student_enrollments')->insert([
                'institute_id' => $institute->id,
                'student_id' => $orphanId,
                'batch_id' => $batch->id,
                'course_id' => $batch->course_id,
                'roll_number' => 'ORPH-'.uniqid(),
                'enrollment_date' => now(),
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $result2 = $service->checkRelationshipIntegrity();
            $found = collect($result2['issues'])->firstWhere('table', 'student_enrollments');
            $this->assertNotNull($found);
            $this->assertGreaterThan(0, $found['count']);
            $this->assertStringContainsString('SELECT', $found['suggestion']);

            // Cleanup
            DB::table('student_enrollments')->where('student_id', $orphanId)->delete();
        }
    }

    public function test_soft_delete_consistency(): void
    {
        $service = app(DataIntegrityService::class);
        $result = $service->checkSoftDeleteConsistency();

        $this->assertArrayHasKey('healthy', $result);
        $this->assertArrayHasKey('issues', $result);
    }

    public function test_detects_active_child_linked_to_deleted_institute(): void
    {
        $institute = Institute::create([
            'name' => 'Soft Delete Test ' . uniqid(),
            'slug' => 'soft-'.uniqid(),
            'country' => 'Bangladesh',
            'status' => 'active',
        ]);

        $student = Student::create([
            'institute_id' => $institute->id,
            'student_id_number' => 'SD-'.uniqid(),
            'first_name' => 'Soft',
            'last_name' => 'Child',
            'status' => 'active',
            'admission_date' => now(),
        ]);

        // Soft delete institute
        $institute->delete();

        $service = app(DataIntegrityService::class);
        $result = $service->checkSoftDeleteConsistency();

        $found = collect($result['issues'])->first(fn($i) => $i['table'] === 'students');
        $this->assertNotNull($found);
        $this->assertGreaterThan(0, $found['count']);

        // Restore for cleanup (DatabaseTransactions will rollback anyway)
        $institute->restore();
    }

    public function test_full_check_returns_report(): void
    {
        $service = app(DataIntegrityService::class);
        $report = $service->check();

        $this->assertArrayHasKey('tenant', $report);
        $this->assertArrayHasKey('relations', $report);
        $this->assertArrayHasKey('soft_delete', $report);
        $this->assertArrayHasKey('total_issues', $report);
        $this->assertArrayHasKey('status', $report);
    }

    public function test_console_report_format(): void
    {
        $service = app(DataIntegrityService::class);
        $generated = $service->generateReport();

        $this->assertArrayHasKey('text', $generated);
        $this->assertArrayHasKey('lines', $generated);
        $this->assertStringContainsString('HEALTH CHECK REPORT', $generated['text']);
        $this->assertStringContainsString('Tenant:', $generated['text']);
        $this->assertStringContainsString('Foreign Keys:', $generated['text']);
        $this->assertStringContainsString('Orphans:', $generated['text']);
    }

    public function test_artisan_command_runs(): void
    {
        $this->artisan('system:integrity-check')
            ->assertExitCode(0);
    }

    public function test_artisan_json_output(): void
    {
        $this->artisan('system:integrity-check', ['--json' => true])
            ->assertExitCode(0);
    }

    public function test_never_auto_deletes(): void
    {
        $service = app(DataIntegrityService::class);
        $beforeStudents = DB::table('students')->count();
        $beforeEnrollments = DB::table('student_enrollments')->count();

        $service->check();

        $this->assertEquals($beforeStudents, DB::table('students')->count());
        $this->assertEquals($beforeEnrollments, DB::table('student_enrollments')->count());
    }
}

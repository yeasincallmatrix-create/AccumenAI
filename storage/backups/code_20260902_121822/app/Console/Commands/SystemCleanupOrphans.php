<?php

namespace App\Console\Commands;

use App\Services\System\OrphanCleanupService;
use Illuminate\Console\Command;

class SystemCleanupOrphans extends Command
{
    protected $signature = 'system:cleanup-orphans {--dry-run : Dry run (default)} {--execute : Execute real cleanup}';

    protected $description = 'Step 111 — Orphan Cleanup (dry-run by default, --execute to delete)';

    public function handle(OrphanCleanupService $service): int
    {
        $isExecute = $this->option('execute');
        $isDry = $this->option('dry-run') || ! $isExecute;

        if ($isExecute) {
            $this->warn("==================================================");
            $this->warn("ORPHAN CLEANUP — REAL EXECUTION");
            $this->warn("==================================================");
            $result = $service->execute(dryRun: false);

            $this->info("Backup: {$result['backup']->filename} (checksum: {$result['backup']->checksum}) Verified: {$result['backup']->status}");
            $this->info("Deleted: batches {$result['deleted']['batches']}, enrollments {$result['deleted']['enrollments']}, institution_users {$result['deleted']['institution_users']}, dependents {$result['deleted']['dependents']}");
            $this->info("Blocked: batches ".($result['blocked']['batches'] ?? 0).", enrollments ".($result['blocked']['enrollments'] ?? 0));
            $this->info("After: batches {$result['after']['batches']}, enrollments {$result['after']['enrollments']}, institution_users {$result['after']['institution_users']}");
            $this->info("Audit logged to audit_logs (orphan_cleanup_completed)");
            return 0;
        }

        // Dry run
        $result = $service->dryRun();

        $this->line("==================================================");
        $this->line("ORPHAN CLEANUP DRY RUN");
        $this->line("==================================================");
        $this->line("");
        $this->line("Batches:");
        $this->line("{$result['counts']['batches']} detected");
        if (! empty($result['identified']['batches'])) {
            $this->line("  ID | Institute | Course | Branch | Reason | Created");
            foreach (array_slice($result['identified']['batches'], 0, 10) as $b) {
                $this->line("  {$b['id']} | {$b['institute_id']} | {$b['course_id']} | {$b['branch_id']} | {$b['reason']} | {$b['created_at']}");
            }
            if (count($result['identified']['batches']) > 10) $this->line("  ... and ".(count($result['identified']['batches'])-10)." more");
        }

        $this->line("");
        $this->line("Enrollments:");
        $this->line("{$result['counts']['enrollments']} detected");
        if (! empty($result['identified']['enrollments'])) {
            $this->line("  ID | Student | Batch | Institute | Reason | Created");
            foreach (array_slice($result['identified']['enrollments'], 0, 10) as $e) {
                $this->line("  {$e['id']} | {$e['student_id']} | {$e['batch_id']} | {$e['institute_id']} | {$e['reason']} | {$e['created_at']}");
            }
            if (count($result['identified']['enrollments']) > 10) $this->line("  ... and ".(count($result['identified']['enrollments'])-10)." more");
        }

        $this->line("");
        $this->line("Institution Users:");
        $this->line("{$result['counts']['institution_users']} detected");
        foreach ($result['identified']['institution_users'] as $iu) {
            $this->line("  {$iu['id']} | {$iu['user_id']} | {$iu['institute_id']} | {$iu['email']} | {$iu['reason']} | {$iu['created_at']}");
        }

        $this->line("");
        $this->line("Dependent records:");
        foreach ($result['dependencies']['batches'] as $id => $deps) {
            if (! empty($deps)) $this->line("  Batch $id: ".json_encode($deps));
        }
        foreach ($result['dependencies']['enrollments'] as $id => $deps) {
            if (! empty($deps)) $this->line("  Enrollment $id: ".json_encode($deps));
        }

        $this->line("");
        $this->line("SAFE TO DELETE:");
        $this->line("  batches: {$result['safe']['batches']}, enrollments: {$result['safe']['enrollments']}, institution_users: {$result['safe']['institution_users']}");
        $this->line("DELETE_WITH_DEPENDENCIES:");
        $this->line("  batches: {$result['with_deps']['batches']}, enrollments: {$result['with_deps']['enrollments']}");
        $this->line("BLOCKED:");
        $this->line("  batches: {$result['blocked']['batches']}, enrollments: {$result['blocked']['enrollments']}, institution_users: {$result['blocked']['institution_users']}");

        $this->line("");
        $this->warn("No database changes performed.");

        return 0;
    }
}

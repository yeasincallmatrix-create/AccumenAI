<?php

namespace App\Console\Commands;

use App\Models\Institute;
use App\Services\AcademicSetupService;
use Illuminate\Console\Command;

class AcademicSetupCommand extends Command
{
    protected $signature = 'academic:setup {--all : Backfill all education institutes} {--institute= : Specific institute ID}';

    protected $description = 'Ensure academic defaults (year + global grade scale) for education institutes. Idempotent.';

    public function handle(AcademicSetupService $service): int
    {
        $instituteId = $this->option('institute');
        $all = $this->option('all');

        if ($instituteId !== null) {
            $id = (int) $instituteId;
            $institute = Institute::withoutGlobalScopes()->find($id);
            if (! $institute) {
                $this->error("Institute #{$id} not found.");
                return self::FAILURE;
            }
            if (! \App\Support\InstituteDomain::isAcademic($institute)) {
                $this->warn("Institute #{$id} is not academic (domain=".\App\Support\InstituteDomain::fromInstitute($institute).", industry={$institute->industry}); skipping.");
                return self::SUCCESS;
            }
            $result = $service->ensureDefaults($institute);
            $this->reportOne($institute, $result);
            return self::SUCCESS;
        }

        if ($all) {
            $institutes = Institute::withoutGlobalScopes()->where('status', 'active')->get()->filter(fn($inst) => \App\Support\InstituteDomain::isAcademic($inst));
            if ($institutes->isEmpty()) {
                $this->info('No active education institutes found.');
                return self::SUCCESS;
            }
            $createdYears = 0;
            $createdScales = 0;
            foreach ($institutes as $institute) {
                $result = $service->ensureDefaults($institute);
                if ($result['academic_year']['created']) $createdYears++;
                if ($result['grade_scale']['created']) $createdScales++;
                $this->reportOne($institute, $result);
            }
            $this->newLine();
            $this->info("Summary: {$institutes->count()} education institutes | {$createdYears} year(s) created | {$createdScales} grade scale(s) created (global singleton).");
            return self::SUCCESS;
        }

        // No options: pick the current workspace / first education institute or explain
        $this->warn('Specify --institute=<id> or --all.');
        $this->line('  php artisan academic:setup --institute=123');
        $this->line('  php artisan academic:setup --all');
        return self::FAILURE;
    }

    private function reportOne(Institute $institute, array $result): void
    {
        $year = $result['academic_year'];
        $scale = $result['grade_scale'];

        $yearMsg = $year['created']
            ? "<info>created</info> year {$year['code']} (#{$year['id']})"
            : "year exists (#{$year['id']} {$year['code']})";
        $scaleMsg = $scale['created']
            ? "<info>created</info> grade scale #{$scale['id']}"
            : "grade scale exists (#{$scale['id']})";

        $this->line("  Institute #{$institute->id} ({$institute->name}): {$yearMsg} | {$scaleMsg}");
    }
}

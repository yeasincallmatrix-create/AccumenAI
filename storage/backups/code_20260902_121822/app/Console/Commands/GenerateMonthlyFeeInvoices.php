<?php

namespace App\Console\Commands;

use App\Services\Education\MonthlyFeeGenerationService;
use Illuminate\Console\Command;

class GenerateMonthlyFeeInvoices extends Command
{
    protected $signature = 'finance:generate-monthly-fees
        {--month= : Billing period month (YYYY-MM). Defaults to current month.}
        {--institute= : Specific institute ID to process.}
        {--branch= : Specific branch ID to process.}
        {--dry-run : Preview without creating invoices.}';

    protected $description = 'Generate monthly recurring fee invoices for active students';

    public function handle(MonthlyFeeGenerationService $service): int
    {
        $month = $this->option('month')
            ? $this->option('month') . '-01'
            : now()->format('Y-m-01');

        $instituteId = $this->option('institute');
        $branchId = $this->option('branch');
        $dryRun = $this->option('dry-run');

        $this->info($dryRun ? 'DRY RUN — No invoices will be created.' : '');
        $this->info('Generating monthly fees for: ' . $month);
        $this->newLine();

        $query = \App\Models\Institute::query();
        if ($instituteId) {
            $query->where('id', $instituteId);
        }

        $institutes = $query->get();

        $totalStructures = 0;
        $totalStudents = 0;
        $totalGenerated = 0;
        $totalSkipped = 0;
        $totalErrors = 0;

        foreach ($institutes as $institute) {
            $this->info('Processing Institute: ' . $institute->name . ' (ID: ' . $institute->id . ')');

            $result = $service->generate(
                $institute->id,
                $branchId ? (int) $branchId : null,
                $month,
                $dryRun,
            );

            $totalStructures += $result['structures_processed'];
            $totalStudents += $result['students_checked'];
            $totalGenerated += $result['invoices_generated'];
            $totalSkipped += $result['skipped'];

            if (! empty($result['errors'])) {
                $totalErrors += count($result['errors']);
                foreach ($result['errors'] as $error) {
                    $this->error('  ERROR: ' . $error);
                }
            }

            $this->line("  Structures: {$result['structures_processed']} | Students: {$result['students_checked']} | Generated: {$result['invoices_generated']} | Skipped: {$result['skipped']}");
            $this->newLine();
        }

        $this->info('=== Summary ===');
        $this->line("Institutes processed: {$institutes->count()}");
        $this->line("Fee structures processed: {$totalStructures}");
        $this->line("Students checked: {$totalStudents}");
        $this->line("Invoices generated: {$totalGenerated}");
        $this->line("Already generated/skipped: {$totalSkipped}");
        $this->line("Errors: {$totalErrors}");

        return $totalErrors > 0 ? 1 : 0;
    }
}

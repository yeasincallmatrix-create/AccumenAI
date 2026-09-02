<?php

namespace App\Console\Commands;

use App\Models\AccountingPeriod;
use App\Models\FiscalYear;
use App\Models\Journal;
use Illuminate\Console\Command;

/**
 * Readiness check for fiscal-year close.
 *
 * Analyses the target fiscal year and reports blocking issues before
 * an operator invokes the actual closeFiscalYear() service method.
 *
 * Exit codes: 0 = ready, 1 = not ready, 2 = error.
 */
class FiscalYearCloseCheck extends Command
{
    protected $signature = 'accounting:close-check
                            {institute : Institute ID}
                            {--branch= : Branch ID (optional)}
                            {--year= : Fiscal year ID (omit for current year)}';

    protected $description = 'Pre-flight checks for fiscal-year close';

    public function handle(): int
    {
        $instituteId = (int) $this->argument('institute');
        $branchId = $this->option('branch') !== null ? (int) $this->option('branch') : null;

        $query = FiscalYear::query()
            ->where('institute_id', $instituteId)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId));

        if ($this->option('year')) {
            $year = $query->where('id', (int) $this->option('year'))->first();
        } else {
            $year = $query->where('is_current', true)->where('status', 'open')->first();
        }

        if (! $year) {
            $this->error('No matching open fiscal year found.');
            return 2;
        }

        $this->info("Fiscal Year: {$year->name} (ID: {$year->id})");
        $this->line("  Period: {$year->start_date} → {$year->end_date}");
        $this->line("  Status: {$year->status}");
        $this->newLine();

        $issues = [];

        // Check 1: Year must be open
        if ($year->status !== 'open') {
            $issues[] = "Fiscal year status is '{$year->status}', expected 'open'.";
        }

        // Check 2: Next fiscal year must exist
        $nextYear = FiscalYear::query()
            ->where('institute_id', $instituteId)
            ->where('branch_id', $year->branch_id)
            ->whereDate('start_date', '>', $year->end_date->toDateString())
            ->orderBy('start_date')
            ->first();

        if (! $nextYear) {
            $issues[] = 'No subsequent fiscal year exists. Create it before closing.';
        } else {
            $this->info("  Next Year: {$nextYear->name} (ID: {$nextYear->id})");
        }

        // Check 3: No draft journals in any period
        $draftCount = Journal::query()
            ->where('institute_id', $instituteId)
            ->where('branch_id', $year->branch_id)
            ->where('status', 'draft')
            ->whereBetween('journal_date', [$year->start_date, $year->end_date])
            ->count();

        if ($draftCount > 0) {
            $issues[] = "{$draftCount} draft journal(s) exist in this fiscal year. Post or void them first.";
        }

        // Check 4: Final period must be open (needed for closing journal)
        $finalPeriod = AccountingPeriod::query()
            ->where('institute_id', $instituteId)
            ->where('fiscal_year_id', $year->id)
            ->whereDate('start_date', '<=', $year->end_date->toDateString())
            ->whereDate('end_date', '>=', $year->end_date->toDateString())
            ->first();

        if ($finalPeriod && $finalPeriod->status !== 'open') {
            $issues[] = "The final period covering year-end ({$finalPeriod->name}) is '{$finalPeriod->status}', expected 'open'.";
        }

        // Check 5: No open draft-requiring items
        $periodsWithDrafts = AccountingPeriod::query()
            ->where('institute_id', $instituteId)
            ->where('fiscal_year_id', $year->id)
            ->where('status', 'open')
            ->whereHas('journals', fn ($q) => $q->where('status', 'draft'))
            ->count();

        if ($periodsWithDrafts > 0) {
            $issues[] = "{$periodsWithDrafts} open period(s) contain draft journals.";
        }

        // Summary
        $this->newLine();
        $this->line('─── Checks ───');

        $checks = [
            'Fiscal year is open' => $year->status === 'open',
            'Subsequent fiscal year exists' => $nextYear !== null,
            'No draft journals remain' => $draftCount === 0,
            'Final period is open' => ! $finalPeriod || $finalPeriod->status === 'open',
        ];

        foreach ($checks as $label => $passed) {
            $this->line($passed ? "  ✓ {$label}" : "  ✗ {$label}");
        }

        $this->newLine();

        if (count($issues) === 0) {
            $this->info('READY — This fiscal year can be closed.');
            return 0;
        }

        $this->error('NOT READY — ' . count($issues) . ' blocking issue(s):');
        foreach ($issues as $issue) {
            $this->line("  • {$issue}");
        }

        return 1;
    }
}

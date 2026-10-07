<?php

namespace App\Console\Commands;

use App\Models\Institute;
use App\Services\Accounting\TenantCoaSeederService;
use App\Support\BranchContext;
use App\Support\TenantContext;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * F-002 / N-8 — provision the full accounting stack for institutes onboarded
 * before seedFullProvisioning existed: they hold CoA children (maybe) but no
 * fiscal year / periods / payment methods / settings, and because
 * journals.fiscal_year_id is NOT NULL those tenants cannot post a journal.
 *
 * Selection: --institute={id} (one) or --all-partial (institutes missing any
 * part of the stack — see needsBackfill()). Both selectors include
 * soft-deleted tenants. --dry-run prints the plan and BEFORE counts only.
 * Each institute runs in its own try/catch — one failure never aborts the run.
 */
class CoaBackfill extends Command
{
    /**
     * Lowest industry-reachable CoA leaf count (healthcare / unfiltered = 72;
     * education tops out at 76, training_center and retail at 74).
     */
    private const COA_FLOOR = 72;

    /**
     * Tables that carry a soft-delete column (accounting_settings does not).
     */
    private const SOFT_DELETES = ['chart_of_accounts', 'fiscal_years', 'accounting_periods', 'payment_methods'];

    protected $signature = 'coa:backfill
        {--institute= : Target a single institute id}
        {--all-partial : Backfill institutes missing FY / periods / payment methods / settings / CoA floor}
        {--dry-run : Print plan, no writes}';

    protected $description = 'Provision full accounting stack for institutes missing CoA/FY/PM/settings';

    public function handle(): int
    {
        TenantContext::clear();
        BranchContext::clear();

        $dryRun = (bool) $this->option('dry-run');
        $this->info($dryRun ? 'DRY-RUN mode: plan only, no writes.' : 'EXECUTE mode: provisioning selected institutes.');

        $ids = $this->targets();

        if ($ids === null) {
            return self::FAILURE;
        }

        if ($ids === []) {
            $this->warn('Nothing to do: no institute matched the selection.');

            return self::SUCCESS;
        }

        $seeder = app(TenantCoaSeederService::class);
        $failed = 0;

        foreach ($ids as $id) {
            $before = $this->counts($id);
            $this->line("institute {$id} BEFORE: {$this->format($before)}");

            if ($dryRun) {
                $this->line('  [dry-run] skipped (no writes)');

                continue;
            }

            try {
                $seeder->seedFullProvisioning($id);
            } catch (\Throwable $e) {
                $failed++;
                $this->error("  FAILED: {$e->getMessage()}");

                continue;
            }

            $after = $this->counts($id);
            $this->line("institute {$id} AFTER:  {$this->format($after)}");
        }

        if ($dryRun) {
            $this->info('Dry-run complete. No rows written.');
        } else {
            $this->info(sprintf('Done: %d institute(s) processed, %d failed.', count($ids), $failed));
        }

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Resolve target institute ids. Returns null on a usage error.
     *
     * @return array<int, int>|null
     */
    private function targets(): ?array
    {
        $institute = $this->option('institute');

        if ($institute !== null && $institute !== '') {
            $id = (int) $institute;

            // Explicit id = operator intent: match even soft-deleted tenants
            // (--all-partial includes them too, so neither path silently skips).
            if (! Institute::withTrashed()->whereKey($id)->exists()) {
                $this->error("Institute {$id} not found.");

                return [];
            }

            return [$id];
        }

        if (! $this->option('all-partial')) {
            $this->error('Provide --institute={id} or --all-partial.');

            return null;
        }

        $counts = [
            'coa' => $this->groupedCounts('chart_of_accounts'),
            'fy' => $this->groupedCounts('fiscal_years'),
            'periods' => $this->groupedCounts('accounting_periods'),
            'pm' => $this->groupedCounts('payment_methods'),
            'settings' => $this->groupedCounts('accounting_settings'),
        ];

        return Institute::withTrashed()
            ->pluck('id')
            ->filter(fn ($id) => $this->needsBackfill((int) $id, $counts))
            ->sort()
            ->values()
            ->all();
    }

    /**
     * A tenant is partial until the whole stack exists. Deliberately not
     * "coa < template total": the industry filter caps reachable leaves at
     * 72–76, so a template-total predicate would match every institute
     * forever and force a full re-scan on every run.
     *
     * @param  array<string, array<int, int>>  $counts
     */
    private function needsBackfill(int $instituteId, array $counts): bool
    {
        return ($counts['fy'][$instituteId] ?? 0) === 0
            || ($counts['periods'][$instituteId] ?? 0) < 12
            || ($counts['pm'][$instituteId] ?? 0) < 4
            || ($counts['settings'][$instituteId] ?? 0) < 10
            || ($counts['coa'][$instituteId] ?? 0) < self::COA_FLOOR;
    }

    /**
     * @return array<int, int>
     */
    private function groupedCounts(string $table): array
    {
        $query = DB::table($table)
            ->whereNotNull('institute_id');

        if (in_array($table, self::SOFT_DELETES, true)) {
            $query->whereNull('deleted_at');
        }

        return $query
            ->groupBy('institute_id')
            ->get([DB::raw('COUNT(*) as c'), 'institute_id'])
            ->mapWithKeys(fn ($row) => [(int) $row->institute_id => (int) $row->c])
            ->all();
    }

    /**
     * @return array<string, int>
     */
    private function counts(int $instituteId): array
    {
        $count = function (string $table) use ($instituteId): int {
            $query = DB::table($table)->where('institute_id', $instituteId);

            if (in_array($table, self::SOFT_DELETES, true)) {
                $query->whereNull('deleted_at');
            }

            return $query->count();
        };

        return [
            'coa' => $count('chart_of_accounts'),
            'fy' => $count('fiscal_years'),
            'periods' => $count('accounting_periods'),
            'pm' => $count('payment_methods'),
            'settings' => $count('accounting_settings'),
        ];
    }

    /**
     * @param  array<string, int>  $counts
     */
    private function format(array $counts): string
    {
        return sprintf(
            'coa=%d fy=%d periods=%d pm=%d settings=%d',
            $counts['coa'],
            $counts['fy'],
            $counts['periods'],
            $counts['pm'],
            $counts['settings'],
        );
    }
}

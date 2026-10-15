<?php

namespace App\Console\Commands;

use App\Models\Institute;
use App\Services\Accounting\MedicalCoaInstaller;
use App\Support\BranchContext;
use App\Support\TenantContext;
use Illuminate\Console\Command;

/**
 * Medical COA remediation — install the healthcare-tagged template accounts
 * (4300.1 Consultation Fees, 4300.2 Diagnostic Fees, 4300.3 Pharmacy Sales,
 * 4000.5 Discount Allowed, 2400.3 Patient Advances, 1200.4 Insurance / TPA
 * Receivable, 2000.4 Doctor Commission Payable) into EXISTING healthcare
 * institutes that were onboarded before the seeder's industry filter was
 * fixed (it compared institutes.industry='healthcare' against a stale
 * 'medical' tag and silently skipped the whole 4300.x subtree).
 *
 * Selection: --institute={id} (one) or --all-healthcare (every active
 * healthcare institute). --dry-run is the DEFAULT: it prints the missing
 * accounts per institute and writes nothing. Pass --execute to apply.
 * Each institute runs in its own try/catch — one failure never aborts.
 */
class CoaInstallMedicalSubtree extends Command
{
    protected $signature = 'coa:install-medical-subtree
        {--institute= : Target a single institute id}
        {--all-healthcare : Target every active healthcare institute}
        {--execute : Actually write (omit for dry-run)}
        {--dry-run : Print the plan, no writes (default behaviour)}';

    protected $description = 'Install healthcare-tagged CoA accounts into existing medical institutes';

    public function handle(): int
    {
        TenantContext::clear();
        BranchContext::clear();

        // --execute wins over --dry-run; without either, dry-run is the default.
        $execute = (bool) $this->option('execute') && ! $this->option('dry-run');

        $this->info($execute
            ? 'EXECUTE mode: installing medical CoA accounts into selected institutes.'
            : 'DRY-RUN mode: plan only, no writes. Pass --execute to apply.');

        $ids = $this->targets();

        if ($ids === null) {
            return self::FAILURE;
        }

        if ($ids === []) {
            $this->warn('Nothing to do: no institute matched the selection.');

            return self::SUCCESS;
        }

        // The healthcare-tagged template codes this command is responsible for.
        $installer = app(MedicalCoaInstaller::class);
        $medicalCodes = $installer->medicalCodes();

        $this->line('Healthcare-tagged template accounts: '.implode(', ', $medicalCodes));
        $this->newLine();

        $failed = 0;

        foreach ($ids as $id) {
            $missing = $installer->missingCodes((int) $id, $medicalCodes);

            if ($missing === []) {
                $this->line("institute {$id}: already complete (all ".count($medicalCodes).' medical accounts present).');

                continue;
            }

            $this->line("institute {$id}: missing ".count($missing).' → '.implode(', ', $missing));

            if (! $execute) {
                $this->line('  [dry-run] skipped (no writes)');

                continue;
            }

            try {
                $installer->install((int) $id, $missing);
                $this->line('  installed '.count($missing).' account(s).');
            } catch (\Throwable $e) {
                $failed++;
                $this->error("  FAILED: {$e->getMessage()}");
            }
        }

        if (! $execute) {
            $this->newLine();
            $this->info('Dry-run complete. No rows written. Re-run with --execute to apply.');
        } else {
            $this->newLine();
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

            if (! Institute::withTrashed()->whereKey($id)->exists()) {
                $this->error("Institute {$id} not found.");

                return [];
            }

            return [$id];
        }

        if (! $this->option('all-healthcare')) {
            $this->error('Provide --institute={id} or --all-healthcare.');

            return null;
        }

        return Institute::query()
            ->whereNull('deleted_at')
            ->where('industry', 'healthcare')
            ->orderBy('id')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }
}

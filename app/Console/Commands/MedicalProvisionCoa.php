<?php

namespace App\Console\Commands;

use App\Models\Institute;
use App\Services\Accounting\MedicalCoaInstaller;
use App\Support\BranchContext;
use App\Support\TenantContext;
use Illuminate\Console\Command;

/**
 * Admin-facing on-demand CoA provisioning for medical billing.
 *
 * Non-healthcare tenants (and healthcare institutes that skipped onboarding)
 * lack the tenant-owned accounts the medical billing journal pipeline
 * resolves, so postSaleJournal() fails soft: the invoice is created, no
 * journal is written, and only a warning is logged. This command is the
 * documented recovery — the same install core as coa:install-medical-subtree
 * (shared MedicalCoaInstaller, zero duplicated logic) but with the
 * billing-core accounts (1000.1 Cash, 1200.1 Trade Receivable, 2100.1 VAT
 * Output) included, because a subtree-only install still fails the AR lookup.
 *
 * Dry-run is the default; --execute writes. Per-institute try/catch — one
 * failure never aborts the rest. Exit 0 when everything is OK, 1 otherwise.
 */
class MedicalProvisionCoa extends Command
{
    protected $signature = 'medical:provision-coa
        {--institute= : Single institute ID}
        {--all-healthcare : All healthcare-industry institutes}
        {--dry-run : Preview without writing (default)}
        {--execute : Actually install}';

    protected $description = 'Provision the Chart of Accounts accounts required by medical billing journals';

    public function handle(): int
    {
        TenantContext::clear();
        BranchContext::clear();

        $execute = (bool) $this->option('execute') && ! $this->option('dry-run');

        $targets = $this->targets();
        if ($targets === null) {
            return self::FAILURE;
        }

        if ($targets === []) {
            $this->warn('Nothing to do: no institute matched the selection.');

            return self::SUCCESS;
        }

        $installer = app(MedicalCoaInstaller::class);
        $codes = $installer->codesForBilling();

        $this->line(($execute ? 'EXECUTE' : 'DRY-RUN')
            .' mode: '.count($codes).' billing accounts in scope (healthcare subtree + 1000.1, 1200.1, 2100.1).');
        $this->newLine();

        $failed = 0;

        foreach ($targets as $id) {
            $missing = $installer->missingCodes((int) $id, $codes);

            if ($missing === []) {
                $this->line("⊘ institute {$id}: no changes needed");

                continue;
            }

            if (! $execute) {
                $this->line("◌ institute {$id}: [dry-run] would install ".count($missing).' ('.implode(', ', $missing).')');

                continue;
            }

            try {
                $installer->install((int) $id, $missing);
                $this->info("✓ institute {$id}: ".count($missing).' account(s) installed ('.implode(', ', $missing).')');
            } catch (\Throwable $e) {
                $failed++;
                $this->error("✗ institute {$id}: failed — {$e->getMessage()}");
            }
        }

        $this->newLine();
        if (! $execute) {
            $this->info('Dry-run complete. No rows written. Re-run with --execute to apply.');
        } else {
            $this->info(sprintf('Done: %d institute(s) processed, %d failed.', count($targets), $failed));
        }

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Resolve target institute ids.
     *
     * --institute must exist AND be healthcare (a wrong target is an error,
     * never a silent no-op). --all-healthcare filters by industry itself.
     * Returns null on a usage error.
     *
     * @return array<int, int>|null
     */
    private function targets(): ?array
    {
        $institute = $this->option('institute');

        if ($institute !== null && $institute !== '') {
            $id = (int) $institute;

            $row = Institute::withTrashed()->whereKey($id)->first();

            if ($row === null) {
                $this->error("Institute {$id} not found.");

                return null;
            }

            if (($row->industry ?? '') !== 'healthcare') {
                $this->error("✗ institute {$id}: skipped — industry is '".($row->industry ?? 'null')."', not healthcare.");

                return null;
            }

            return [$id];
        }

        if (! $this->option('all-healthcare')) {
            $this->error('Specify target: pass --institute={id} or --all-healthcare.');

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

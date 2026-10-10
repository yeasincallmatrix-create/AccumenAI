<?php

namespace App\Console\Commands;

use App\Models\Institute;
use App\Services\Accounting\CoaReanchorService;
use App\Services\Accounting\CoaTemplate;
use App\Support\BranchContext;
use App\Support\TenantContext;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

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
        $medicalCodes = $this->medicalCodes();

        $this->line('Healthcare-tagged template accounts: '.implode(', ', $medicalCodes));
        $this->newLine();

        $reanchor = app(CoaReanchorService::class);
        $failed = 0;

        foreach ($ids as $id) {
            $missing = $this->missingCodes((int) $id, $medicalCodes);

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
                $this->install((int) $id, $missing, $reanchor);
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
     * Template codes tagged for the healthcare industry (the medical subtree).
     *
     * @return list<string>
     */
    private function medicalCodes(): array
    {
        $codes = [];

        foreach (CoaTemplate::all() as $row) {
            $industries = $row[6] ?? null;
            if (! empty($industries) && in_array('healthcare', $industries, true)) {
                $codes[] = (string) $row[0];
            }
        }

        sort($codes);

        return $codes;
    }

    /**
     * Which of the given codes this institute does NOT yet own.
     *
     * @param  list<string>  $codes
     * @return list<string>
     */
    private function missingCodes(int $instituteId, array $codes): array
    {
        $owned = DB::table('chart_of_accounts')
            ->where('institute_id', $instituteId)
            ->whereNull('deleted_at')
            ->whereIn('code', $codes)
            ->pluck('code')
            ->all();

        return array_values(array_diff($codes, $owned));
    }

    /**
     * Install the missing codes: ensure the tenant anchor exists, then create
     * the leaf under it with the canonical name / type / flags. Mirrors
     * TenantCoaSeederService::seedForTenant's per-row write-set.
     *
     * @param  list<string>  $codes
     */
    private function install(int $instituteId, array $codes, CoaReanchorService $reanchor): void
    {
        DB::transaction(function () use ($instituteId, $codes, $reanchor): void {
            foreach ($codes as $code) {
                $row = CoaTemplate::findByCode($code);

                if ($row === null || $row[3]) {
                    continue; // unknown code or a header — not installable here
                }

                $parentCode = (string) $row[2];

                // The parent anchor must be a global header; clone it under
                // the tenant on demand (same path the onboarding seeder uses).
                $parent = DB::table('chart_of_accounts')
                    ->whereNull('institute_id')
                    ->where('code', $parentCode)
                    ->where('is_header', true)
                    ->first();

                if ($parent === null) {
                    throw new \RuntimeException(
                        "No global header '{$parentCode}' for code '{$code}'; cannot install."
                    );
                }

                $anchorId = $reanchor->ensureAnchor($instituteId, $parentCode);

                DB::table('chart_of_accounts')->insert([
                    'institute_id' => $instituteId,
                    'branch_id' => null,
                    'account_group_id' => $parent->account_group_id,
                    'parent_id' => $anchorId,
                    'code' => $code,
                    'name' => $row[1],
                    'type' => $row[5],
                    'cash_flow_category' => $row[7]['cash_flow_category'] ?? null,
                    'is_cash' => (int) ($row[7]['is_cash'] ?? false),
                    'is_bank' => (int) ($row[7]['is_bank'] ?? false),
                    'is_receivable' => (int) ($row[7]['is_receivable'] ?? false),
                    'is_payable' => (int) ($row[7]['is_payable'] ?? false),
                    'is_active' => 1,
                    'is_postable' => 1,
                    'is_header' => 0,
                    'is_system' => 0,
                    'industries' => $row[6] ? json_encode($row[6]) : null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });
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

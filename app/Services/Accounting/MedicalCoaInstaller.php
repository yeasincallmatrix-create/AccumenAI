<?php

namespace App\Services\Accounting;

use Illuminate\Support\Facades\DB;

/**
 * Shared medical CoA provisioning core, used by both artisan commands:
 *
 *   - coa:install-medical-subtree — healthcare-subtree remediation for
 *     institutes onboarded before the industry filter was fixed;
 *   - medical:provision-coa      — admin-facing on-demand provisioning
 *     (includes the billing-core accounts, see billingCoreCodes()).
 *
 * Extracted from CoaInstallMedicalSubtree so the two commands cannot drift:
 * missing-code detection and leaf installation exist exactly once.
 * Behaviour of install()/missingCodes()/medicalCodes() is byte-identical to
 * the private methods it replaces.
 */
class MedicalCoaInstaller
{
    public function __construct(
        private readonly CoaReanchorService $reanchor,
    ) {}

    /**
     * Template codes tagged for the healthcare industry (the medical subtree,
     * including the 4300 header row).
     *
     * @return list<string>
     */
    public function medicalCodes(): array
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
     * Accounts the medical billing journal pipeline resolves by tenant code.
     *
     * accountByCode() is strictly tenant-scoped (institute_id = X, no global
     * NULL-row fallback), and these three live in the template's GLOBAL_ROWS —
     * so a healthcare institute that never went onboarding has no tenant row
     * for them. Installing only the healthcare subtree would still leave
     * postSaleJournal() failing soft on the Trade Receivable lookup.
     *
     * @return list<string>
     */
    public function billingCoreCodes(): array
    {
        return ['1000.1', '1200.1', '2100.1'];
    }

    /**
     * Everything medical:provision-coa installs: the healthcare subtree plus
     * the billing-core accounts, unique + sorted.
     *
     * @return list<string>
     */
    public function codesForBilling(): array
    {
        $codes = array_values(array_unique(array_merge(
            $this->medicalCodes(),
            $this->billingCoreCodes(),
        )));

        sort($codes);

        return $codes;
    }

    /**
     * Which of the given codes this institute does NOT yet own.
     * Fresh query every call — no caching.
     *
     * @param  list<string>  $codes
     * @return list<string>
     */
    public function missingCodes(int $instituteId, array $codes): array
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
     * TenantCoaSeederService::seedForTenant's per-row write-set. All-or-nothing
     * per call (single DB transaction).
     *
     * @param  list<string>  $codes
     */
    public function install(int $instituteId, array $codes): void
    {
        DB::transaction(function () use ($instituteId, $codes): void {
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

                $anchorId = $this->reanchor->ensureAnchor($instituteId, $parentCode);

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
}

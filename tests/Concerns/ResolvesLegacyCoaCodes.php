<?php

namespace Tests\Concerns;

use App\Models\AccountGroup;
use App\Models\ChartOfAccount;
use App\Services\Accounting\CoaTemplate;

/**
 * F-024 / TASK E2 — legacy CoA code fixtures.
 *
 * C1's reanchor + the 2026_09_22 purge invalidated the legacy codes
 * (4001, 5006, 3002, 2001, 2003, 1000, 1100, 1200, 1500, 5001) that test
 * helpers still reference. This trait maps each legacy code to its
 * canonical leaf and extends fixture setup to seed missing codes.
 */
trait ResolvesLegacyCoaCodes
{
    /** Legacy pre-reanchor codes → canonical leaf codes. */
    protected const LEGACY_COA_MAP = [
        '4001' => '4100.1',   // Income
        '5006' => '5900.1',   // Misc expense (name-match)
        '5001' => '5000.5',   // COGS (semantic)
        '3002' => '3400.1',   // Equity / Retained Earnings
        '3001' => '3100.1',   // Owner's Capital (BusinessEntityService legacy)
        '2001' => '2000.1',   // AP
        '2003' => '2000.3',   // Liability
        '1000' => '1000.1',   // Cash in Hand
        '1100' => '1100.1',   // Bank
        '1200' => '1200.1',   // Accounts Receivable
        '1500' => '1500.1',   // Asset
    ];

    protected function resolveCoaCode(string $code): string
    {
        return self::LEGACY_COA_MAP[$code] ?? $code;
    }

    /**
     * Seed every mapped canonical code for the fixture institute.
     * Idempotent — installGroupsAndAccounts already creates most of them;
     * only codes absent from TEMPLATE_ORDER (e.g. 2000.3) are new.
     */
    protected function ensureLegacyCoaFixture(int $instituteId): void
    {
        foreach (self::LEGACY_COA_MAP as $canonical) {
            $this->ensureFixtureAccount($instituteId, $canonical);
        }
    }

    protected function ensureFixtureAccount(int $instituteId, string $canonicalCode): void
    {
        $template = CoaTemplate::findByCode($canonicalCode);
        if (! $template) {
            throw new \RuntimeException("Canonical code {$canonicalCode} missing from CoaTemplate.");
        }

        $parentId = null;
        if ($template[2]) {
            $parentId = ChartOfAccount::withoutGlobalScopes()
                ->where('institute_id', $instituteId)
                ->where('code', $template[2])
                ->value('id');
        }

        ChartOfAccount::withoutGlobalScopes()->firstOrCreate(
            ['institute_id' => $instituteId, 'code' => $canonicalCode],
            [
                'name' => $template[1],
                'type' => $template[5],
                'parent_id' => $parentId,
                'is_header' => false,
                'is_postable' => true,
                'is_active' => true,
                'account_group_id' => $this->resolveGroupId($instituteId, $template[5]),
            ]
        );
    }

    protected function resolveGroupId(int $instituteId, string $type): int
    {
        return (int) AccountGroup::withoutGlobalScopes()
            ->where('institute_id', $instituteId)
            ->where('category', $type)
            ->value('id');
    }
}

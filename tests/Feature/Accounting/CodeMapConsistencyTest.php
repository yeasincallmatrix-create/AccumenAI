<?php

namespace Tests\Feature\Accounting;

use App\Services\Accounting\CoaTemplate;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Pins the single canonical code map introduced by N-3 consolidation.
 *
 * These assertions intentionally re-state the legacy constants' shapes so any
 * future edit that changes what onboarding / the seeder / template install
 * writes has to come through CoaTemplate deliberately.
 */
class CodeMapConsistencyTest extends TestCase
{
    use DatabaseTransactions;

    public function test_children_view_keeps_legacy_parent_shape(): void
    {
        $children = CoaTemplate::childrenByParent();

        $this->assertSame(
            ['1000', '1100', '1200', '1300', '1400', '1500', '1600', '2000', '2100', '2200',
                '2300', '2400', '3100', '3300', '3400', '4000', '4100', '4200', '4300', '4400',
                '4900', '5000', '5100', '6000', '5300', '5400', '5500', '5900'],
            array_map('strval', array_keys($children)),
            'The CHILDREN parent key order must stay byte-stable for tenant seeding.',
        );

        $flat = [];
        foreach ($children as $kids) {
            foreach ($kids as $kid) {
                $flat[$kid[0]] = $kid;
            }
        }

        $this->assertCount(83, $flat, '81 legacy children plus the two FX accounts.');
        $this->assertArrayHasKey('1100.1', $flat);
        $this->assertSame(['is_bank' => true], $flat['1100.1'][2], 'Only the legacy write-set extra.');
        $this->assertArrayNotHasKey('cash_flow_category', $flat['1200.1'][2] ?? [],
            'childrenByParent() must not gain rich TEMPLATE flags.');

        foreach ($children as $parentCode => $kids) {
            $this->assertMatchesRegularExpression('/^\d{1,4}$/', (string) $parentCode);
            foreach ($kids as $kid) {
                $this->assertNotSame((string) $parentCode, $kid[0]);
            }
        }
    }

    public function test_flat_typed_view_matches_legacy_template_order(): void
    {
        $rows = CoaTemplate::flatTyped();

        $this->assertCount(33, $rows);
        $this->assertSame('1000.1', $rows[0][0], 'First legacy TEMPLATE row.');
        $this->assertSame('5901', $rows[32][0], 'Last legacy TEMPLATE row.');
        $this->assertSame('Cash in Hand', $rows[0][1]);

        $codes = array_column($rows, 0);
        $this->assertSame($codes, array_values(array_unique($codes)), 'No duplicate codes.');

        foreach ($rows as $row) {
            $this->assertCount(4, $row);
            $this->assertContains($row[2], ['asset', 'liability', 'equity', 'income', 'expense']);
        }

        $byCode = array_column($rows, null, 0);
        $this->assertSame(
            ['is_receivable' => true, 'cash_flow_category' => 'operating'],
            $byCode['1200.1'][3],
            'Rich flags are a TEMPLATE-view responsibility.',
        );
        $this->assertTrue($byCode['1000.1'][3]['is_cash']);
    }

    public function test_global_view_matches_legacy_seeder_shape(): void
    {
        $rows = CoaTemplate::globalRows();

        $this->assertCount(114, $rows, 'Legacy GlobalChartOfAccountsSeeder row count.');
        $this->assertSame('1', $rows[0][0]);
        $this->assertSame('Assets', $rows[0][1]);
        $this->assertNull($rows[0][2]);
        $this->assertCount(7, $rows[0], 'Row tuple is [code, name, parent_code, is_header, is_postable, type, industries].');

        $headers = array_filter($rows, fn ($r) => $r[3] === true);
        $leaves = array_filter($rows, fn ($r) => $r[3] === false);

        $this->assertCount(34, $headers);
        $this->assertCount(80, $leaves);

        foreach ($headers as $row) {
            $this->assertFalse($row[4], 'Headers are never postable.');
        }
        foreach ($leaves as $row) {
            $this->assertTrue($row[4], 'Leaves are always postable.');
            $this->assertNotNull($row[2], 'Every leaf is parented in the global tree.');
        }

        $codes = array_column($rows, 0);
        $this->assertSame($codes, array_values(array_unique($codes)));

        $byCode = array_column($rows, null, 0);
        $this->assertSame(
            'Service Revenue',
            $byCode['4000.2'][1],
            'The global tree keeps the legacy label - is_system rows cannot be renamed by the seeder.',
        );
        $this->assertSame(
            'Consultation Fees',
            $byCode['4000.3'][1],
            'The global tree keeps the legacy label - is_system rows cannot be renamed by the seeder.',
        );
    }

    public function test_registry_is_internally_consistent_across_views(): void
    {
        $all = CoaTemplate::all();
        $codes = array_column($all, 0);

        $this->assertSame($codes, array_values(array_unique($codes)), 'One row per code.');
        $this->assertCount(117, $all);

        $headers = [];
        foreach ($all as $row) {
            if ($row[3]) {
                $headers[$row[0]] = true;
            }
        }
        $this->assertNotEmpty($headers);

        foreach ($all as $row) {
            $parent = $row[2];
            if ($parent === null) {
                $this->assertTrue($row[3], "Non-header '{$row[0]}' must have a parent.");

                continue;
            }
            $this->assertArrayHasKey($parent, $headers, "Parent of '{$row[0]}' must be a header.");
        }

        // flatTyped() and globalRows() must both be subsets of the registry.
        foreach (CoaTemplate::flatTyped() as $row) {
            $this->assertContains($row[0], $codes, 'TEMPLATE code missing from registry.');
        }
        foreach (CoaTemplate::globalRows() as $row) {
            $this->assertContains($row[0], $codes, 'Seeder code missing from registry.');
        }
        foreach (CoaTemplate::childrenByParent() as $kids) {
            foreach ($kids as $kid) {
                $this->assertContains($kid[0], $codes, 'CHILDREN code missing from registry.');
            }
        }

        foreach ($all as $row) {
            $this->assertContains($row[5], ['asset', 'liability', 'equity', 'income', 'expense']);
            $this->assertIsBool($row[3]);
            $this->assertIsBool($row[4]);
        }
    }

    public function test_fx_accounts_and_canonical_labels_resolve_in_every_view(): void
    {
        foreach (['4901' => 'Unrealized FX Gain', '5901' => 'Unrealized FX Loss'] as $code => $label) {
            $code = (string) $code;
            $this->assertTrue(CoaTemplate::has($code), "{$code} must exist - DEFAULT_SETTINGS points at it.");

            $childLabel = null;
            foreach (CoaTemplate::childrenByParent() as $kids) {
                foreach ($kids as $kid) {
                    if ($kid[0] === $code) {
                        $childLabel = $kid[1];
                    }
                }
            }
            $this->assertSame($label, $childLabel, "{$code} must be seeded to onboarding tenants.");

            $typed = array_column(CoaTemplate::flatTyped(), null, 0);
            $this->assertSame($label, $typed[$code][1]);

            $this->assertNotContains($code, array_column(CoaTemplate::globalRows(), 0),
                "{$code} is not part of the global anchor tree.");
        }

        $children = CoaTemplate::childrenByParent();
        $byCode = [];
        foreach ($children as $kids) {
            foreach ($kids as $kid) {
                $byCode[$kid[0]] = $kid[1];
            }
        }

        $this->assertSame('Other Income', $byCode['4000.2'], 'TEMPLATE label wins over Service Revenue.');
        $this->assertSame('Inventory Adjustment Income', $byCode['4000.3'], 'TEMPLATE label wins over Consultation Fees.');
        $this->assertSame('Other Sales Income', $byCode['4400.2'], 'CHILDREN-only code must stay available.');
    }

    public function test_validate_code_rejects_unknown_codes(): void
    {
        foreach (['4901', '5901', '1000.1', '4400.2', '4000.2'] as $code) {
            CoaTemplate::validateCode($code);
            $this->assertTrue(CoaTemplate::has($code));
        }

        $this->assertFalse(CoaTemplate::has('9999'));
        $this->assertFalse(CoaTemplate::has(''));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/missing from the canonical CoaTemplate registry/');
        CoaTemplate::validateCode('9999');
    }
}

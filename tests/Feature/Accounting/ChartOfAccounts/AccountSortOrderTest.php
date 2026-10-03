<?php

namespace Tests\Feature\Accounting\ChartOfAccounts;

use App\Livewire\ChartOfAccountList;
use App\Models\AccountGroup;
use App\Models\ChartOfAccount;
use App\Models\Institute;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

class AccountSortOrderTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * Deliberately inserted worst-first so a pass proves sorting, not
     * insertion order. Codes cover every shape the validator accepts:
     * bare anchor, single segment, and the 1000.2 vs 1000.10 trap.
     */
    private const SEEDED = [
        ['5000', 'expense'],
        ['1000.11', 'asset'],
        ['3000', 'equity'],
        ['1000.10', 'asset'],
        ['2000', 'liability'],
        ['1100', 'asset'],
        ['4000', 'income'],
        ['1000.2', 'asset'],
        ['1000', 'asset'],
        ['1100.1', 'asset'],
        ['1000.1', 'asset'],
    ];

    private const CANONICAL = [
        '1000',
        '1000.1',
        '1000.2',
        '1000.10',
        '1000.11',
        '1100',
        '1100.1',
        '2000',
        '3000',
        '4000',
        '5000',
    ];

    private Institute $institute;

    protected function setUp(): void
    {
        parent::setUp();

        $this->institute = Institute::where('name', 'MAWA ACADEMY')->firstOrFail();
        TenantContext::set($this->institute->id);

        foreach (self::SEEDED as [$code, $type]) {
            $this->createAccount($code, $type);
        }
    }

    protected function tearDown(): void
    {
        TenantContext::clear();

        parent::tearDown();
    }

    public function test_type_then_natural_code_order_is_exact(): void
    {
        $this->assertSame(self::CANONICAL, $this->tenantCodes());
    }

    public function test_anchor_precedes_its_children_naturally(): void
    {
        $codes = $this->tenantCodes();

        $anchor = array_search('1000', $codes, true);
        $first = array_search('1000.1', $codes, true);
        $second = array_search('1000.2', $codes, true);
        $tenth = array_search('1000.10', $codes, true);
        $eleventh = array_search('1000.11', $codes, true);

        $this->assertNotFalse($anchor);
        $this->assertNotFalse($first);
        $this->assertNotFalse($second);
        $this->assertNotFalse($tenth);
        $this->assertNotFalse($eleventh);

        $this->assertLessThan($first, $anchor);
        $this->assertLessThan($second, $first);
        $this->assertLessThan($tenth, $second);
        $this->assertLessThan($eleventh, $tenth);
    }

    public function test_visible_set_is_grouped_by_type_in_canonical_order(): void
    {
        $rows = ChartOfAccount::ordered()->get(['id', 'code', 'type']);

        $rank = ChartOfAccount::TYPE_ORDER;
        $previous = 0;
        foreach ($rows as $row) {
            $this->assertArrayHasKey($row->type, $rank);
            $this->assertGreaterThanOrEqual($previous, $rank[$row->type]);
            $previous = $rank[$row->type];
        }

        $this->assertSame(
            ['asset', 'liability', 'equity', 'income', 'expense'],
            array_values(array_unique($rows->pluck('type')->all())),
        );
    }

    public function test_search_and_type_filters_preserve_order(): void
    {
        $expected = array_values(array_filter(self::CANONICAL, fn ($code) => str_contains($code, '1')));

        $searched = $this->tenantQuery()
            ->ordered()
            ->where('name', 'like', '%Sort 1%')
            ->pluck('code')
            ->all();
        $this->assertSame($expected, $searched);

        $assets = $this->tenantQuery()
            ->where('type', 'asset')
            ->ordered()
            ->pluck('code')
            ->all();
        $this->assertSame(
            ['1000', '1000.1', '1000.2', '1000.10', '1000.11', '1100', '1100.1'],
            $assets,
        );
    }

    public function test_soft_deleted_accounts_are_excluded(): void
    {
        $deleted = $this->tenantQuery()->where('code', '1000.2')->firstOrFail();
        $deleted->delete();

        $this->assertSoftDeleted('chart_of_accounts', ['id' => $deleted->id]);
        $this->assertSame(
            ['1000', '1000.1', '1000.10', '1000.11', '1100', '1100.1', '2000', '3000', '4000', '5000'],
            $this->tenantCodes(),
        );
    }

    public function test_livewire_list_renders_canonical_order(): void
    {
        $page = Livewire::test(ChartOfAccountList::class)->viewData('accounts');
        $codes = $page->pluck('code')->all();

        $this->assertSame(['asset'], $page->pluck('type')->unique()->all());

        $anchor = array_search('1000', $codes, true);
        $second = array_search('1000.2', $codes, true);
        $tenth = array_search('1000.10', $codes, true);

        $this->assertNotFalse($anchor);
        $this->assertNotFalse($second);
        $this->assertNotFalse($tenth);
        $this->assertLessThan($second, $anchor);
        $this->assertLessThan($tenth, $second);
    }

    private function tenantQuery()
    {
        return ChartOfAccount::withoutGlobalScope('institute')
            ->where('institute_id', $this->institute->id);
    }

    private function tenantCodes(): array
    {
        return $this->tenantQuery()->ordered()->pluck('code')->all();
    }

    private function createAccount(string $code, string $type): ChartOfAccount
    {
        return ChartOfAccount::create([
            'institute_id' => $this->institute->id,
            'branch_id' => null,
            'account_group_id' => $this->groupId($type),
            'code' => $code,
            'name' => 'Sort '.$code,
            'type' => $type,
            'is_system' => 0,
            'is_active' => 1,
        ]);
    }

    private function groupId(string $type): int
    {
        return (int) (AccountGroup::withoutGlobalScope('institute')
                ->where('institute_id', $this->institute->id)
                ->where('category', $type)
                ->value('id')
            ?? AccountGroup::withoutGlobalScope('institute')
                ->whereNull('institute_id')
                ->where('is_system', 1)
                ->where('category', $type)
                ->value('id'));
    }
}

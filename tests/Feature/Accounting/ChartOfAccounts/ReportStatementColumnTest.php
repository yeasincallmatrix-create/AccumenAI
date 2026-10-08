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

class ReportStatementColumnTest extends TestCase
{
    use DatabaseTransactions;

    private Institute $institute;

    protected function setUp(): void
    {
        parent::setUp();

        $this->institute = Institute::where('name', 'MAWA ACADEMY')->firstOrFail();
        TenantContext::set($this->institute->id);

        $this->createAccount('88901', 'asset', 'Stmt Probe Asset');
        $this->createAccount('88902', 'income', 'Stmt Probe Income');
    }

    protected function tearDown(): void
    {
        TenantContext::clear();

        parent::tearDown();
    }

    public function test_every_type_maps_to_its_financial_statement(): void
    {
        $expected = [
            'asset' => 'Balance Sheet',
            'liability' => 'Balance Sheet',
            'equity' => 'Balance Sheet',
            'income' => 'Profit & Loss',
            'expense' => 'Profit & Loss',
        ];

        $this->assertSame($expected, ChartOfAccount::STATEMENT_BY_TYPE);

        foreach ($expected as $type => $statement) {
            $this->assertSame(
                $statement,
                (new ChartOfAccount(['type' => $type]))->reportStatement(),
                "type {$type}",
            );
        }
    }

    public function test_list_renders_statement_column_for_both_statements(): void
    {
        Livewire::test(ChartOfAccountList::class)
            ->set('search', 'Stmt Probe')
            ->assertSee('Statement')
            ->assertSee('Balance Sheet')
            ->assertSee('Profit & Loss');
    }

    private function createAccount(string $code, string $type, string $name): ChartOfAccount
    {
        return ChartOfAccount::create([
            'institute_id' => $this->institute->id,
            'branch_id' => null,
            'account_group_id' => $this->groupId($type),
            'code' => $code,
            'name' => $name,
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

<?php

namespace Tests\Feature\Accounting;

use App\Models\ChartOfAccount;
use App\Models\Institute;
use App\Services\Accounting\ChartOfAccountService;
use App\Support\BranchContext;
use App\Support\TenantContext;
use Database\Seeders\GlobalAccountGroupsSeeder;
use Database\Seeders\GlobalChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class GlobalCoaSeederIntegrityTest extends TestCase
{
    use DatabaseTransactions;

    protected function tearDown(): void
    {
        TenantContext::clear();
        BranchContext::clear();
        parent::tearDown();
    }

    private function runSeeder(object $seeder): string
    {
        $stub = new class {
            public array $messages = [];

            public function info($message): void
            {
                $this->messages[] = (string) $message;
            }
        };

        $command = (new \ReflectionObject($seeder))->getProperty('command');
        $command->setAccessible(true);
        $command->setValue($seeder, $stub);

        $seeder->run();

        return implode("\n", $stub->messages);
    }

    private function seedGroups(): string
    {
        return $this->runSeeder(new GlobalAccountGroupsSeeder());
    }

    private function seedAccounts(): string
    {
        return $this->runSeeder(new GlobalChartOfAccountsSeeder());
    }

    private function seededAccountCount(string $messages): int
    {
        $this->assertMatchesRegularExpression('/Global COA seeded:\s*\d+/', $messages);
        preg_match('/Global COA seeded:\s*(\d+)/', $messages, $matches);

        return (int) $matches[1];
    }

    public function test_seeders_persist_is_system_on_new_rows(): void
    {
        $accountsBefore = DB::table('chart_of_accounts')->where('is_system', 1)->count();
        $groupsBefore = DB::table('account_groups')->where('is_system', 1)->count();

        $groupMessages = $this->seedGroups();
        $this->seedAccounts();

        $this->assertStringContainsString('Global Groups: 0 created, 5 existed', $groupMessages);
        $this->assertSame($accountsBefore, DB::table('chart_of_accounts')->where('is_system', 1)->count());
        $this->assertSame($groupsBefore, DB::table('account_groups')->where('is_system', 1)->count());

        DB::table('account_groups')->whereNull('institute_id')->where('code', '1')->update(['code' => '1-old']);

        $groupMessages = $this->seedGroups();

        $this->assertStringContainsString('Global Groups: 1 created, 4 existed', $groupMessages);
        $freshGroup = DB::table('account_groups')->whereNull('institute_id')->where('code', '1')->orderByDesc('id')->first();
        $this->assertNotNull($freshGroup, 'The seeder must recreate a removed global group row.');
        $this->assertSame(1, (int) $freshGroup->is_system, 'A freshly created global group must keep is_system = 1.');
        $this->assertSame(1, (int) DB::table('account_groups')->whereNull('institute_id')->where('code', '1-old')->value('is_system'));

        DB::table('chart_of_accounts')->whereNull('institute_id')->where('code', '1000.1')->delete();

        $this->seedAccounts();

        $recreated = DB::table('chart_of_accounts')->whereNull('institute_id')->where('code', '1000.1')->first();
        $this->assertNotNull($recreated, 'The deleted global row must be recreated by the seeder.');
        $this->assertSame(1, (int) $recreated->is_system, 'The recreated global row must keep is_system = 1.');
        $this->assertSame($accountsBefore, DB::table('chart_of_accounts')->where('is_system', 1)->count());
    }

    public function test_fresh_install_reproduces_global_template(): void
    {
        DB::table('chart_of_accounts')->whereNull('institute_id')->delete();

        $this->seedGroups();
        $expected = $this->seededAccountCount($this->seedAccounts());

        $this->assertGreaterThan(100, $expected, 'The seeded global template must exceed 100 rows.');
        $this->assertSame($expected, DB::table('chart_of_accounts')->whereNull('institute_id')->count());
        $this->assertSame($expected, DB::table('chart_of_accounts')->whereNull('institute_id')->where('is_system', 1)->count());
        $this->assertSame(0, DB::table('chart_of_accounts')->whereNull('institute_id')->where('is_system', 0)->count());
        $this->assertSame(5, DB::table('account_groups')->whereNull('institute_id')->where('is_system', 1)->count());
        $this->assertSame(0, DB::table('account_groups')->whereNull('institute_id')->where('is_system', 0)->count());
    }

    public function test_tenant_scoped_scope_sees_seeded_globals(): void
    {
        DB::table('chart_of_accounts')->whereNull('institute_id')->delete();

        $expected = $this->seededAccountCount($this->seedAccounts());

        $institute = Institute::query()
            ->whereNotExists(function ($query) {
                $query->selectRaw('1')
                    ->from('chart_of_accounts')
                    ->whereColumn('chart_of_accounts.institute_id', 'institutes.id');
            })
            ->orderBy('id')
            ->firstOrFail();

        $this->assertSame(0, DB::table('chart_of_accounts')->where('institute_id', $institute->id)->count());

        TenantContext::set($institute->id);

        $this->assertSame($expected, ChartOfAccount::query()->count());
    }

    public function test_global_chart_of_accounts_seeder_heals_drifted_is_system(): void
    {
        $code = '1000.1';

        DB::table('chart_of_accounts')->whereNull('institute_id')->where('code', $code)->update(['is_system' => 0]);

        $this->assertSame(0, (int) DB::table('chart_of_accounts')->whereNull('institute_id')->where('code', $code)->value('is_system'));

        $this->seedAccounts();

        $this->assertSame(1, (int) DB::table('chart_of_accounts')->whereNull('institute_id')->where('code', $code)->value('is_system'));
        $this->assertSame(0, DB::table('chart_of_accounts')->whereNull('institute_id')->where('is_system', 0)->count());
    }

    public function test_tenant_onboarding_persists_is_system_and_created_by(): void
    {
        $institute = Institute::create([
            'name' => 'Seeder Integrity '.uniqid(),
            'slug' => 'seeder-integrity-'.uniqid(),
            'country' => 'Bangladesh',
            'status' => 'active',
        ]);

        $createdBy = (int) DB::table('users')->orderBy('id')->value('id') ?: 7;

        (new ChartOfAccountService())->installGroupsAndAccounts($institute->id, null, $createdBy);

        $this->assertGreaterThan(0, DB::table('chart_of_accounts')->where('institute_id', $institute->id)->count());
        $this->assertSame(0, DB::table('chart_of_accounts')->where('institute_id', $institute->id)->where('is_system', 0)->count());
        $this->assertSame($createdBy, (int) DB::table('chart_of_accounts')->where('institute_id', $institute->id)->orderBy('id')->value('created_by'));

        $this->assertSame(5, DB::table('account_groups')->where('institute_id', $institute->id)->count());
        $this->assertSame(0, DB::table('account_groups')->where('institute_id', $institute->id)->where('is_system', 0)->count());
        $this->assertSame($createdBy, (int) DB::table('account_groups')->where('institute_id', $institute->id)->orderBy('id')->value('created_by'));
    }
}

<?php

namespace Tests\Feature;

use App\Models\AccountHead;
use App\Models\Branch;
use App\Models\Institute;
use App\Models\InstituteSetting;
use App\Models\InstituteUser;
use App\Models\Role;
use App\Models\Setting;
use App\Models\Transaction;
use App\Services\Ai\AiContext;
use App\Services\Ai\AiService;
use App\Services\Ai\AiToolRegistry;
use App\Services\Ai\Contracts\AiProvider;
use App\Services\Ai\Contracts\AiProviderResponse;
use App\Services\Ai\Tools\Core\IncomeExpenseTool;
use App\Services\Ai\Tools\Education\StudentsTool;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class FakeFinanceProvider implements AiProvider
{
    public array $calls = [];

    public function __construct(
        protected ?AiProviderResponse $first = null,
        protected ?AiProviderResponse $second = null,
    ) {}

    public function chat(array $messages, array $tools): AiProviderResponse
    {
        $this->calls[] = $messages;

        if ($this->first !== null && count($this->calls) === 1) {
            return $this->first;
        }

        return $this->second ?? new AiProviderResponse('fallback answer', [], 10);
    }
}

class AiCoreFinanceTest extends TestCase
{
    use DatabaseTransactions;

    protected string $password = 'secret12345';

    protected function setUp(): void
    {
        parent::setUp();

        TenantContext::clear();
    }

    protected function freshInstitute(string $industry = 'education'): Institute
    {
        $institute = Institute::create([
            'name' => 'Fixture '.ucfirst($industry).' '.mt_rand(1000, 9999),
            'slug' => $industry.'-'.mt_rand(1000, 9999),
            'industry' => $industry,
            'status' => 'active',
        ]);

        InstituteSetting::withoutGlobalScopes()->create([
            'institute_id' => $institute->id,
            'ai_config' => [
                'enabled' => true,
                'features' => ['assistant'],
                'daily_limit' => 0,
                'monthly_limit' => 0,
            ],
        ]);

        return $institute;
    }

    protected function ownerFor(Institute $institute, string $email, array $extra = []): InstituteUser
    {
        return $this->userFor($institute, 'institute-owner', $email, $extra);
    }

    protected function userFor(Institute $institute, string $roleSlug, string $email, array $extra = []): InstituteUser
    {
        $role = Role::where('slug', $roleSlug)->firstOrFail();

        return InstituteUser::create(array_merge([
            'institute_id' => $institute->id,
            'role_id' => $role->id,
            'email' => $email,
            'phone' => '0170000'.substr(md5($email), 0, 4),
            'password_hash' => bcrypt($this->password),
            'status' => 'active',
        ], $extra));
    }

    protected function contextFor(InstituteUser $user, Institute $institute): AiContext
    {
        return AiContext::resolve($user, $institute);
    }

    protected function makeLedger(Institute $institute, Branch $branch): void
    {
        $incomeHead = AccountHead::create(['institute_id' => $institute->id, 'name' => 'Sales', 'type' => 'income', 'status' => 'active']);
        $expenseHead = AccountHead::create(['institute_id' => $institute->id, 'name' => 'Rent', 'type' => 'expense', 'status' => 'active']);

        Transaction::create([
            'institute_id' => $institute->id,
            'branch_id' => $branch->id,
            'account_head_id' => $incomeHead->id,
            'type' => 'income',
            'amount' => 2000,
            'transaction_date' => Carbon::today()->toDateString(),
            'description' => 'Sales income',
        ]);
        Transaction::create([
            'institute_id' => $institute->id,
            'branch_id' => $branch->id,
            'account_head_id' => $expenseHead->id,
            'type' => 'expense',
            'amount' => 500,
            'transaction_date' => Carbon::today()->toDateString(),
            'description' => 'Office rent',
        ]);
        Transaction::create([
            'institute_id' => $institute->id,
            'branch_id' => $branch->id,
            'account_head_id' => $incomeHead->id,
            'type' => 'income',
            'amount' => 1000,
            'transaction_date' => Carbon::yesterday()->toDateString(),
            'description' => 'Earlier sales',
        ]);
    }

    public function test_registry_isolates_industries(): void
    {
        $registry = app(AiToolRegistry::class);

        foreach (['real_estate', 'transportation', 'restaurant'] as $industry) {
            $ctx = new AiContext(
                actor: null,
                institute: null,
                industry: $industry,
                aiEnabled: true,
                enabledFeatures: ['assistant'],
                permissions: ['*'],
            );

            $available = $registry->available($ctx);

            $this->assertArrayHasKey('get_income_expense', $available, $industry.' should get core tools');
            $this->assertArrayHasKey('get_financial_summary', $available, $industry.' should get core finance tools');
            $this->assertArrayHasKey('get_crm_summary', $available, $industry.' should get core CRM tools');
            $this->assertArrayNotHasKey((new StudentsTool)->name(), $available, $industry.' must not get education tools');
            $this->assertCount(3, $available, $industry.' should have core tools only');
        }
    }

    public function test_income_expense_tool_aggregates_correctly(): void
    {
        $institute = $this->freshInstitute('restaurant');
        $branch = Branch::create(['institute_id' => $institute->id, 'name' => 'Downtown', 'status' => 'active']);
        TenantContext::set($institute->id);
        $this->makeLedger($institute, $branch);

        $owner = $this->ownerFor($institute, 'finance-owner@example.test');
        $ctx = $this->contextFor($owner, $institute);

        $result = (new IncomeExpenseTool)->handle([], $ctx);

        $this->assertSame(3, $result['total_transactions']);
        $this->assertSame(3000.0, $result['total_income']);
        $this->assertSame(500.0, $result['total_expense']);
        $this->assertSame(2500.0, $result['net']);
        $this->assertSame('BDT', $result['currency']);
        $this->assertCount(3, $result['rows']);
    }

    public function test_income_expense_tool_group_by_month_and_head(): void
    {
        $institute = $this->freshInstitute('transportation');
        $branch = Branch::create(['institute_id' => $institute->id, 'name' => 'Central', 'status' => 'active']);
        TenantContext::set($institute->id);
        $this->makeLedger($institute, $branch);

        $owner = $this->ownerFor($institute, 'finance-group@example.test');
        $ctx = $this->contextFor($owner, $institute);

        $byHead = (new IncomeExpenseTool)->handle(['group_by' => 'head'], $ctx);
        $this->assertArrayHasKey('Sales', $byHead['by_head']);
        $this->assertArrayHasKey('Rent', $byHead['by_head']);
        $this->assertSame(3000.0, $byHead['by_head']['Sales']['income']);

        $byMonth = (new IncomeExpenseTool)->handle([
            'group_by' => 'month',
            'from' => Carbon::today()->toDateString(),
            'to' => Carbon::today()->toDateString(),
        ], $ctx);
        $month = Carbon::today()->format('Y-m');
        $this->assertArrayHasKey($month, $byMonth['by_month']);
        $this->assertSame(2000.0, $byMonth['by_month'][$month]['income']);
        $this->assertSame(500.0, $byMonth['by_month'][$month]['expense']);
    }

    public function test_income_expense_tool_branch_filter(): void
    {
        $institute = $this->freshInstitute('real_estate');
        $branchA = Branch::create(['institute_id' => $institute->id, 'name' => 'Branch A', 'status' => 'active']);
        $branchB = Branch::create(['institute_id' => $institute->id, 'name' => 'Branch B', 'status' => 'active']);
        TenantContext::set($institute->id);
        $this->makeLedger($institute, $branchA);

        $otherHead = AccountHead::create(['institute_id' => $institute->id, 'name' => 'Service Income', 'type' => 'income', 'status' => 'active']);
        Transaction::create([
            'institute_id' => $institute->id,
            'branch_id' => $branchB->id,
            'account_head_id' => $otherHead->id,
            'type' => 'income',
            'amount' => 9000,
            'transaction_date' => Carbon::today()->toDateString(),
        ]);

        $owner = $this->ownerFor($institute, 'finance-branch@example.test');
        $ctx = $this->contextFor($owner, $institute);

        $result = (new IncomeExpenseTool)->handle(['branch_id' => $branchA->id], $ctx);

        $this->assertSame(3000.0, $result['total_income']);
        $this->assertSame(3, $result['total_transactions']);
    }

    public function test_income_expense_tool_isolates_tenants(): void
    {
        $institute = $this->freshInstitute('restaurant');
        $branch = Branch::create(['institute_id' => $institute->id, 'name' => 'Downtown', 'status' => 'active']);
        TenantContext::set($institute->id);
        $this->makeLedger($institute, $branch);

        $other = $this->freshInstitute('restaurant');
        $otherBranch = Branch::create(['institute_id' => $other->id, 'name' => 'Other Branch', 'status' => 'active']);
        $incomeHead = AccountHead::create(['institute_id' => $other->id, 'name' => 'Sales', 'type' => 'income', 'status' => 'active']);
        Transaction::create([
            'institute_id' => $other->id,
            'branch_id' => $otherBranch->id,
            'account_head_id' => $incomeHead->id,
            'type' => 'income',
            'amount' => 999999,
            'transaction_date' => Carbon::today()->toDateString(),
        ]);

        $owner = $this->ownerFor($institute, 'finance-tenant@example.test');
        $ctx = $this->contextFor($owner, $institute);

        $result = (new IncomeExpenseTool)->handle(['institute_id' => $other->id], $ctx);
        $this->assertSame(3000.0, $result['total_income']);
        $this->assertStringNotContainsString('999999', json_encode($result['rows']));
    }

    public function test_income_expense_tool_empty_and_invalid(): void
    {
        $institute = $this->freshInstitute('restaurant');
        TenantContext::set($institute->id);

        $owner = $this->ownerFor($institute, 'finance-empty@example.test');
        $ctx = $this->contextFor($owner, $institute);

        $empty = (new IncomeExpenseTool)->handle([], $ctx);
        $this->assertSame(0, $empty['total_transactions']);
        $this->assertSame(0.0, $empty['total_income']);
        $this->assertSame(0.0, $empty['total_expense']);
        $this->assertSame([], $empty['rows']);

        $invalidGroup = (new IncomeExpenseTool)->handle(['group_by' => 'bogus'], $ctx);
        $this->assertArrayNotHasKey('by_head', $invalidGroup);
        $this->assertArrayNotHasKey('by_month', $invalidGroup);
    }

    public function test_income_expense_tool_date_range(): void
    {
        $institute = $this->freshInstitute('transportation');
        $branch = Branch::create(['institute_id' => $institute->id, 'name' => 'Central', 'status' => 'active']);
        TenantContext::set($institute->id);
        $this->makeLedger($institute, $branch);

        $owner = $this->ownerFor($institute, 'finance-dates@example.test');
        $ctx = $this->contextFor($owner, $institute);

        $result = (new IncomeExpenseTool)->handle([
            'from' => Carbon::today()->toDateString(),
            'to' => Carbon::today()->toDateString(),
        ], $ctx);

        $this->assertSame(2, $result['total_transactions']);
        $this->assertSame(2000.0, $result['total_income']);
        $this->assertStringContainsString('to', $result['period'] ?? '');
    }

    public function test_income_expense_tool_respects_limit(): void
    {
        $institute = $this->freshInstitute('real_estate');
        $branch = Branch::create(['institute_id' => $institute->id, 'name' => 'Central', 'status' => 'active']);
        TenantContext::set($institute->id);
        $this->makeLedger($institute, $branch);

        $owner = $this->ownerFor($institute, 'finance-limit@example.test');
        $ctx = $this->contextFor($owner, $institute);

        $result = (new IncomeExpenseTool)->handle(['limit' => 2], $ctx);

        $this->assertCount(2, $result['rows']);
    }

    public function test_income_expense_requires_finance_permission(): void
    {
        $institute = $this->freshInstitute('restaurant');
        $branch = Branch::create(['institute_id' => $institute->id, 'name' => 'Downtown', 'status' => 'active']);
        TenantContext::set($institute->id);
        $this->makeLedger($institute, $branch);

        $registry = app(AiToolRegistry::class);

        $teacher = $this->userFor($institute, 'teacher', 'finance-denied@example.test');
        $teacherCtx = $this->contextFor($teacher, $institute);
        $this->assertArrayNotHasKey('get_income_expense', $registry->available($teacherCtx));

        $accountant = $this->userFor($institute, 'institute-admin', 'finance-allowed@example.test');
        $accountantCtx = $this->contextFor($accountant, $institute);
        $this->assertArrayHasKey('get_income_expense', $registry->available($accountantCtx));
    }

    public function test_service_handles_english_bangla_and_banglish_prompts(): void
    {
        $institute = $this->freshInstitute('restaurant');
        $branch = Branch::create(['institute_id' => $institute->id, 'name' => 'Downtown', 'status' => 'active']);
        TenantContext::set($institute->id);
        $this->makeLedger($institute, $branch);

        $owner = $this->ownerFor($institute, 'finance-lang@example.test');
        $ctx = $this->contextFor($owner, $institute);
        Setting::set('ai.enabled', '1');

        foreach ([
            'What is today\'s total sales?',
            'আজকের মোট sales কত?',
            'ajker total sales koto?',
        ] as $prompt) {
            app()->instance(AiProvider::class, new FakeFinanceProvider(
                first: new AiProviderResponse('', [
                    ['name' => 'get_income_expense', 'arguments' => '{"group_by":"none"}', 'id' => 'call_lang'],
                ], 10),
                second: new AiProviderResponse('Today\'s income is 2000 taka.', [], 5)
            ));

            $result = app(AiService::class)->ask($prompt, $ctx);

            $this->assertSame('ok', $result['status']);
            $this->assertContains('get_income_expense', $result['tools']);
            $this->assertSame('Today\'s income is 2000 taka.', $result['content']);
        }
    }

    public function test_service_gates_block_core_tool(): void
    {
        $institute = $this->freshInstitute('restaurant');
        $owner = $this->ownerFor($institute, 'finance-gates@example.test');
        $ctx = $this->contextFor($owner, $institute);

        Setting::set('ai.enabled', '0');
        $result = app(AiService::class)->ask('income?', $ctx);
        $this->assertSame('blocked', $result['status']);

        Setting::set('ai.enabled', '1');
        $institute->settings()->update(['ai_config' => ['enabled' => false, 'features' => ['assistant']]]);
        $ctx = $this->contextFor($owner, Institute::find($institute->id));
        $result = app(AiService::class)->ask('income?', $ctx);
        $this->assertSame('blocked', $result['status']);
    }
}

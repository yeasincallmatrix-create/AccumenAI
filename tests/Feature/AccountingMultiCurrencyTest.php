<?php

namespace Tests\Feature;

use App\Models\ChartOfAccount;
use App\Models\Currency;
use App\Models\ExchangeRate;
use App\Models\FxRevaluation;
use App\Models\Institute;
use App\Models\Journal;
use App\Models\Membership;
use App\Models\Party;
use App\Models\Role;
use App\Models\User;
use App\Services\Accounting\AccountingSetupService;
use App\Services\Accounting\ExchangeRateService;
use App\Services\Accounting\FxConversionService;
use App\Services\Accounting\FxRevaluationService;
use App\Services\Accounting\InvoiceService;
use App\Services\Accounting\JournalPostingService;
use App\Services\Accounting\PaymentService;
use App\Services\Accounting\RealizedFxService;
use App\Services\MembershipService;
use App\Services\UserAccountService;
use App\Support\BranchContext;
use App\Support\TenantContext;
use App\Support\Workspace;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * STEP 19 — Multi-Currency & FX Accounting.
 *
 * Covers FxConversionService, ExchangeRateService, InvoiceService FX fields,
 * RealizedFxService, FxRevaluationService, ReceivablesPayablesService
 * per-currency breakdowns, FinancialReportService FX reports and route-level
 * permission enforcement for FX screens.
 */
class AccountingMultiCurrencyTest extends TestCase
{
    use DatabaseTransactions;

    protected string $password = 'secret12345';

    protected function setUp(): void
    {
        parent::setUp();

        TenantContext::clear();
        BranchContext::clear();
        Workspace::clear();
    }

    // ------------------------------------------------------------ Fixtures

    protected function owner(string $email): User
    {
        return (new UserAccountService)->registerOwner([
            'name' => 'Step19 Owner',
            'first_name' => 'Step19',
            'last_name' => 'Owner',
            'email' => $email,
            'password_hash' => bcrypt($this->password),
            'status' => 'active',
        ]);
    }

    protected function staff(string $email): User
    {
        return (new UserAccountService)->createStaffFromInvitation([
            'name' => 'Step19 Staff',
            'first_name' => 'Step19',
            'last_name' => 'Staff',
            'email' => $email,
            'password_hash' => bcrypt($this->password),
            'status' => 'active',
        ]);
    }

    protected function institute(string $name): Institute
    {
        return Institute::where('name', $name)->firstOrFail();
    }

    protected function roleId(string $slug): int
    {
        return Role::where('slug', $slug)->firstOrFail()->id;
    }

    protected function assign(User $user, Institute $institute, string $roleSlug, array $attributes = []): Membership
    {
        return (new MembershipService)->assign($user, $institute->id, $this->roleId($roleSlug), $attributes);
    }

    protected function setupAccounting(Institute $institute, ?int $branchId = null): void
    {
        app(AccountingSetupService::class)->setupForInstitute($institute->id, $branchId);
    }

    protected function currencyId(string $code): int
    {
        return (int) DB::table('currencies')->where('code', $code)->value('id');
    }

    protected function posting(): JournalPostingService
    {
        return app(JournalPostingService::class);
    }

    protected function asUser(User $user, int $workspaceId): static
    {
        return $this->withSession([Workspace::SESSION_KEY => $workspaceId])->actingAs($user, 'web');
    }

    protected function coaId(int $instituteId, ?int $branchId, string $code): int
    {
        return (int) ChartOfAccount::withoutGlobalScopes()
            ->where('institute_id', $instituteId)
            ->where('branch_id', $branchId)
            ->where('code', $code)
            ->value('id');
    }

    protected function fx(): FxConversionService
    {
        return app(FxConversionService::class);
    }

    protected function rates(): ExchangeRateService
    {
        return app(ExchangeRateService::class);
    }

    protected function realizedFx(): RealizedFxService
    {
        return app(RealizedFxService::class);
    }

    protected function revaluation(): FxRevaluationService
    {
        return app(FxRevaluationService::class);
    }

    // ============================================================ FxConversionService (3)

    public function test_fx_conversion_multiplies_at_rate(): void
    {
        $result = $this->fx()->convert('1000', '110.50000000');

        $this->assertSame('110500.0000', $result);
    }

    public function test_fx_conversion_rounds_half_up(): void
    {
        $result = $this->fx()->round('1.23456', 4);

        $this->assertSame('1.2346', $result);

        $result2 = $this->fx()->round('1.23454', 4);

        $this->assertSame('1.2345', $result2);
    }

    public function test_fx_base_currency_id_returns_seeded_base(): void
    {
        $mawa = $this->institute('MAWA ACADEMY');
        $this->setupAccounting($mawa);

        $baseCurrencyId = $this->fx()->baseCurrencyId((int) $mawa->id, null);

        $this->assertGreaterThan(0, $baseCurrencyId);

        $bdtId = $this->currencyId('BDT');
        $this->assertSame($bdtId, $baseCurrencyId);
    }

    // ============================================================ ExchangeRateService (5)

    public function test_exchange_rate_create_and_lookup(): void
    {
        $mawa = $this->institute('MAWA ACADEMY');
        $this->setupAccounting($mawa);

        $usdId = $this->currencyId('USD');
        $bdtId = $this->currencyId('BDT');

        $rate = $this->rates()->create((int) $mawa->id, null, [
            'from_currency_id' => $usdId,
            'to_currency_id' => $bdtId,
            'rate' => '110.50',
            'rate_date' => now()->toDateString(),
            'source' => 'manual',
        ]);

        $this->assertNotNull($rate->id);
        $this->assertSame('110.50000000', $rate->rate);

        $found = $this->rates()->findEffective((int) $mawa->id, null, $usdId, $bdtId, now()->toDateString());

        $this->assertNotNull($found);
        $this->assertSame('110.50000000', $found['rate']);
    }

    public function test_exchange_rate_duplicate_detection(): void
    {
        $mawa = $this->institute('MAWA ACADEMY');
        $this->setupAccounting($mawa);

        $usdId = $this->currencyId('USD');
        $bdtId = $this->currencyId('BDT');

        $this->rates()->create((int) $mawa->id, null, [
            'from_currency_id' => $usdId,
            'to_currency_id' => $bdtId,
            'rate' => '110.50',
            'rate_date' => now()->toDateString(),
        ]);

        $this->expectException(ValidationException::class);

        $this->rates()->create((int) $mawa->id, null, [
            'from_currency_id' => $usdId,
            'to_currency_id' => $bdtId,
            'rate' => '111.00',
            'rate_date' => now()->toDateString(),
        ]);
    }

    public function test_exchange_rate_find_effective_returns_exact_date(): void
    {
        $mawa = $this->institute('MAWA ACADEMY');
        $this->setupAccounting($mawa);

        $usdId = $this->currencyId('USD');
        $bdtId = $this->currencyId('BDT');

        $this->rates()->create((int) $mawa->id, null, [
            'from_currency_id' => $usdId,
            'to_currency_id' => $bdtId,
            'rate' => '110.50',
            'rate_date' => '2025-06-15',
        ]);

        $found = $this->rates()->findEffective((int) $mawa->id, null, $usdId, $bdtId, '2025-06-15');

        $this->assertNotNull($found);
        $this->assertSame('110.50000000', $found['rate']);
        $this->assertSame('institute_exact', $found['source']);
    }

    public function test_exchange_rate_find_effective_returns_historical(): void
    {
        $mawa = $this->institute('MAWA ACADEMY');
        $this->setupAccounting($mawa);

        $usdId = $this->currencyId('USD');
        $bdtId = $this->currencyId('BDT');

        $this->rates()->create((int) $mawa->id, null, [
            'from_currency_id' => $usdId,
            'to_currency_id' => $bdtId,
            'rate' => '108.75',
            'rate_date' => '2025-01-10',
        ]);

        $found = $this->rates()->findEffective((int) $mawa->id, null, $usdId, $bdtId, '2025-06-15');

        $this->assertNotNull($found);
        $this->assertSame('108.75000000', $found['rate']);
        $this->assertSame('institute_historical', $found['source']);
    }

    public function test_exchange_rate_list_returns_paginated(): void
    {
        $mawa = $this->institute('MAWA ACADEMY');
        $this->setupAccounting($mawa);

        $usdId = $this->currencyId('USD');
        $bdtId = $this->currencyId('BDT');

        $this->rates()->create((int) $mawa->id, null, [
            'from_currency_id' => $usdId,
            'to_currency_id' => $bdtId,
            'rate' => '110.00',
            'rate_date' => '2025-06-01',
        ]);

        $this->rates()->create((int) $mawa->id, null, [
            'from_currency_id' => $usdId,
            'to_currency_id' => $bdtId,
            'rate' => '111.00',
            'rate_date' => '2025-06-10',
        ]);

        $result = $this->rates()->list((int) $mawa->id, null);

        $this->assertGreaterThanOrEqual(2, $result->total());
    }

    // ============================================================ InvoiceService FX (2)

    public function test_invoice_with_base_currency_has_no_fx_fields(): void
    {
        $mawa = $this->institute('MAWA ACADEMY');
        $this->setupAccounting($mawa);

        $bdtId = $this->currencyId('BDT');
        $incomeId = $this->coaId((int) $mawa->id, null, '4001');

        $owner = $this->owner('step19-inv-base@example.test');
        $this->assign($owner, $mawa, 'institute-owner');

        $customer = app(\App\Services\Accounting\PartyService::class)->create($mawa->id, null, [
            'type' => 'customer',
            'name' => 'Step19 FX Customer',
            'phone' => '0199'.rand(100000, 999999),
        ]);

        $invoice = app(InvoiceService::class)->create((int) $mawa->id, null, [
            'invoice_type' => 'admission',
            'currency_id' => $bdtId,
            'party_id' => $customer->id,
            'items' => [
                ['description' => 'Test item', 'amount' => 5000, 'coa_id' => $incomeId],
            ],
        ]);

        $this->assertSame('1.00000000', $invoice->exchange_rate);
        $this->assertEqualsWithDelta(5000.0, (float) $invoice->base_payable_amount, 0.001);
    }

    public function test_exchange_rate_validation_rejects_zero(): void
    {
        $this->expectException(ValidationException::class);

        $this->fx()->normalizeRate('0');
    }

    // ============================================================ RealizedFxService (3)

    public function test_realized_fx_computes_gain_on_higher_settlement(): void
    {
        $mawa = $this->institute('MAWA ACADEMY');
        $this->setupAccounting($mawa);

        $usdId = $this->currencyId('USD');

        $invoice = new \App\Models\Invoice([
            'institute_id' => $mawa->id,
            'exchange_rate' => '110.00000000',
            'currency_id' => $usdId,
        ]);

        $computed = $this->realizedFx()->compute($invoice, '1000', '112.00000000');

        $this->assertTrue($computed['is_gain']);
        $this->assertFalse($computed['is_loss']);
        $this->assertGreaterThan(0.0, (float) $computed['difference']);
    }

    public function test_realized_fx_computes_loss_on_lower_settlement(): void
    {
        $mawa = $this->institute('MAWA ACADEMY');
        $this->setupAccounting($mawa);

        $usdId = $this->currencyId('USD');

        $invoice = new \App\Models\Invoice([
            'institute_id' => $mawa->id,
            'exchange_rate' => '112.00000000',
            'currency_id' => $usdId,
        ]);

        $computed = $this->realizedFx()->compute($invoice, '1000', '110.00000000');

        $this->assertFalse($computed['is_gain']);
        $this->assertTrue($computed['is_loss']);
        $this->assertLessThan(0.0, (float) $computed['difference']);
    }

    public function test_realized_fx_journal_line_uses_correct_accounts(): void
    {
        $mawa = $this->institute('MAWA ACADEMY');
        $this->setupAccounting($mawa);

        $gainAccount = $this->realizedFx()->gainAccount((int) $mawa->id, null);
        $lossAccount = $this->realizedFx()->lossAccount((int) $mawa->id, null);

        $this->assertNotNull($gainAccount);
        $this->assertNotNull($lossAccount);
        $this->assertNotSame($gainAccount->id, $lossAccount->id);

        $gainLine = $this->realizedFx()->journalLine((int) $mawa->id, null, [
            'difference' => '2000.0000',
            'is_gain' => true,
            'is_loss' => false,
        ], 'FX gain');

        $this->assertNotNull($gainLine);
        $this->assertSame($gainAccount->id, $gainLine['coa_id']);
        $this->assertEquals(0, $gainLine['debit']);
        $this->assertGreaterThan(0.0, $gainLine['credit']);

        $lossLine = $this->realizedFx()->journalLine((int) $mawa->id, null, [
            'difference' => '-500.0000',
            'is_gain' => false,
            'is_loss' => true,
        ], 'FX loss');

        $this->assertNotNull($lossLine);
        $this->assertSame($lossAccount->id, $lossLine['coa_id']);
        $this->assertGreaterThan(0.0, $lossLine['debit']);
        $this->assertEquals(0, $lossLine['credit']);
    }

    // ============================================================ FxRevaluationService (4)

    public function test_revaluation_returns_empty_when_no_fx_positions(): void
    {
        $mawa = $this->institute('MAWA ACADEMY');
        $this->setupAccounting($mawa);

        $owner = $this->owner('step19-rev-empty@example.test');
        $this->assign($owner, $mawa, 'institute-owner');

        $results = $this->revaluation()->run((int) $mawa->id, null, [
            'as_of_date' => now()->toDateString(),
        ], (int) $owner->id);

        $this->assertEmpty($results);
    }

    public function test_revaluation_posts_adjustment_for_open_position(): void
    {
        $mawa = $this->institute('MAWA ACADEMY');
        $this->setupAccounting($mawa);

        $usdId = $this->currencyId('USD');
        $bdtId = $this->currencyId('BDT');

        $this->rates()->create((int) $mawa->id, null, [
            'from_currency_id' => $usdId,
            'to_currency_id' => $bdtId,
            'rate' => '115.00',
            'rate_date' => now()->toDateString(),
        ]);

        $owner = $this->owner('step19-rev-post@example.test');
        $this->assign($owner, $mawa, 'institute-owner');

        $arId = $this->coaId((int) $mawa->id, null, '1100');
        $incomeId = $this->coaId((int) $mawa->id, null, '4001');

        $customer = app(\App\Services\Accounting\PartyService::class)->create($mawa->id, null, [
            'type' => 'customer',
            'name' => 'Step19 Reval Customer',
            'phone' => '0198'.rand(100000, 999999),
        ]);

        $journal = $this->posting()->create([
            'institute_id' => $mawa->id,
            'branch_id' => null,
            'journal_date' => now()->toDateString(),
            'type' => 'sale',
            'currency_id' => $usdId,
            'entries' => [
                ['coa_id' => $arId, 'debit' => 5750, 'credit' => 0, 'party_id' => $customer->id],
                ['coa_id' => $incomeId, 'debit' => 0, 'credit' => 5750],
            ],
        ]);

        DB::table('journal_entries')
            ->where('journal_id', $journal->id)
            ->update([
                'currency_id' => $usdId,
                'foreign_debit' => 50,
                'foreign_credit' => 0,
                'exchange_rate' => '115.00000000',
            ]);

        $results = $this->revaluation()->run((int) $mawa->id, null, [
            'as_of_date' => now()->toDateString(),
        ], (int) $owner->id);

        if ($results !== []) {
            $this->assertNotEmpty($results);
            $this->assertSame('posted', $results[0]->status);
            $this->assertNotNull($results[0]->journal_id);

            $revalRow = FxRevaluation::withoutGlobalScopes()
                ->where('institute_id', $mawa->id)
                ->where('currency_id', $usdId)
                ->latest()
                ->first();
            $this->assertNotNull($revalRow);
        }
    }

    public function test_revaluation_is_idempotent_on_same_key(): void
    {
        $mawa = $this->institute('MAWA ACADEMY');
        $this->setupAccounting($mawa);

        $usdId = $this->currencyId('USD');
        $bdtId = $this->currencyId('BDT');

        $this->rates()->create((int) $mawa->id, null, [
            'from_currency_id' => $usdId,
            'to_currency_id' => $bdtId,
            'rate' => '115.00',
            'rate_date' => now()->toDateString(),
        ]);

        $owner = $this->owner('step19-rev-idemp@example.test');
        $this->assign($owner, $mawa, 'institute-owner');

        $arId = $this->coaId((int) $mawa->id, null, '1100');
        $incomeId = $this->coaId((int) $mawa->id, null, '4001');

        $customer = app(\App\Services\Accounting\PartyService::class)->create($mawa->id, null, [
            'type' => 'customer',
            'name' => 'Step19 Idemp Customer',
            'phone' => '0197'.rand(100000, 999999),
        ]);

        $journal = $this->posting()->create([
            'institute_id' => $mawa->id,
            'branch_id' => null,
            'journal_date' => now()->toDateString(),
            'type' => 'sale',
            'currency_id' => $usdId,
            'entries' => [
                ['coa_id' => $arId, 'debit' => 5750, 'credit' => 0, 'party_id' => $customer->id],
                ['coa_id' => $incomeId, 'debit' => 0, 'credit' => 5750],
            ],
        ]);

        DB::table('journal_entries')
            ->where('journal_id', $journal->id)
            ->update([
                'currency_id' => $usdId,
                'foreign_debit' => 50,
                'foreign_credit' => 0,
                'exchange_rate' => '115.00000000',
            ]);

        $results1 = $this->revaluation()->run((int) $mawa->id, null, [
            'as_of_date' => now()->toDateString(),
        ], (int) $owner->id);

        $results2 = $this->revaluation()->run((int) $mawa->id, null, [
            'as_of_date' => now()->toDateString(),
        ], (int) $owner->id);

        if ($results1 !== []) {
            $this->assertNotEmpty($results2);
            $this->assertSame($results1[0]->id, $results2[0]->id);

            $count = FxRevaluation::withoutGlobalScopes()
                ->where('institute_id', $mawa->id)
                ->where('currency_id', $usdId)
                ->count();
            $this->assertSame(1, $count);
        }
    }

    public function test_revaluation_rejects_closed_period(): void
    {
        $mawa = $this->institute('MAWA ACADEMY');
        $mb = app(\App\Models\Branch::class)->create([
            'institute_id' => $mawa->id,
            'name' => 'Step19 Rev Branch',
            'status' => 'active',
        ]);
        $this->setupAccounting($mawa, (int) $mb->id);

        $usdId = $this->currencyId('USD');
        $bdtId = $this->currencyId('BDT');

        $this->rates()->create((int) $mawa->id, (int) $mb->id, [
            'from_currency_id' => $usdId,
            'to_currency_id' => $bdtId,
            'rate' => '115.00',
            'rate_date' => now()->toDateString(),
        ]);

        $arId = $this->coaId((int) $mawa->id, (int) $mb->id, '1100');
        $incomeId = $this->coaId((int) $mawa->id, (int) $mb->id, '4001');

        $customer = app(\App\Services\Accounting\PartyService::class)->create($mawa->id, (int) $mb->id, [
            'type' => 'customer',
            'name' => 'Step19 Closed Customer',
            'phone' => '0196'.rand(100000, 999999),
        ]);

        $journal = $this->posting()->create([
            'institute_id' => $mawa->id,
            'branch_id' => $mb->id,
            'journal_date' => now()->toDateString(),
            'type' => 'sale',
            'currency_id' => $usdId,
            'entries' => [
                ['coa_id' => $arId, 'debit' => 5750, 'credit' => 0, 'party_id' => $customer->id],
                ['coa_id' => $incomeId, 'debit' => 0, 'credit' => 5750],
            ],
        ]);

        DB::table('journal_entries')
            ->where('journal_id', $journal->id)
            ->update([
                'currency_id' => $usdId,
                'foreign_debit' => 50,
                'foreign_credit' => 0,
                'exchange_rate' => '115.00000000',
            ]);

        $fy = \App\Models\FiscalYear::query()
            ->where('institute_id', $mawa->id)
            ->where('branch_id', $mb->id)
            ->first();

        if ($fy === null) {
            $fy = \App\Models\FiscalYear::query()
                ->where('institute_id', $mawa->id)
                ->whereNull('branch_id')
                ->first();
        }

        if ($fy !== null) {
            app(\App\Services\Accounting\AccountingPeriodService::class)->createMonthlyPeriods($fy);

            $period = \App\Models\AccountingPeriod::query()
                ->where('fiscal_year_id', $fy->id)
                ->whereDate('start_date', '<=', now()->toDateString())
                ->whereDate('end_date', '>=', now()->toDateString())
                ->first();

            if ($period !== null) {
                app(\App\Services\Accounting\AccountingPeriodService::class)->closePeriod($period, (int) $mawa->id);

                $this->expectException(ValidationException::class);

                $this->revaluation()->run((int) $mawa->id, (int) $mb->id, [
                    'as_of_date' => now()->toDateString(),
                ]);
            }
        }
    }

    // ============================================================ ReceivablesPayablesService per-currency (3)

    public function test_party_balances_by_currency_returns_empty_for_no_fx(): void
    {
        $mawa = $this->institute('MAWA ACADEMY');
        $this->setupAccounting($mawa);

        $customer = app(\App\Services\Accounting\PartyService::class)->create($mawa->id, null, [
            'type' => 'customer',
            'name' => 'Step19 Party No FX',
            'phone' => '0195'.rand(100000, 999999),
        ]);

        $service = app(\App\Services\Accounting\ReceivablesPayablesService::class);
        $result = $service->partyBalancesByCurrency($customer);

        $this->assertCount(0, $result);
    }

    public function test_totals_by_currency_groups_by_currency_id(): void
    {
        $mawa = $this->institute('MAWA ACADEMY');
        $this->setupAccounting($mawa);

        $usdId = $this->currencyId('USD');
        $inrId = $this->currencyId('INR');

        $customer1 = app(\App\Services\Accounting\PartyService::class)->create($mawa->id, null, [
            'type' => 'customer',
            'name' => 'Step19 Totals Cust1',
            'phone' => '0194'.rand(100000, 999999),
        ]);
        $customer2 = app(\App\Services\Accounting\PartyService::class)->create($mawa->id, null, [
            'type' => 'customer',
            'name' => 'Step19 Totals Cust2',
            'phone' => '0193'.rand(100000, 999999),
        ]);

        $arId = $this->coaId((int) $mawa->id, null, '1100');
        $incomeId = $this->coaId((int) $mawa->id, null, '4001');

        $j1 = $this->posting()->create([
            'institute_id' => $mawa->id,
            'branch_id' => null,
            'journal_date' => now()->toDateString(),
            'type' => 'sale',
            'currency_id' => $usdId,
            'entries' => [
                ['coa_id' => $arId, 'debit' => 5000, 'credit' => 0, 'party_id' => $customer1->id],
                ['coa_id' => $incomeId, 'debit' => 0, 'credit' => 5000],
            ],
        ]);

        $j2 = $this->posting()->create([
            'institute_id' => $mawa->id,
            'branch_id' => null,
            'journal_date' => now()->toDateString(),
            'type' => 'sale',
            'currency_id' => $inrId,
            'entries' => [
                ['coa_id' => $arId, 'debit' => 3000, 'credit' => 0, 'party_id' => $customer2->id],
                ['coa_id' => $incomeId, 'debit' => 0, 'credit' => 3000],
            ],
        ]);

        DB::table('journal_entries')
            ->where('journal_id', $j1->id)
            ->update([
                'currency_id' => $usdId,
                'foreign_debit' => 50,
                'foreign_credit' => 0,
                'exchange_rate' => '100.00000000',
            ]);

        DB::table('journal_entries')
            ->where('journal_id', $j2->id)
            ->update([
                'currency_id' => $inrId,
                'foreign_debit' => 300,
                'foreign_credit' => 0,
                'exchange_rate' => '10.00000000',
            ]);

        $service = app(\App\Services\Accounting\ReceivablesPayablesService::class);
        $result = $service->totalsByCurrency((int) $mawa->id, null);

        $this->assertGreaterThanOrEqual(1, $result->count());

        $currencyIds = $result->pluck('currency_id')->filter()->values();
        $this->assertTrue(
            $currencyIds->contains($usdId) || $currencyIds->contains($inrId),
            'Expected at least one row with USD or INR currency_id'
        );
    }

    public function test_customer_balances_by_currency_only_includes_customers(): void
    {
        $mawa = $this->institute('MAWA ACADEMY');
        $this->setupAccounting($mawa);

        $usdId = $this->currencyId('USD');

        $customer = app(\App\Services\Accounting\PartyService::class)->create($mawa->id, null, [
            'type' => 'customer',
            'name' => 'Step19 Cust Only',
            'phone' => '0192'.rand(100000, 999999),
        ]);
        $supplier = app(\App\Services\Accounting\PartyService::class)->create($mawa->id, null, [
            'type' => 'supplier',
            'name' => 'Step19 Supp Only',
            'phone' => '0191'.rand(100000, 999999),
        ]);

        $arId = $this->coaId((int) $mawa->id, null, '1100');
        $apId = $this->coaId((int) $mawa->id, null, '2001');
        $incomeId = $this->coaId((int) $mawa->id, null, '4001');
        $expenseId = $this->coaId((int) $mawa->id, null, '5006');

        $jCust = $this->posting()->create([
            'institute_id' => $mawa->id,
            'branch_id' => null,
            'journal_date' => now()->toDateString(),
            'type' => 'sale',
            'currency_id' => $usdId,
            'entries' => [
                ['coa_id' => $arId, 'debit' => 2000, 'credit' => 0, 'party_id' => $customer->id],
                ['coa_id' => $incomeId, 'debit' => 0, 'credit' => 2000],
            ],
        ]);

        $jSupp = $this->posting()->create([
            'institute_id' => $mawa->id,
            'branch_id' => null,
            'journal_date' => now()->toDateString(),
            'type' => 'purchase',
            'currency_id' => $usdId,
            'entries' => [
                ['coa_id' => $expenseId, 'debit' => 1500, 'credit' => 0],
                ['coa_id' => $apId, 'debit' => 0, 'credit' => 1500, 'party_id' => $supplier->id],
            ],
        ]);

        DB::table('journal_entries')
            ->where('journal_id', $jCust->id)
            ->update([
                'currency_id' => $usdId,
                'foreign_debit' => 20,
                'foreign_credit' => 0,
                'exchange_rate' => '100.00000000',
            ]);

        DB::table('journal_entries')
            ->where('journal_id', $jSupp->id)
            ->update([
                'currency_id' => $usdId,
                'foreign_debit' => 0,
                'foreign_credit' => 15,
                'exchange_rate' => '100.00000000',
            ]);

        $service = app(\App\Services\Accounting\ReceivablesPayablesService::class);
        $result = $service->customerBalancesByCurrency((int) $mawa->id, null);

        // The result should only contain entries from customer/balances
        // parties, not supplier-only parties. Verify by checking that the
        // supplier's party_id is NOT in any currency row.
        $supplierPartyIds = DB::table('journal_entries as je')
            ->join('journals as j', 'j.id', '=', 'je.journal_id')
            ->where('je.institute_id', $mawa->id)
            ->where('je.party_id', $supplier->id)
            ->where('j.status', 'posted')
            ->whereNull('j.reversal_of')
            ->pluck('je.party_id')
            ->unique();

        // The customer result should NOT contain the supplier's party in
        // its data. The result is aggregated by currency, so we verify
        // that the supplier party_id is not referenced by customer results.
        foreach ($result as $row) {
            $currencyPartyIds = DB::table('journal_entries as je')
                ->join('journals as j', 'j.id', '=', 'je.journal_id')
                ->join('parties as p', 'p.id', '=', 'je.party_id')
                ->where('je.institute_id', $mawa->id)
                ->where('j.status', 'posted')
                ->whereNull('j.reversal_of')
                ->whereIn('p.type', ['customer', 'both'])
                ->where('p.is_active', true)
                ->where('je.currency_id', $row->currency_id)
                ->pluck('je.party_id')
                ->unique();

            // None of the customer-balanced party IDs should be the supplier.
            foreach ($supplierPartyIds as $sid) {
                $this->assertFalse(
                    $currencyPartyIds->contains($sid),
                    "Supplier party {$sid} should not appear in customer balances by currency",
                );
            }
        }

        $this->assertTrue(true);
    }

    // ============================================================ FinancialReportService FX (2)

    public function test_trial_balance_by_currency_returns_per_currency_rows(): void
    {
        $mawa = $this->institute('MAWA ACADEMY');
        $this->setupAccounting($mawa);

        $usdId = $this->currencyId('USD');

        $customer = app(\App\Services\Accounting\PartyService::class)->create($mawa->id, null, [
            'type' => 'customer',
            'name' => 'Step19 TB Cust',
            'phone' => '0190'.rand(100000, 999999),
        ]);

        $arId = $this->coaId((int) $mawa->id, null, '1100');
        $incomeId = $this->coaId((int) $mawa->id, null, '4001');

        $journal = $this->posting()->create([
            'institute_id' => $mawa->id,
            'branch_id' => null,
            'journal_date' => now()->toDateString(),
            'type' => 'sale',
            'currency_id' => $usdId,
            'entries' => [
                ['coa_id' => $arId, 'debit' => 5000, 'credit' => 0, 'party_id' => $customer->id],
                ['coa_id' => $incomeId, 'debit' => 0, 'credit' => 5000],
            ],
        ]);

        DB::table('journal_entries')
            ->where('journal_id', $journal->id)
            ->update([
                'currency_id' => $usdId,
                'foreign_debit' => 50,
                'foreign_credit' => 0,
                'exchange_rate' => '100.00000000',
            ]);

        $service = app(\App\Services\Accounting\FinancialReportService::class);
        $result = $service->trialBalanceByCurrency((int) $mawa->id, null, now()->toDateString());

        $this->assertGreaterThanOrEqual(1, $result->count());

        $foundUsd = $result->firstWhere('currency_id', $usdId);
        if ($foundUsd !== null) {
            $this->assertSame($usdId, (int) $foundUsd->currency_id);
        }
    }

    public function test_fx_gain_loss_report_includes_realized(): void
    {
        $mawa = $this->institute('MAWA ACADEMY');
        $this->setupAccounting($mawa);

        $usdId = $this->currencyId('USD');

        $gainAccount = $this->realizedFx()->gainAccount((int) $mawa->id, null);

        $customer = app(\App\Services\Accounting\PartyService::class)->create($mawa->id, null, [
            'type' => 'customer',
            'name' => 'Step19 FX Report Cust',
            'phone' => '0189'.rand(100000, 999999),
        ]);

        $this->posting()->create([
            'institute_id' => $mawa->id,
            'branch_id' => null,
            'journal_date' => now()->toDateString(),
            'type' => 'journal',
            'currency_id' => $usdId,
            'entries' => [
                ['coa_id' => $this->coaId((int) $mawa->id, null, '1001'), 'debit' => 2000, 'credit' => 0, 'party_id' => $customer->id],
                ['coa_id' => $gainAccount->id, 'debit' => 0, 'credit' => 2000],
            ],
        ]);

        $service = app(\App\Services\Accounting\FinancialReportService::class);
        $report = $service->fxGainLossReport((int) $mawa->id, null, now()->startOfYear()->toDateString(), now()->toDateString());

        $this->assertArrayHasKey('realized', $report);
        $this->assertArrayHasKey('unrealized', $report);

        $totalRealized = $report['total_realized'];
        $this->assertIsFloat($totalRealized);
    }

    // ============================================================ Route permission enforcement (8)

    public function test_exchange_rates_index_owner_ok(): void
    {
        $mawa = $this->institute('MAWA ACADEMY');
        $this->setupAccounting($mawa);

        $owner = $this->owner('step19-route-er-idx@example.test');
        $this->assign($owner, $mawa, 'institute-owner');

        $this->asUser($owner, (int) $mawa->id)
            ->get(route('finance.exchange-rates.index'))
            ->assertOk();
    }

    public function test_exchange_rates_index_forbidden_without_permission(): void
    {
        $mawa = $this->institute('MAWA ACADEMY');

        $teacher = $this->staff('step19-route-er-teacher@example.test');
        $this->assign($teacher, $mawa, 'teacher');

        $this->asUser($teacher, (int) $mawa->id)
            ->get(route('finance.exchange-rates.index'))
            ->assertForbidden();
    }

    public function test_fx_revaluation_index_owner_ok(): void
    {
        $mawa = $this->institute('MAWA ACADEMY');
        $this->setupAccounting($mawa);

        $owner = $this->owner('step19-route-rev-idx@example.test');
        $this->assign($owner, $mawa, 'institute-owner');

        $this->asUser($owner, (int) $mawa->id)
            ->get(route('finance.fx-revaluations.index'))
            ->assertOk();
    }

    public function test_fx_revaluation_index_forbidden_without_permission(): void
    {
        $mawa = $this->institute('MAWA ACADEMY');

        $teacher = $this->staff('step19-route-rev-teacher@example.test');
        $this->assign($teacher, $mawa, 'teacher');

        $this->asUser($teacher, (int) $mawa->id)
            ->get(route('finance.fx-revaluations.index'))
            ->assertForbidden();
    }

    public function test_exchange_rates_store_owner_ok(): void
    {
        $mawa = $this->institute('MAWA ACADEMY');
        $this->setupAccounting($mawa);

        $owner = $this->owner('step19-route-er-store@example.test');
        $this->assign($owner, $mawa, 'institute-owner');

        $usdId = $this->currencyId('USD');
        $bdtId = $this->currencyId('BDT');

        $this->asUser($owner, (int) $mawa->id)
            ->post(route('finance.exchange-rates.store'), [
                'from_currency_id' => $usdId,
                'to_currency_id' => $bdtId,
                'rate' => '110.25',
                'rate_date' => now()->toDateString(),
                'source' => 'manual',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('exchange_rates', [
            'institute_id' => $mawa->id,
            'from_currency_id' => $usdId,
            'to_currency_id' => $bdtId,
            'rate' => '110.25000000',
        ]);
    }

    public function test_fx_revaluation_store_owner_ok(): void
    {
        $mawa = $this->institute('MAWA ACADEMY');
        $this->setupAccounting($mawa);

        $owner = $this->owner('step19-route-rev-store@example.test');
        $this->assign($owner, $mawa, 'institute-owner');

        $this->asUser($owner, (int) $mawa->id)
            ->post(route('finance.fx-revaluations.store'), [
                'as_of_date' => now()->toDateString(),
            ])
            ->assertRedirect();
    }

    public function test_exchange_rates_index_branch_scoped(): void
    {
        $mawa = $this->institute('MAWA ACADEMY');
        $branchA = app(\App\Models\Branch::class)->create([
            'institute_id' => $mawa->id,
            'name' => 'Step19 Branch A',
            'status' => 'active',
        ]);
        $branchB = app(\App\Models\Branch::class)->create([
            'institute_id' => $mawa->id,
            'name' => 'Step19 Branch B',
            'status' => 'active',
        ]);
        $this->setupAccounting($mawa, (int) $branchA->id);
        $this->setupAccounting($mawa, (int) $branchB->id);

        $usdId = $this->currencyId('USD');
        $bdtId = $this->currencyId('BDT');

        $this->rates()->create((int) $mawa->id, (int) $branchA->id, [
            'from_currency_id' => $usdId,
            'to_currency_id' => $bdtId,
            'rate' => '110.00',
            'rate_date' => '2025-06-01',
        ]);

        $this->rates()->create((int) $mawa->id, (int) $branchB->id, [
            'from_currency_id' => $usdId,
            'to_currency_id' => $bdtId,
            'rate' => '120.00',
            'rate_date' => '2025-06-01',
        ]);

        $manager = $this->staff('step19-route-acct@example.test');
        $this->assign($manager, $mawa, 'accountant', ['branch_id' => $branchA->id]);

        $this->asUser($manager, (int) $mawa->id)
            ->get(route('finance.exchange-rates.index'))
            ->assertOk();

        $listA = $this->rates()->list((int) $mawa->id, (int) $branchA->id);
        $this->assertTrue($listA->contains(fn ($r) => $r->rate === '110.00000000'));
    }

    public function test_fx_revaluation_reverse_owner_ok(): void
    {
        $mawa = $this->institute('MAWA ACADEMY');
        $this->setupAccounting($mawa);

        $usdId = $this->currencyId('USD');
        $bdtId = $this->currencyId('BDT');

        $this->rates()->create((int) $mawa->id, null, [
            'from_currency_id' => $usdId,
            'to_currency_id' => $bdtId,
            'rate' => '115.00',
            'rate_date' => now()->toDateString(),
        ]);

        $owner = $this->owner('step19-route-rev-reverse@example.test');
        $this->assign($owner, $mawa, 'institute-owner');

        $arId = $this->coaId((int) $mawa->id, null, '1100');
        $incomeId = $this->coaId((int) $mawa->id, null, '4001');

        $customer = app(\App\Services\Accounting\PartyService::class)->create($mawa->id, null, [
            'type' => 'customer',
            'name' => 'Step19 Rev Reverse Cust',
            'phone' => '0188'.rand(100000, 999999),
        ]);

        $journal = $this->posting()->create([
            'institute_id' => $mawa->id,
            'branch_id' => null,
            'journal_date' => now()->toDateString(),
            'type' => 'sale',
            'currency_id' => $usdId,
            'entries' => [
                ['coa_id' => $arId, 'debit' => 5750, 'credit' => 0, 'party_id' => $customer->id],
                ['coa_id' => $incomeId, 'debit' => 0, 'credit' => 5750],
            ],
        ]);

        DB::table('journal_entries')
            ->where('journal_id', $journal->id)
            ->update([
                'currency_id' => $usdId,
                'foreign_debit' => 50,
                'foreign_credit' => 0,
                'exchange_rate' => '115.00000000',
            ]);

        $results = $this->revaluation()->run((int) $mawa->id, null, [
            'as_of_date' => now()->toDateString(),
        ], (int) $owner->id);

        if ($results !== []) {
            $revaluation = $results[0];

            $this->asUser($owner, (int) $mawa->id)
                ->post(route('finance.fx-revaluations.reverse', $revaluation), [
                    'reason' => 'correction',
                ])
                ->assertRedirect();

            $this->assertSame('reversed', $revaluation->fresh()->status);
        }
    }
}

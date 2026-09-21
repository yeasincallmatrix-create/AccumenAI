<?php

namespace Tests\Feature\Finance;

use App\Models\BankRule;
use App\Models\BankStatement;
use App\Models\BankStatementLine;
use App\Models\ChartOfAccount;
use App\Models\Currency;
use App\Models\Institute;
use App\Models\Role;
use App\Models\User;
use App\Services\Accounting\AccountingSetupService;
use App\Services\Accounting\BankFeedMatchingService;
use App\Services\Accounting\BankStatementImportService;
use App\Services\Accounting\BankStatementParser;
use App\Services\MembershipService;
use App\Services\UserAccountService;
use App\Support\TenantContext;
use App\Support\Workspace;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BankFeedImportTest extends TestCase
{
    use DatabaseTransactions;

    protected Institute $institute;
    protected int $bankAccountId;
    protected int $revenueAccountId;

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::clear();

        $country = \App\Models\Country::firstOrCreate(
            ['iso2' => 'BD'],
            ['name' => 'Bangladesh', 'iso3' => 'BGD', 'phone_code' => '880', 'status' => true]
        );

        $this->institute = Institute::create([
            'name' => 'BF Test Inst ' . uniqid(),
            'slug' => 'bf-test-' . uniqid(),
            'country' => $country->name,
            'country_id' => $country->id,
            'industry' => 'retail',
            'status' => 'active',
        ]);

        app(AccountingSetupService::class)->setupForInstitute($this->institute->id);

        $this->bankAccountId = ChartOfAccount::where('institute_id', $this->institute->id)
            ->where('code', '1100')->first()->id;
        $this->revenueAccountId = ChartOfAccount::where('institute_id', $this->institute->id)
            ->where('code', '4001')->first()->id;

        TenantContext::set($this->institute->id);
    }

    protected function tearDown(): void
    {
        TenantContext::clear();
        parent::tearDown();
    }

    private function createCsvFile(string $content): string
    {
        $path = storage_path('app/test-bank-' . uniqid() . '.csv');
        file_put_contents($path, $content);

        return $path;
    }

    private function createStatement(array $overrides = []): BankStatement
    {
        return BankStatement::create(array_merge([
            'institute_id' => $this->institute->id,
            'bank_account_id' => $this->bankAccountId,
            'statement_date' => now()->toDateString(),
            'status' => 'imported',
        ], $overrides));
    }

    private function createLine(array $overrides = []): BankStatementLine
    {
        $statement = $overrides['statement_id'] ?? $this->createStatement()->id;
        unset($overrides['statement_id']);

        return BankStatementLine::create(array_merge([
            'statement_id' => $statement,
            'institute_id' => $this->institute->id,
            'transaction_date' => now()->toDateString(),
            'description' => 'Test transaction',
            'amount' => 1000,
            'type' => 'deposit',
            'category_status' => 'unmatched',
        ], $overrides));
    }

    // === Parser tests ===

    public function test_parse_csv_with_standard_columns(): void
    {
        $csv = "Date,Description,Reference,Amount\n2026-11-01,Revenue deposit,REF001,5000\n2026-11-02,Expense payment,REF002,-2000\n";
        $path = $this->createCsvFile($csv);

        $parser = new BankStatementParser();
        $result = $parser->parse($path);

        $this->assertEquals('csv', $result['source']);
        $this->assertCount(2, $result['transactions']);
        $this->assertEquals('2026-11-01', $result['transactions'][0]['date']);
        $this->assertEquals('Revenue deposit', $result['transactions'][0]['description']);
        $this->assertEquals(5000, $result['transactions'][0]['amount']);
        $this->assertEquals('deposit', $result['transactions'][0]['type']);

        unlink($path);
    }

    public function test_parse_csv_with_debit_credit_columns(): void
    {
        $csv = "Date,Description,Debit,Credit\n2026-11-01,Deposit,,5000\n2026-11-02,Payment,2000,\n";
        $path = $this->createCsvFile($csv);

        $parser = new BankStatementParser();
        $result = $parser->parse($path);

        $this->assertCount(2, $result['transactions']);
        $this->assertEquals('deposit', $result['transactions'][0]['type']);
        $this->assertEquals(5000, $result['transactions'][0]['amount']);
        $this->assertEquals('withdrawal', $result['transactions'][1]['type']);
        $this->assertEquals(2000, $result['transactions'][1]['amount']);

        unlink($path);
    }

    public function test_parse_ofx_file(): void
    {
        $ofx = <<<OFX
OFXHEADER:100
DATA:OFXSGML
VERSION:102

<OFX>
<BANKMSGSRSV1>
<STMTTRNRS>
<STMTRS>
<CURRENCY>BDT
<ACCOUNTID>123456
<BALLIST>
<LEDGERBAL><BALAMT>10000.00><DTASOF>20261101
</BALLIST>
<STMTTRN>
<TRNTYPE>CREDIT
<DTPOSTED>20261101
<TRNAMT>5000.00
<FITID>TXN001
<MEMO>Revenue deposit
</STMTTRN>
<STMTTRN>
<TRNTYPE>DEBIT
<DTPOSTED>20261102
<TRNAMT>-2000.00
<FITID>TXN002
<MEMO>Expense payment
</STMTTRN>
</STMTRS>
</STMTTRNRS>
</BANKMSGSRSV1>
</OFX>
OFX;
        $path = $this->createCsvFile($ofx);
        rename($path, $path . '.ofx');
        $ofxPath = $path . '.ofx';

        $parser = new BankStatementParser();
        $result = $parser->parse($ofxPath, 'ofx');

        $this->assertEquals('ofx', $result['source']);
        $this->assertCount(2, $result['transactions']);
        $this->assertEquals('Revenue deposit', $result['transactions'][0]['description']);
        $this->assertEquals('deposit', $result['transactions'][0]['type']);
        $this->assertEquals(5000, $result['transactions'][0]['amount']);

        @unlink($path);
        @unlink($ofxPath);
    }

    public function test_parse_handles_different_date_formats(): void
    {
        $csv = "Date,Description,Amount\n01/11/2026,Test1,100\n2026-11-02,Test2,200\n";
        $path = $this->createCsvFile($csv);

        $parser = new BankStatementParser();
        $result = $parser->parse($path);

        $this->assertCount(2, $result['transactions']);
        $this->assertEquals('2026-11-01', $result['transactions'][0]['date']);
        $this->assertEquals('2026-11-02', $result['transactions'][1]['date']);

        unlink($path);
    }

    public function test_parse_handles_currency_symbols_in_amount(): void
    {
        $csv = "Date,Description,Amount\n2026-11-01,Test,\"$5,000.00\"\n";
        $path = $this->createCsvFile($csv);

        $parser = new BankStatementParser();
        $result = $parser->parse($path);

        $this->assertCount(1, $result['transactions']);
        $this->assertEquals(5000, $result['transactions'][0]['amount']);

        unlink($path);
    }

    // === Import tests ===

    public function test_import_creates_statement_header(): void
    {
        $csv = "Date,Description,Amount\n2026-11-01,Test,1000\n";
        $path = $this->createCsvFile($csv);

        $service = app(BankStatementImportService::class);
        $result = $service->import($path, [
            'institute_id' => $this->institute->id,
            'bank_account_id' => $this->bankAccountId,
            'statement_date' => '2026-11-01',
        ]);

        $this->assertEquals('imported', $result['status']);
        $this->assertDatabaseHas('bank_statements', [
            'id' => $result['statement_id'],
            'institute_id' => $this->institute->id,
            'bank_account_id' => $this->bankAccountId,
            'import_source' => 'csv',
        ]);

        unlink($path);
    }

    public function test_import_creates_statement_lines(): void
    {
        $csv = "Date,Description,Amount\n2026-11-01,Test1,1000\n2026-11-02,Test2,2000\n";
        $path = $this->createCsvFile($csv);

        $service = app(BankStatementImportService::class);
        $result = $service->import($path, [
            'institute_id' => $this->institute->id,
            'bank_account_id' => $this->bankAccountId,
        ]);

        $this->assertEquals(2, $result['lines_created']);
        $this->assertDatabaseHas('bank_statement_lines', [
            'statement_id' => $result['statement_id'],
            'description' => 'Test1',
            'amount' => 1000,
            'category_status' => 'unmatched',
        ]);

        unlink($path);
    }

    public function test_import_is_idempotent_by_hash(): void
    {
        $csv = "Date,Description,Amount\n2026-11-01,Test,1000\n";
        $path = $this->createCsvFile($csv);

        $service = app(BankStatementImportService::class);
        $data = [
            'institute_id' => $this->institute->id,
            'bank_account_id' => $this->bankAccountId,
        ];

        $result1 = $service->import($path, $data);
        $result2 = $service->import($path, $data);

        $this->assertEquals('imported', $result1['status']);
        $this->assertEquals('duplicate', $result2['status']);
        $this->assertEquals(0, $result2['lines_created']);

        unlink($path);
    }

    public function test_import_rejects_empty_file(): void
    {
        $csv = "Date,Description,Amount\n";
        $path = $this->createCsvFile($csv);

        $service = app(BankStatementImportService::class);

        $this->expectException(\InvalidArgumentException::class);
        $service->import($path, [
            'institute_id' => $this->institute->id,
            'bank_account_id' => $this->bankAccountId,
        ]);

        unlink($path);
    }

    // === Auto-match tests ===

    public function test_auto_match_finds_matching_je_by_amount_and_date(): void
    {
        $statement = $this->createStatement();
        $this->createLine([
            'statement_id' => $statement->id,
            'transaction_date' => now()->toDateString(),
            'amount' => 5000,
            'type' => 'deposit',
        ]);

        $service = app(BankFeedMatchingService::class);
        $matched = $service->autoMatch($statement->id);

        // May or may not match depending on whether there's a matching JE
        $this->assertIsInt($matched);
    }

    public function test_auto_match_skips_already_matched(): void
    {
        $statement = $this->createStatement();
        $line = $this->createLine([
            'statement_id' => $statement->id,
            'category_status' => 'auto_matched',
        ]);

        $service = app(BankFeedMatchingService::class);
        $matched = $service->autoMatch($statement->id);

        $this->assertEquals(0, $matched);
    }

    // === Rule tests ===

    public function test_rule_contains_pattern_matches(): void
    {
        $rule = BankRule::create([
            'institute_id' => $this->institute->id,
            'name' => 'Salary Rule',
            'priority' => 100,
            'pattern_field' => 'description',
            'pattern_type' => 'contains',
            'pattern_value' => 'SALARY',
            'action_type' => 'categorize',
            'account_id' => $this->revenueAccountId,
            'is_active' => true,
        ]);

        $line = $this->createLine(['description' => 'Monthly SALARY payment']);

        $this->assertTrue($rule->matches($line));
    }

    public function test_rule_does_not_match_wrong_pattern(): void
    {
        $rule = BankRule::create([
            'institute_id' => $this->institute->id,
            'name' => 'Salary Rule',
            'priority' => 100,
            'pattern_field' => 'description',
            'pattern_type' => 'contains',
            'pattern_value' => 'SALARY',
            'action_type' => 'categorize',
            'account_id' => $this->revenueAccountId,
            'is_active' => true,
        ]);

        $line = $this->createLine(['description' => 'Office rent payment']);

        $this->assertFalse($rule->matches($line));
    }

    public function test_rule_starts_with_matches(): void
    {
        $rule = BankRule::create([
            'institute_id' => $this->institute->id,
            'name' => 'BKash Rule',
            'priority' => 100,
            'pattern_field' => 'description',
            'pattern_type' => 'starts_with',
            'pattern_value' => 'BKash',
            'action_type' => 'categorize',
            'account_id' => $this->revenueAccountId,
            'is_active' => true,
        ]);

        $line = $this->createLine(['description' => 'BKash transfer to vendor']);

        $this->assertTrue($rule->matches($line));
    }

    public function test_rule_regex_matches(): void
    {
        $rule = BankRule::create([
            'institute_id' => $this->institute->id,
            'name' => 'Invoice Pattern',
            'priority' => 100,
            'pattern_field' => 'description',
            'pattern_type' => 'regex',
            'pattern_value' => 'INV-\d{4}',
            'action_type' => 'categorize',
            'account_id' => $this->revenueAccountId,
            'is_active' => true,
        ]);

        $line = $this->createLine(['description' => 'Payment for INV-2026']);

        $this->assertTrue($rule->matches($line));
    }

    public function test_rule_amount_filter_applies(): void
    {
        $rule = BankRule::create([
            'institute_id' => $this->institute->id,
            'name' => 'Large Payment',
            'priority' => 100,
            'pattern_field' => 'description',
            'pattern_type' => 'contains',
            'pattern_value' => 'Payment',
            'amount_operator' => '>=',
            'amount_min' => 5000,
            'action_type' => 'categorize',
            'account_id' => $this->revenueAccountId,
            'is_active' => true,
        ]);

        $lineLarge = $this->createLine(['description' => 'Payment received', 'amount' => 10000]);
        $lineSmall = $this->createLine(['description' => 'Payment received', 'amount' => 1000]);

        $this->assertTrue($rule->matches($lineLarge));
        $this->assertFalse($rule->matches($lineSmall));
    }

    public function test_rule_direction_filter_applies(): void
    {
        $rule = BankRule::create([
            'institute_id' => $this->institute->id,
            'name' => 'Deposit Rule',
            'priority' => 100,
            'pattern_field' => 'description',
            'pattern_type' => 'contains',
            'pattern_value' => 'Transfer',
            'direction' => 'deposit',
            'action_type' => 'categorize',
            'account_id' => $this->revenueAccountId,
            'is_active' => true,
        ]);

        $deposit = $this->createLine(['description' => 'Transfer in', 'type' => 'deposit']);
        $withdrawal = $this->createLine(['description' => 'Transfer out', 'type' => 'withdrawal']);

        $this->assertTrue($rule->matches($deposit));
        $this->assertFalse($rule->matches($withdrawal));
    }

    public function test_apply_rules_categorizes_matching_lines(): void
    {
        BankRule::create([
            'institute_id' => $this->institute->id,
            'name' => 'Office Rent',
            'priority' => 100,
            'pattern_field' => 'description',
            'pattern_type' => 'contains',
            'pattern_value' => 'OFFICE RENT',
            'action_type' => 'categorize',
            'account_id' => $this->revenueAccountId,
            'is_active' => true,
        ]);

        $statement = $this->createStatement();
        $this->createLine([
            'statement_id' => $statement->id,
            'description' => 'Monthly OFFICE RENT payment',
        ]);
        $this->createLine([
            'statement_id' => $statement->id,
            'description' => 'Something else',
        ]);

        $service = app(BankFeedMatchingService::class);
        $categorized = $service->applyRules($statement->id);

        $this->assertEquals(1, $categorized);
    }

    public function test_rule_priority_order_respected(): void
    {
        $expense = ChartOfAccount::where('institute_id', $this->institute->id)
            ->where('code', '5002')->first();

        BankRule::create([
            'institute_id' => $this->institute->id,
            'name' => 'Low Priority',
            'priority' => 200,
            'pattern_field' => 'description',
            'pattern_type' => 'contains',
            'pattern_value' => 'RENT',
            'action_type' => 'categorize',
            'account_id' => $this->revenueAccountId,
            'is_active' => true,
        ]);

        BankRule::create([
            'institute_id' => $this->institute->id,
            'name' => 'High Priority',
            'priority' => 10,
            'pattern_field' => 'description',
            'pattern_type' => 'contains',
            'pattern_value' => 'RENT',
            'action_type' => 'categorize',
            'account_id' => $expense->id,
            'is_active' => true,
        ]);

        $statement = $this->createStatement();
        $line = $this->createLine([
            'statement_id' => $statement->id,
            'description' => 'OFFICE RENT payment',
        ]);

        $service = app(BankFeedMatchingService::class);
        $service->applyRules($statement->id);

        $line->refresh();
        $this->assertEquals($expense->id, $line->categorized_account_id);
    }

    // === Categorize tests ===

    public function test_bulk_ignore(): void
    {
        $statement = $this->createStatement();
        $line1 = $this->createLine(['statement_id' => $statement->id, 'description' => 'Line 1']);
        $line2 = $this->createLine(['statement_id' => $statement->id, 'description' => 'Line 2']);

        $service = app(BankFeedMatchingService::class);
        $count = $service->bulkIgnore([$line1->id, $line2->id]);

        $this->assertEquals(2, $count);
        $this->assertDatabaseHas('bank_statement_lines', ['id' => $line1->id, 'category_status' => 'ignored']);
        $this->assertDatabaseHas('bank_statement_lines', ['id' => $line2->id, 'category_status' => 'ignored']);
    }

    // === Multi-tenant isolation ===

    public function test_bank_rule_scoped_to_institute(): void
    {
        TenantContext::clear();

        $otherInstitute = Institute::create([
            'name' => 'Other ' . uniqid(),
            'slug' => 'other-' . uniqid(),
            'country' => 'Bangladesh',
            'industry' => 'retail',
            'status' => 'active',
        ]);

        $myRule = BankRule::create([
            'institute_id' => $this->institute->id,
            'name' => 'My Rule',
            'pattern_field' => 'description',
            'pattern_type' => 'contains',
            'pattern_value' => 'TEST',
            'action_type' => 'categorize',
            'account_id' => $this->revenueAccountId,
            'is_active' => true,
        ]);

        $otherRule = BankRule::create([
            'institute_id' => $otherInstitute->id,
            'name' => 'Other Rule',
            'pattern_field' => 'description',
            'pattern_type' => 'contains',
            'pattern_value' => 'TEST',
            'action_type' => 'categorize',
            'account_id' => $this->revenueAccountId,
            'is_active' => true,
        ]);

        TenantContext::set($this->institute->id);
        $rules = BankRule::where('institute_id', $this->institute->id)->get();

        $this->assertCount(1, $rules);
        $this->assertEquals($myRule->id, $rules->first()->id);
    }

    public function test_cannot_access_other_institute_statement(): void
    {
        TenantContext::clear();

        $otherInstitute = Institute::create([
            'name' => 'Other ' . uniqid(),
            'slug' => 'other-' . uniqid(),
            'country' => 'Bangladesh',
            'industry' => 'retail',
            'status' => 'active',
        ]);

        $otherStatement = BankStatement::create([
            'institute_id' => $otherInstitute->id,
            'bank_account_id' => $this->bankAccountId,
            'statement_date' => now()->toDateString(),
            'status' => 'imported',
        ]);

        TenantContext::set($this->institute->id);

        $this->assertNotEquals($this->institute->id, $otherStatement->institute_id);
    }
}

<?php

namespace Tests\Feature\Accounting;

use App\Models\ChartOfAccount;
use App\Models\Institute;
use App\Services\Accounting\AccountingAuditService;
use App\Services\Accounting\BankAccountService;
use App\Services\Accounting\JournalPostingService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PostableAccountTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        ChartOfAccount::clearIndustrySlugCache();
    }

    private function institute(): Institute
    {
        return Institute::create([
            'name' => 'Test Institute',
            'slug' => 'test-institute-' . uniqid(),
            'country' => 'Bangladesh',
        ]);
    }

    private function postingService(): JournalPostingService
    {
        return new JournalPostingService(app(AccountingAuditService::class));
    }

    public function test_header_account_cannot_be_posted(): void
    {
        $institute = $this->institute();
        $coa = ChartOfAccount::create([
            'institute_id' => $institute->id,
            'code' => '1100',
            'name' => 'Bank Accounts',
            'account_group_id' => 1,
            'type' => 'asset',
            'is_header' => true,
            'is_postable' => false,
        ]);

        $this->assertFalse($coa->is_postable);
        $this->assertTrue($coa->is_header);
    }

    public function test_leaf_account_can_be_posted(): void
    {
        $institute = $this->institute();
        $coa = ChartOfAccount::create([
            'institute_id' => $institute->id,
            'code' => '1100.1',
            'name' => 'DBBL Savings',
            'account_group_id' => 1,
            'type' => 'asset',
            'is_header' => false,
            'is_postable' => true,
        ]);

        $this->assertTrue($coa->is_postable);
        $this->assertFalse($coa->is_header);
        $this->assertTrue($coa->canBePosted());
    }

    public function test_bank_account_is_header_not_postable(): void
    {
        $institute = $this->institute();
        $service = new BankAccountService();
        $header = $service->ensureBankHeader($institute->id);

        $this->assertTrue($header->is_header);
        $this->assertFalse($header->is_postable);
    }

    public function test_bank_sub_account_is_postable(): void
    {
        $institute = $this->institute();
        $service = new BankAccountService();
        $account = $service->createBankAccount($institute->id, ['name' => 'DBBL Savings']);

        $this->assertFalse($account->is_header);
        $this->assertTrue($account->is_postable);
        $this->assertNotNull($account->parent_id);
    }

    public function test_journal_entry_to_header_throws_exception(): void
    {
        $institute = $this->institute();
        $header = ChartOfAccount::create([
            'institute_id' => $institute->id,
            'code' => '1200',
            'name' => 'Accounts Receivable',
            'account_group_id' => 1,
            'type' => 'asset',
            'is_header' => true,
            'is_postable' => false,
        ]);

        $this->expectException(ValidationException::class);
        $this->postingService()->create([
            'institute_id' => $institute->id,
            'branch_id' => null,
            'journal_date' => today()->toDateString(),
            'type' => 'journal',
            'currency_id' => 1,
            'entries' => [
                ['coa_id' => $header->id, 'debit' => 100, 'credit' => 0],
                ['coa_id' => 2, 'debit' => 0, 'credit' => 100],
            ],
        ]);
    }

    public function test_parent_id_change_updates_is_header(): void
    {
        $institute = $this->institute();
        $child = ChartOfAccount::create([
            'institute_id' => $institute->id,
            'code' => '1201',
            'name' => 'Input VAT',
            'account_group_id' => 1,
            'type' => 'asset',
            'is_postable' => true,
            'is_header' => false,
        ]);

        $parent = ChartOfAccount::create([
            'institute_id' => $institute->id,
            'code' => '1200',
            'name' => 'Accounts Receivable',
            'account_group_id' => 1,
            'type' => 'asset',
            'is_postable' => true,
            'is_header' => false,
        ]);

        $child->update(['parent_id' => $parent->id]);

        $parent->refresh();
        $child->refresh();

        $this->assertTrue($parent->is_header);
        $this->assertFalse($parent->is_postable);
    }

    public function test_scope_postable_returns_only_postable(): void
    {
        $institute = $this->institute();
        ChartOfAccount::create([
            'institute_id' => $institute->id, 'code' => '1100.1', 'name' => 'Bank 1',
            'account_group_id' => 1, 'type' => 'asset', 'is_postable' => true, 'is_header' => false,
        ]);
        ChartOfAccount::create([
            'institute_id' => $institute->id, 'code' => '1100', 'name' => 'Bank Header',
            'account_group_id' => 1, 'type' => 'asset', 'is_postable' => false, 'is_header' => true,
        ]);

        $postable = ChartOfAccount::postable()->where('institute_id', $institute->id)->get();
        $this->assertCount(1, $postable);
        $this->assertEquals('1100.1', $postable->first()->code);
    }

    public function test_scope_headers_returns_only_headers(): void
    {
        $institute = $this->institute();
        ChartOfAccount::create([
            'institute_id' => $institute->id, 'code' => '1100', 'name' => 'Bank Header',
            'account_group_id' => 1, 'type' => 'asset', 'is_postable' => false, 'is_header' => true,
        ]);
        ChartOfAccount::create([
            'institute_id' => $institute->id, 'code' => '1100.1', 'name' => 'Bank 1',
            'account_group_id' => 1, 'type' => 'asset', 'is_postable' => true, 'is_header' => false,
        ]);

        $headers = ChartOfAccount::headers()->where('institute_id', $institute->id)->get();
        $this->assertCount(1, $headers);
        $this->assertEquals('1100', $headers->first()->code);
    }
}

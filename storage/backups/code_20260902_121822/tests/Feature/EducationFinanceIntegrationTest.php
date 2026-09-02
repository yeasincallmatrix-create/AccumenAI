<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Country;
use App\Models\Institute;
use App\Models\InstituteSetting;
use App\Models\InstituteUser;
use App\Models\Invoice;
use App\Models\Party;
use App\Models\Role;
use App\Models\Student;
use App\Services\Accounting\AccountingSetupService;
use App\Services\Accounting\InvoiceService;
use App\Services\Accounting\PartyService;
use App\Services\Accounting\ReceivablesPayablesService;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Step 34 — Education ↔ AccumenAI Core (Finance): student-only invoices are
 * automatically linked to the student's customer party so AR derives into
 * party balances — tenant-safe, idempotent, non-destructive and never able to
 * fail the invoice.
 */
class EducationFinanceIntegrationTest extends TestCase
{
    use DatabaseTransactions;

    protected string $password = 'secret12345';

    protected function setUp(): void
    {
        parent::setUp();

        TenantContext::clear();
    }

    // ------------------------------------------------------------ Fixtures

    protected function freshInstitute(string $industry = 'education'): Institute
    {
        $country = Country::withoutGlobalScopes()->firstOrCreate(
            ['iso2' => 'BD'],
            ['name' => 'Bangladesh', 'iso3' => 'BDR', 'phone_code' => '880', 'status' => true]
        );

        $institute = Institute::create([
            'name' => 'Edu Fin '.mt_rand(1000, 9999),
            'slug' => 'edu-fin-'.mt_rand(1000, 9999),
            'industry' => $industry,
            'country' => $country->name,
            'country_id' => $country->id,
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

    protected function branch(Institute $institute, string $name): Branch
    {
        return Branch::create([
            'institute_id' => $institute->id,
            'name' => $name,
            'status' => 'active',
        ]);
    }

    protected function ownerFor(Institute $institute, string $prefix): InstituteUser
    {
        return InstituteUser::create([
            'institute_id' => $institute->id,
            'branch_id' => null,
            'role_id' => Role::where('slug', 'institute-owner')->firstOrFail()->id,
            'first_name' => $prefix,
            'last_name' => 'User',
            'email' => $prefix.'-'.uniqid().'@example.test',
            'phone' => '01700'.rand(100000, 999999),
            'password_hash' => bcrypt($this->password),
            'status' => 'active',
        ]);
    }

    protected function setupAccounting(Institute $institute, ?Branch $branch = null): void
    {
        app(AccountingSetupService::class)->setupForInstitute($institute->id, $branch?->id);
    }

    protected function student(Institute $institute, string $name): Student
    {
        return Student::create([
            'institute_id' => $institute->id,
            'student_id_number' => (string) mt_rand(100000, 999999),
            'first_name' => $name,
            'last_name' => 'Student',
            'phone' => '01711'.rand(100000, 999999),
            'guardian_phone' => '01811'.rand(100000, 999999),
            'email' => strtolower(preg_replace('/[^a-z0-9]/', '', strtolower($name))).'-'.uniqid().'@example.com',
            'status' => 'active',
            'admission_date' => '2026-01-01',
        ]);
    }

    protected function invoiceFor(Institute $institute, ?Branch $branch, int $studentId, int $actorId, float $amount): Invoice
    {
        return app(InvoiceService::class)->create($institute->id, $branch?->id, [
            'student_id' => $studentId,
            'invoice_type' => 'course_fee',
            'discount' => 0,
            'items' => [
                ['description' => 'Tuition', 'amount' => $amount],
            ],
        ], $actorId);
    }

    // -------------------------------------------------------------- Tests

    public function test_student_invoice_auto_links_customer_party(): void
    {
        $institute = $this->freshInstitute('education');
        $branch = $this->branch($institute, 'Downtown');
        TenantContext::set($institute->id);
        $this->setupAccounting($institute, $branch);
        $owner = $this->ownerFor($institute, 'fin-party');
        $student = $this->student($institute, 'Fee');

        $invoice = $this->invoiceFor($institute, $branch, (int) $student->id, (int) $owner->id, 5000);

        $this->assertNotNull($invoice->party_id);
        $party = Party::withoutGlobalScopes()->find($invoice->party_id);
        $this->assertSame((int) $student->id, (int) ($party->party_meta['student_id'] ?? null));
        $this->assertSame($student->full_name, $party->name);
        $this->assertSame($branch->id, $party->branch_id);
    }

    public function test_student_party_is_reused_across_invoices(): void
    {
        $institute = $this->freshInstitute('education');
        $branch = $this->branch($institute, 'Downtown');
        TenantContext::set($institute->id);
        $this->setupAccounting($institute, $branch);
        $owner = $this->ownerFor($institute, 'fin-reuse');
        $student = $this->student($institute, 'Reuse');

        $invoice1 = $this->invoiceFor($institute, $branch, (int) $student->id, (int) $owner->id, 1000);
        $invoice2 = $this->invoiceFor($institute, $branch, (int) $student->id, (int) $owner->id, 2500);

        $this->assertSame((int) $invoice1->party_id, (int) $invoice2->party_id);
        $this->assertSame(1, Party::withoutGlobalScopes()
            ->where('institute_id', $institute->id)
            ->where('type', 'customer')
            ->count());
    }

    public function test_student_invoice_ar_derives_into_party_balance(): void
    {
        $institute = $this->freshInstitute('education');
        $branch = $this->branch($institute, 'Downtown');
        TenantContext::set($institute->id);
        $this->setupAccounting($institute, $branch);
        app(AccountingSetupService::class)->setSetting($institute->id, 'invoice_auto_post', true, $branch->id);
        $owner = $this->ownerFor($institute, 'fin-ar');
        $student = $this->student($institute, 'Ar');

        $invoice = $this->invoiceFor($institute, $branch, (int) $student->id, (int) $owner->id, 5000);
        $party = Party::withoutGlobalScopes()->find($invoice->party_id);

        $balance = app(ReceivablesPayablesService::class)->partyBalance($party);
        $this->assertSame(5000.0, $balance['receivable']);
        $this->assertSame(0.0, $balance['payable']);
    }

    public function test_student_party_is_tenant_isolated(): void
    {
        $instA = $this->freshInstitute('education');
        $branchA = $this->branch($instA, 'Branch A');
        TenantContext::set($instA->id);
        $this->setupAccounting($instA, $branchA);
        app(AccountingSetupService::class)->setSetting($instA->id, 'invoice_auto_post', true, $branchA->id);
        $ownerA = $this->ownerFor($instA, 'fin-ta');
        $studentA = $this->student($instA, 'Tenant A');
        $this->invoiceFor($instA, $branchA, (int) $studentA->id, (int) $ownerA->id, 1000);

        $instB = $this->freshInstitute('education');
        $branchB = $this->branch($instB, 'Branch B');
        TenantContext::set($instB->id);
        $this->setupAccounting($instB, $branchB);
        $ownerB = $this->ownerFor($instB, 'fin-tb');
        $studentB = $this->student($instB, 'Tenant B');
        $this->invoiceFor($instB, $branchB, (int) $studentB->id, (int) $ownerB->id, 999999);

        $this->assertSame(1, Party::withoutGlobalScopes()->where('institute_id', $instA->id)->count());
        $this->assertSame(1, Party::withoutGlobalScopes()->where('institute_id', $instB->id)->count());

        TenantContext::set($instA->id);
        $this->assertSame(1000.0, app(ReceivablesPayablesService::class)
            ->totals($instA->id, $branchA->id)['receivable']);
    }

    public function test_duplicate_student_phone_never_fails_invoice(): void
    {
        $institute = $this->freshInstitute('education');
        $branch = $this->branch($institute, 'Downtown');
        TenantContext::set($institute->id);
        $this->setupAccounting($institute, $branch);
        $owner = $this->ownerFor($institute, 'fin-dup');
        $student = $this->student($institute, 'Dup');

        // A pre-existing customer party already owns the student's phone.
        app(PartyService::class)->create($institute->id, $branch->id, [
            'type' => 'customer',
            'name' => 'Existing Party',
            'phone' => $student->phone,
        ], (int) $owner->id);

        $invoice = $this->invoiceFor($institute, $branch, (int) $student->id, (int) $owner->id, 5000);

        $this->assertNotNull($invoice->id);
        $this->assertNull($invoice->party_id, 'invoice must still be created even when the party cannot be linked');
    }

    public function test_existing_student_invoices_are_never_rewritten(): void
    {
        $institute = $this->freshInstitute('education');
        $branch = $this->branch($institute, 'Downtown');
        TenantContext::set($institute->id);
        $this->setupAccounting($institute, $branch);
        $owner = $this->ownerFor($institute, 'fin-hist');
        $student = $this->student($institute, 'Legacy');

        // A historical student-only invoice created before this integration.
        $legacy = Invoice::create([
            'institute_id' => $institute->id,
            'student_id' => $student->id,
            'party_id' => null,
            'invoice_number' => 'LEG-'.mt_rand(1000, 9999),
            'invoice_type' => 'course_fee',
            'total_amount' => 1000,
            'discount' => 0,
            'payable_amount' => 1000,
            'paid_amount' => 0,
            'due_amount' => 1000,
            'status' => 'unpaid',
        ]);

        $new = $this->invoiceFor($institute, $branch, (int) $student->id, (int) $owner->id, 2000);

        $this->assertNull($legacy->fresh()->party_id, 'historical invoice must remain untouched');
        $this->assertNotNull($new->party_id);
    }
}

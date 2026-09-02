<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Country;
use App\Models\Institute;
use App\Models\InstitutePaymentGateway;
use App\Models\InstituteSetting;
use App\Models\InstituteUser;
use App\Models\Invoice;
use App\Models\Journal;
use App\Models\OnlinePaymentAttempt;
use App\Models\Payment;
use App\Models\PaymentGateway;
use App\Models\Role;
use App\Models\Student;
use App\Services\Accounting\AccountingSetupService;
use App\Services\Accounting\InvoiceService;
use App\Services\PaymentGateway\GatewayCallbackService;
use App\Services\PaymentGateway\PaymentGatewayManager;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OnlinePaymentTest extends TestCase
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
            'ai_config' => ['enabled' => true, 'features' => ['assistant'], 'daily_limit' => 0, 'monthly_limit' => 0],
        ]);
        return $institute;
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
            'items' => [['description' => 'Tuition', 'amount' => $amount]],
        ], $actorId);
    }

    protected function seedGateway(): void
    {
        DB::table('payment_gateways')->updateOrInsert(
            ['slug' => 'mock'],
            ['name' => 'Mock Gateway', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]
        );
    }

    protected function enableGatewayFor(Institute $institute): void
    {
        $this->seedGateway();
        InstitutePaymentGateway::withoutGlobalScopes()->create([
            'institute_id' => $institute->id,
            'gateway_id' => PaymentGateway::where('slug', 'mock')->first()->id,
            'is_enabled' => true,
        ]);
    }

    protected function setupWorld(): array
    {
        $institute = $this->freshInstitute('education');
        $owner = $this->ownerFor($institute, 'op-pay');
        TenantContext::set($institute->id);
        $this->setupAccounting($institute);
        $this->enableGatewayFor($institute);
        $student = $this->student($institute, 'Pay');
        $invoice = $this->invoiceFor($institute, null, (int) $student->id, (int) $owner->id, 5000);

        return compact('institute', 'owner', 'student', 'invoice');
    }

    // ------------------------------------------------------------------
    // 1. Mock gateway is seeded
    // ------------------------------------------------------------------
    public function test_mock_gateway_is_seeded(): void
    {
        $this->seedGateway();

        $gateway = PaymentGateway::where('slug', 'mock')->first();
        $this->assertNotNull($gateway);
        $this->assertTrue((bool) $gateway->is_active);
    }

    // ------------------------------------------------------------------
    // 2. Gateway can be enabled for institute
    // ------------------------------------------------------------------
    public function test_gateway_can_be_enabled_for_institute(): void
    {
        $institute = $this->freshInstitute('education');
        $this->enableGatewayFor($institute);

        $ig = InstitutePaymentGateway::withoutGlobalScopes()
            ->where('institute_id', $institute->id)
            ->first();
        $this->assertNotNull($ig);
        $this->assertTrue((bool) $ig->is_enabled);
    }

    // ------------------------------------------------------------------
    // 3. Gateway can be disabled for institute
    // ------------------------------------------------------------------
    public function test_gateway_can_be_disabled_for_institute(): void
    {
        $institute = $this->freshInstitute('education');
        $this->enableGatewayFor($institute);

        InstitutePaymentGateway::withoutGlobalScopes()
            ->where('institute_id', $institute->id)
            ->update(['is_enabled' => false]);

        $ig = InstitutePaymentGateway::withoutGlobalScopes()
            ->where('institute_id', $institute->id)
            ->first();
        $this->assertFalse((bool) $ig->is_enabled);
    }

    // ------------------------------------------------------------------
    // 4. Initiate creates pending attempt
    // ------------------------------------------------------------------
    public function test_initiate_creates_pending_attempt(): void
    {
        $w = $this->setupWorld();

        $attempt = app(PaymentGatewayManager::class)->initiate(
            (int) $w['institute']->id,
            null,
            (int) $w['invoice']->id,
            5000.0,
            null,
            (int) $w['student']->id,
            (int) $w['owner']->id,
        );

        $this->assertInstanceOf(OnlinePaymentAttempt::class, $attempt);
        $this->assertSame(OnlinePaymentAttempt::STATUS_PENDING, $attempt->fresh()->status);
    }

    // ------------------------------------------------------------------
    // 5. Initiate sets gateway reference
    // ------------------------------------------------------------------
    public function test_initiate_sets_gateway_reference(): void
    {
        $w = $this->setupWorld();

        $attempt = app(PaymentGatewayManager::class)->initiate(
            (int) $w['institute']->id,
            null,
            (int) $w['invoice']->id,
            5000.0,
            null,
            (int) $w['student']->id,
            (int) $w['owner']->id,
        );

        $this->assertNotNull($attempt->fresh()->gateway_reference);
        $this->assertStringStartsWith('MOCK-', $attempt->fresh()->gateway_reference);
    }

    // ------------------------------------------------------------------
    // 6. Initiate rejects cancelled invoice
    // ------------------------------------------------------------------
    public function test_initiate_rejects_cancelled_invoice(): void
    {
        $w = $this->setupWorld();
        app(InvoiceService::class)->cancel($w['invoice'], (int) $w['institute']->id, (int) $w['owner']->id);

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        app(PaymentGatewayManager::class)->initiate(
            (int) $w['institute']->id,
            null,
            (int) $w['invoice']->id,
            5000.0,
            null,
            (int) $w['student']->id,
            (int) $w['owner']->id,
        );
    }

    // ------------------------------------------------------------------
    // 7. Initiate rejects paid invoice
    // ------------------------------------------------------------------
    public function test_initiate_rejects_paid_invoice(): void
    {
        $w = $this->setupWorld();

        $w['invoice']->forceFill(['status' => 'paid', 'due_amount' => 0, 'paid_amount' => 5000])->save();

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        app(PaymentGatewayManager::class)->initiate(
            (int) $w['institute']->id,
            null,
            (int) $w['invoice']->id,
            5000.0,
            null,
            (int) $w['student']->id,
            (int) $w['owner']->id,
        );
    }

    // ------------------------------------------------------------------
    // 8. Initiate rejects zero amount
    // ------------------------------------------------------------------
    public function test_initiate_rejects_zero_amount(): void
    {
        $w = $this->setupWorld();

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        app(PaymentGatewayManager::class)->initiate(
            (int) $w['institute']->id,
            null,
            (int) $w['invoice']->id,
            0.0,
            null,
            (int) $w['student']->id,
            (int) $w['owner']->id,
        );
    }

    // ------------------------------------------------------------------
    // 9. Initiate rejects amount exceeding due
    // ------------------------------------------------------------------
    public function test_initiate_rejects_amount_exceeding_due(): void
    {
        $w = $this->setupWorld();

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        app(PaymentGatewayManager::class)->initiate(
            (int) $w['institute']->id,
            null,
            (int) $w['invoice']->id,
            99999.0,
            null,
            (int) $w['student']->id,
            (int) $w['owner']->id,
        );
    }

    // ------------------------------------------------------------------
    // 10. Initiate rejects no gateway configured
    // ------------------------------------------------------------------
    public function test_initiate_rejects_no_gateway_configured(): void
    {
        $institute = $this->freshInstitute('education');
        $owner = $this->ownerFor($institute, 'op-nogw');
        TenantContext::set($institute->id);
        $this->setupAccounting($institute);
        $student = $this->student($institute, 'NoGW');
        $invoice = $this->invoiceFor($institute, null, (int) $student->id, (int) $owner->id, 1000);

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        app(PaymentGatewayManager::class)->initiate(
            (int) $institute->id,
            null,
            (int) $invoice->id,
            1000.0,
            null,
            (int) $student->id,
            (int) $owner->id,
        );
    }

    // ------------------------------------------------------------------
    // 11. Initiate rejects inactive gateway
    // ------------------------------------------------------------------
    public function test_initiate_rejects_inactive_gateway(): void
    {
        $institute = $this->freshInstitute('education');
        $owner = $this->ownerFor($institute, 'op-inact');
        TenantContext::set($institute->id);
        $this->setupAccounting($institute);
        $this->seedGateway();

        PaymentGateway::where('slug', 'mock')->update(['is_active' => false]);

        InstitutePaymentGateway::withoutGlobalScopes()->create([
            'institute_id' => $institute->id,
            'gateway_id' => PaymentGateway::where('slug', 'mock')->first()->id,
            'is_enabled' => true,
        ]);

        $student = $this->student($institute, 'InactiveGW');
        $invoice = $this->invoiceFor($institute, null, (int) $student->id, (int) $owner->id, 1000);

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        app(PaymentGatewayManager::class)->initiate(
            (int) $institute->id,
            null,
            (int) $invoice->id,
            1000.0,
            null,
            (int) $student->id,
            (int) $owner->id,
        );
    }

    // ------------------------------------------------------------------
    // 12. Successful webhook records payment
    // ------------------------------------------------------------------
    public function test_successful_webhook_records_payment(): void
    {
        $w = $this->setupWorld();
        $attempt = app(PaymentGatewayManager::class)->initiate(
            (int) $w['institute']->id,
            null,
            (int) $w['invoice']->id,
            5000.0,
            null,
            (int) $w['student']->id,
            (int) $w['owner']->id,
        );

        $callback = app(GatewayCallbackService::class);
        $attempt = $callback->handleCallback('mock', [
            'attempt_id' => $attempt->id,
            'outcome' => 'success',
            'gateway_reference' => $attempt->gateway_reference,
        ], null);

        $this->assertSame(OnlinePaymentAttempt::STATUS_COMPLETED, $attempt->status);

        $payment = Payment::withoutGlobalScopes()
            ->where('institute_id', $w['institute']->id)
            ->where('invoice_id', $w['invoice']->id)
            ->first();
        $this->assertNotNull($payment);
        $this->assertSame('online', $payment->payment_method);
    }

    // ------------------------------------------------------------------
    // 13. Successful webhook creates receipt journal
    // ------------------------------------------------------------------
    public function test_successful_webhook_creates_receipt_journal(): void
    {
        $w = $this->setupWorld();
        $attempt = app(PaymentGatewayManager::class)->initiate(
            (int) $w['institute']->id,
            null,
            (int) $w['invoice']->id,
            5000.0,
            null,
            (int) $w['student']->id,
            (int) $w['owner']->id,
        );

        $callback = app(GatewayCallbackService::class);
        $attempt = $callback->handleCallback('mock', [
            'attempt_id' => $attempt->id,
            'outcome' => 'success',
            'gateway_reference' => $attempt->gateway_reference,
        ], null);

        $this->assertNotNull($attempt->fresh()->payment_id);
        $this->assertNotNull($attempt->fresh()->journal_id);

        $journal = Journal::withoutGlobalScopes()->find($attempt->fresh()->journal_id);
        $this->assertNotNull($journal);
    }

    // ------------------------------------------------------------------
    // 14. Successful webhook updates invoice status to paid
    // ------------------------------------------------------------------
    public function test_successful_webhook_updates_invoice_status(): void
    {
        $w = $this->setupWorld();
        $attempt = app(PaymentGatewayManager::class)->initiate(
            (int) $w['institute']->id,
            null,
            (int) $w['invoice']->id,
            5000.0,
            null,
            (int) $w['student']->id,
            (int) $w['owner']->id,
        );

        $callback = app(GatewayCallbackService::class);
        $callback->handleCallback('mock', [
            'attempt_id' => $attempt->id,
            'outcome' => 'success',
            'gateway_reference' => $attempt->gateway_reference,
        ], null);

        $invoice = $w['invoice']->fresh();
        $this->assertSame('paid', $invoice->status);
    }

    // ------------------------------------------------------------------
    // 15. Failed webhook does not record payment
    // ------------------------------------------------------------------
    public function test_failed_webhook_does_not_record_payment(): void
    {
        $w = $this->setupWorld();
        $attempt = app(PaymentGatewayManager::class)->initiate(
            (int) $w['institute']->id,
            null,
            (int) $w['invoice']->id,
            5000.0,
            null,
            (int) $w['student']->id,
            (int) $w['owner']->id,
        );

        $callback = app(GatewayCallbackService::class);
        $callback->handleCallback('mock', [
            'attempt_id' => $attempt->id,
            'outcome' => 'failure',
            'gateway_reference' => $attempt->gateway_reference,
            'failure_reason' => 'Insufficient funds.',
        ], null);

        $this->assertNull(Payment::withoutGlobalScopes()
            ->where('institute_id', $w['institute']->id)
            ->where('invoice_id', $w['invoice']->id)
            ->first());
    }

    // ------------------------------------------------------------------
    // 16. Failed webhook sets failure reason
    // ------------------------------------------------------------------
    public function test_failed_webhook_sets_failure_reason(): void
    {
        $w = $this->setupWorld();
        $attempt = app(PaymentGatewayManager::class)->initiate(
            (int) $w['institute']->id,
            null,
            (int) $w['invoice']->id,
            5000.0,
            null,
            (int) $w['student']->id,
            (int) $w['owner']->id,
        );

        $callback = app(GatewayCallbackService::class);
        $attempt = $callback->handleCallback('mock', [
            'attempt_id' => $attempt->id,
            'outcome' => 'failure',
            'gateway_reference' => $attempt->gateway_reference,
            'failure_reason' => 'Insufficient funds.',
        ], null);

        $this->assertSame(OnlinePaymentAttempt::STATUS_FAILED, $attempt->fresh()->status);
        $this->assertSame('Insufficient funds.', $attempt->fresh()->failure_reason);
    }

    // ------------------------------------------------------------------
    // 17. Duplicate webhook is idempotent
    // ------------------------------------------------------------------
    public function test_duplicate_webhook_is_idempotent(): void
    {
        $w = $this->setupWorld();
        $attempt = app(PaymentGatewayManager::class)->initiate(
            (int) $w['institute']->id,
            null,
            (int) $w['invoice']->id,
            5000.0,
            null,
            (int) $w['student']->id,
            (int) $w['owner']->id,
        );

        $callback = app(GatewayCallbackService::class);
        $callback->handleCallback('mock', [
            'attempt_id' => $attempt->id,
            'outcome' => 'success',
            'gateway_reference' => $attempt->gateway_reference,
        ], null);

        $callback->handleCallback('mock', [
            'attempt_id' => $attempt->id,
            'outcome' => 'success',
            'gateway_reference' => $attempt->gateway_reference,
        ], null);

        $paymentCount = Payment::withoutGlobalScopes()
            ->where('institute_id', $w['institute']->id)
            ->where('invoice_id', $w['invoice']->id)
            ->count();
        $this->assertSame(1, $paymentCount);
    }

    // ------------------------------------------------------------------
    // 18. Webhook with invalid signature rejected
    // ------------------------------------------------------------------
    public function test_webhook_with_invalid_signature_rejected(): void
    {
        $this->seedGateway();
        $institute = $this->freshInstitute('education');
        TenantContext::set($institute->id);
        $this->setupAccounting($institute);

        InstitutePaymentGateway::withoutGlobalScopes()->create([
            'institute_id' => $institute->id,
            'gateway_id' => PaymentGateway::where('slug', 'mock')->first()->id,
            'is_enabled' => true,
        ]);

        $mock = $this->createMock(GatewayCallbackService::class);
        $mock->method('handleCallback')
            ->willThrowException(\Illuminate\Validation\ValidationException::withMessages([
                'signature' => 'Invalid webhook signature.',
            ]));

        $this->app->instance(GatewayCallbackService::class, $mock);

        $this->postJson(route('finance.webhook', 'mock'), [
            'attempt_id' => 999,
            'outcome' => 'success',
        ])
            ->assertStatus(422);
    }

    // ------------------------------------------------------------------
    // 19. Webhook with missing attempt rejected
    // ------------------------------------------------------------------
    public function test_webhook_with_missing_attempt_rejected(): void
    {
        $w = $this->setupWorld();

        $callback = app(GatewayCallbackService::class);

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        $callback->handleCallback('mock', [
            'attempt_id' => 999999,
            'outcome' => 'success',
        ], null);
    }

    // ------------------------------------------------------------------
    // 20. Attempt tracks student_id
    // ------------------------------------------------------------------
    public function test_attempt_tracks_student_id(): void
    {
        $w = $this->setupWorld();
        $attempt = app(PaymentGatewayManager::class)->initiate(
            (int) $w['institute']->id,
            null,
            (int) $w['invoice']->id,
            5000.0,
            null,
            (int) $w['student']->id,
            (int) $w['owner']->id,
        );

        $this->assertSame((int) $w['student']->id, (int) $attempt->fresh()->student_id);
    }

    // ------------------------------------------------------------------
    // 21. Attempt tracks invoice_id
    // ------------------------------------------------------------------
    public function test_attempt_tracks_invoice_id(): void
    {
        $w = $this->setupWorld();
        $attempt = app(PaymentGatewayManager::class)->initiate(
            (int) $w['institute']->id,
            null,
            (int) $w['invoice']->id,
            5000.0,
            null,
            (int) $w['student']->id,
            (int) $w['owner']->id,
        );

        $this->assertSame((int) $w['invoice']->id, (int) $attempt->fresh()->invoice_id);
    }

    // ------------------------------------------------------------------
    // 22. Attempt tracks amount
    // ------------------------------------------------------------------
    public function test_attempt_tracks_amount(): void
    {
        $w = $this->setupWorld();
        $attempt = app(PaymentGatewayManager::class)->initiate(
            (int) $w['institute']->id,
            null,
            (int) $w['invoice']->id,
            2500.0,
            null,
            (int) $w['student']->id,
            (int) $w['owner']->id,
        );

        $this->assertSame(2500.0, (float) $attempt->fresh()->amount);
    }

    // ------------------------------------------------------------------
    // 23. Attempt tracks timestamps
    // ------------------------------------------------------------------
    public function test_attempt_tracks_timestamps(): void
    {
        $w = $this->setupWorld();
        $attempt = app(PaymentGatewayManager::class)->initiate(
            (int) $w['institute']->id,
            null,
            (int) $w['invoice']->id,
            5000.0,
            null,
            (int) $w['student']->id,
            (int) $w['owner']->id,
        );

        $this->assertNotNull($attempt->fresh()->initiated_at);

        $callback = app(GatewayCallbackService::class);
        $callback->handleCallback('mock', [
            'attempt_id' => $attempt->id,
            'outcome' => 'success',
            'gateway_reference' => $attempt->gateway_reference,
        ], null);

        $this->assertNotNull($attempt->fresh()->completed_at);
    }

    // ------------------------------------------------------------------
    // 24. Payment method is online
    // ------------------------------------------------------------------
    public function test_payment_method_is_online(): void
    {
        $w = $this->setupWorld();
        $attempt = app(PaymentGatewayManager::class)->initiate(
            (int) $w['institute']->id,
            null,
            (int) $w['invoice']->id,
            5000.0,
            null,
            (int) $w['student']->id,
            (int) $w['owner']->id,
        );

        app(GatewayCallbackService::class)->handleCallback('mock', [
            'attempt_id' => $attempt->id,
            'outcome' => 'success',
            'gateway_reference' => $attempt->gateway_reference,
        ], null);

        $payment = Payment::withoutGlobalScopes()
            ->where('institute_id', $w['institute']->id)
            ->first();
        $this->assertSame('online', $payment->payment_method);
    }

    // ------------------------------------------------------------------
    // 25. Payment transaction_id matches gateway_reference
    // ------------------------------------------------------------------
    public function test_payment_transaction_id_matches_gateway_reference(): void
    {
        $w = $this->setupWorld();
        $attempt = app(PaymentGatewayManager::class)->initiate(
            (int) $w['institute']->id,
            null,
            (int) $w['invoice']->id,
            5000.0,
            null,
            (int) $w['student']->id,
            (int) $w['owner']->id,
        );

        app(GatewayCallbackService::class)->handleCallback('mock', [
            'attempt_id' => $attempt->id,
            'outcome' => 'success',
            'gateway_reference' => $attempt->gateway_reference,
        ], null);

        $payment = Payment::withoutGlobalScopes()
            ->where('institute_id', $w['institute']->id)
            ->first();
        $this->assertSame($attempt->gateway_reference, $payment->transaction_id);
    }

    // ------------------------------------------------------------------
    // 26. Webhook gateway response stored
    // ------------------------------------------------------------------
    public function test_webhook_gateway_response_stored(): void
    {
        $w = $this->setupWorld();
        $attempt = app(PaymentGatewayManager::class)->initiate(
            (int) $w['institute']->id,
            null,
            (int) $w['invoice']->id,
            5000.0,
            null,
            (int) $w['student']->id,
            (int) $w['owner']->id,
        );

        app(GatewayCallbackService::class)->handleCallback('mock', [
            'attempt_id' => $attempt->id,
            'outcome' => 'success',
            'gateway_reference' => $attempt->gateway_reference,
            'extra' => 'data',
        ], null);

        $attempt->refresh();
        $this->assertNotNull($attempt->gateway_response);
        $this->assertSame('data', $attempt->gateway_response['extra'] ?? null);
    }

    // ------------------------------------------------------------------
    // 27. Successful payment updates invoice paid_amount
    // ------------------------------------------------------------------
    public function test_successful_payment_updates_invoice_paid_amount(): void
    {
        $w = $this->setupWorld();
        $attempt = app(PaymentGatewayManager::class)->initiate(
            (int) $w['institute']->id,
            null,
            (int) $w['invoice']->id,
            5000.0,
            null,
            (int) $w['student']->id,
            (int) $w['owner']->id,
        );

        app(GatewayCallbackService::class)->handleCallback('mock', [
            'attempt_id' => $attempt->id,
            'outcome' => 'success',
            'gateway_reference' => $attempt->gateway_reference,
        ], null);

        $invoice = $w['invoice']->fresh();
        $this->assertSame(5000.0, (float) $invoice->paid_amount);
    }

    // ------------------------------------------------------------------
    // 28. Successful payment updates invoice due_amount
    // ------------------------------------------------------------------
    public function test_successful_payment_updates_invoice_due_amount(): void
    {
        $w = $this->setupWorld();
        $attempt = app(PaymentGatewayManager::class)->initiate(
            (int) $w['institute']->id,
            null,
            (int) $w['invoice']->id,
            5000.0,
            null,
            (int) $w['student']->id,
            (int) $w['owner']->id,
        );

        app(GatewayCallbackService::class)->handleCallback('mock', [
            'attempt_id' => $attempt->id,
            'outcome' => 'success',
            'gateway_reference' => $attempt->gateway_reference,
        ], null);

        $invoice = $w['invoice']->fresh();
        $this->assertSame(0.0, (float) $invoice->due_amount);
    }

    // ------------------------------------------------------------------
    // 29. Partial payment keeps invoice partial
    // ------------------------------------------------------------------
    public function test_partial_payment_keeps_invoice_partial(): void
    {
        $w = $this->setupWorld();
        $attempt = app(PaymentGatewayManager::class)->initiate(
            (int) $w['institute']->id,
            null,
            (int) $w['invoice']->id,
            2000.0,
            null,
            (int) $w['student']->id,
            (int) $w['owner']->id,
        );

        app(GatewayCallbackService::class)->handleCallback('mock', [
            'attempt_id' => $attempt->id,
            'outcome' => 'success',
            'gateway_reference' => $attempt->gateway_reference,
        ], null);

        $invoice = $w['invoice']->fresh();
        $this->assertSame('partial', $invoice->status);
        $this->assertSame(2000.0, (float) $invoice->paid_amount);
        $this->assertSame(3000.0, (float) $invoice->due_amount);
    }

    // ------------------------------------------------------------------
    // 30. Institute cannot access other institute attempt
    // ------------------------------------------------------------------
    public function test_institute_cannot_access_other_institute_gateway(): void
    {
        $w = $this->setupWorld();
        $instB = $this->freshInstitute('education');
        $ownerB = $this->ownerFor($instB, 'op-cross');
        $this->setupAccounting($instB);

        TenantContext::set($w['institute']->id);
        $attempt = app(PaymentGatewayManager::class)->initiate(
            (int) $w['institute']->id,
            null,
            (int) $w['invoice']->id,
            5000.0,
            null,
            (int) $w['student']->id,
            (int) $w['owner']->id,
        );

        TenantContext::set($instB->id);
        $this->actingAs($ownerB, 'institute_user')
            ->getJson(route('online-payments.status', $attempt->id))
            ->assertStatus(404);
    }

    // ------------------------------------------------------------------
    // 31. Gateway config route requires permission
    // ------------------------------------------------------------------
    public function test_gateway_config_route_requires_permission(): void
    {
        $institute = $this->freshInstitute('education');
        $this->setupAccounting($institute);
        $this->enableGatewayFor($institute);

        $teacher = InstituteUser::create([
            'institute_id' => $institute->id,
            'branch_id' => null,
            'role_id' => Role::where('slug', 'teacher')->firstOrFail()->id,
            'first_name' => 'NoPerm',
            'last_name' => 'Teacher',
            'email' => 'noperm-'.uniqid().'@example.test',
            'phone' => '01700'.rand(100000, 999999),
            'password_hash' => bcrypt($this->password),
            'status' => 'active',
        ]);

        TenantContext::set($institute->id);

        $this->actingAs($teacher, 'institute_user')
            ->getJson(route('finance.online-payments.gateways'))
            ->assertForbidden();
    }

    // ------------------------------------------------------------------
    // 32. Payment initiation route requires permission
    // ------------------------------------------------------------------
    public function test_payment_initiation_route_requires_permission(): void
    {
        $w = $this->setupWorld();

        $teacher = InstituteUser::create([
            'institute_id' => $w['institute']->id,
            'branch_id' => null,
            'role_id' => Role::where('slug', 'teacher')->firstOrFail()->id,
            'first_name' => 'NoPermPay',
            'last_name' => 'Teacher',
            'email' => 'nopermpay-'.uniqid().'@example.test',
            'phone' => '01700'.rand(100000, 999999),
            'password_hash' => bcrypt($this->password),
            'status' => 'active',
        ]);

        TenantContext::set($w['institute']->id);

        $this->actingAs($teacher, 'institute_user')
            ->getJson(route('online-payments.initiate'))
            ->assertForbidden();
    }

    // ------------------------------------------------------------------
    // 33. Rate limiting on payment initiation
    // ------------------------------------------------------------------
    public function test_rate_limiting_on_payment_initiation(): void
    {
        $w = $this->setupWorld();

        for ($i = 0; $i < 10; $i++) {
            $attempt = app(PaymentGatewayManager::class)->initiate(
                (int) $w['institute']->id,
                null,
                (int) $w['invoice']->id,
                1.0,
                null,
                (int) $w['student']->id,
                (int) $w['owner']->id,
            );
        }

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        app(PaymentGatewayManager::class)->initiate(
            (int) $w['institute']->id,
            null,
            (int) $w['invoice']->id,
            1.0,
            null,
            (int) $w['student']->id,
            (int) $w['owner']->id,
        );
    }

    // ------------------------------------------------------------------
    // 34. Webhook returns JSON response
    // ------------------------------------------------------------------
    public function test_webhook_returns_json_response(): void
    {
        $w = $this->setupWorld();
        $attempt = app(PaymentGatewayManager::class)->initiate(
            (int) $w['institute']->id,
            null,
            (int) $w['invoice']->id,
            5000.0,
            null,
            (int) $w['student']->id,
            (int) $w['owner']->id,
        );

        $response = $this->postJson(route('finance.webhook', 'mock'), [
            'attempt_id' => $attempt->id,
            'outcome' => 'success',
            'gateway_reference' => $attempt->gateway_reference,
        ]);

        $response->assertOk()
            ->assertJsonStructure(['status', 'attempt_id', 'attempt_status']);
    }

    // ------------------------------------------------------------------
    // 35. Idempotency key is stored
    // ------------------------------------------------------------------
    public function test_idempotency_key_is_stored(): void
    {
        $w = $this->setupWorld();
        $idempotencyKey = 'idem-'.uniqid();

        $attempt = app(PaymentGatewayManager::class)->initiate(
            (int) $w['institute']->id,
            null,
            (int) $w['invoice']->id,
            5000.0,
            null,
            (int) $w['student']->id,
            (int) $w['owner']->id,
            $idempotencyKey,
        );

        $this->assertSame($idempotencyKey, $attempt->fresh()->idempotency_key);
    }
}

<?php

namespace Tests\Feature\Medical;

use App\Models\Country;
use App\Models\FiscalYear;
use App\Models\Institute;
use App\Models\InstituteFeatureOverride;
use App\Models\Journal;
use App\Models\Medical\Patient;
use App\Models\Medical\Payment;
use App\Models\Membership;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use App\Services\Medical\BillingService;
use App\Services\ModuleAccessService;
use App\Support\Workspace;
use Database\Seeders\FeatureRegistrySeeder;
use Database\Seeders\MedicalSubModuleSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * Gap 1 fix: receipt journals must carry ref_type='medical_invoice' +
 * ref_id so InstituteSettingController::hasMedicalJournalsInFiscalYear()
 * sees cash-mode receipts. Before the fix receipts were created with
 * ref_type=NULL, so a cash-only tenant could switch revenue_mode mid-year.
 */
class ReceiptJournalRefTypeTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $country = Country::firstOrCreate(
            ['iso2' => 'BD'],
            ['name' => 'Bangladesh', 'iso3' => 'BGD', 'phone_code' => '880', 'status' => true]
        );

        $this->institute = Institute::create([
            'name' => 'Receipt RefType Hospital '.uniqid(),
            'slug' => 'receipt-reftype-hospital-'.uniqid(),
            'country' => $country->name,
            'country_id' => $country->id,
            'industry' => 'healthcare',
            'sub_industry' => 'hospital',
            'status' => 'active',
        ]);

        $this->owner = User::factory()->create([
            'account_type' => 'owner',
            'email_verified_at' => now(),
            'status' => 'active',
        ]);
        Membership::create([
            'user_id' => $this->owner->id,
            'institution_id' => $this->institute->id,
            'role_id' => Role::where('slug', 'institute-owner')->value('id'),
            'status' => 'active',
        ]);

        (new MedicalSubModuleSeeder)->run();
        (new FeatureRegistrySeeder)->run();
        app(ModuleAccessService::class)->enableModule($this->institute, 'medical');
        app(ModuleAccessService::class)->enableModule($this->institute, 'medical.billing');
        app(ModuleAccessService::class)->enableModule($this->institute, 'finance');
        InstituteFeatureOverride::updateOrCreate(
            ['institute_id' => $this->institute->id, 'feature_key' => 'medical.billing'],
            ['enabled' => true]
        );

        $this->patient = Patient::create([
            'institute_id' => $this->institute->id,
            'mr_number' => 'MR-'.uniqid(),
            'first_name' => 'Test',
            'last_name' => 'Patient',
            'phone' => '017'.rand(10000000, 99999999),
            'gender' => 'male',
            'date_of_birth' => '1990-01-01',
        ]);

        FiscalYear::firstOrCreate(
            ['institute_id' => $this->institute->id, 'branch_id' => null, 'is_current' => true],
            [
                'name' => 'FY '.date('Y'),
                'start_date' => date('Y-01-01'),
                'end_date' => date('Y-12-31'),
                'status' => 'open',
            ]
        );

        Setting::set("medical.billing.revenue_mode.{$this->institute->id}", 'accrual');
        Setting::set("medical.billing.auto_post.{$this->institute->id}", '1');

        $code = Artisan::call('medical:provision-coa', [
            '--institute' => (string) $this->institute->id,
            '--execute' => true,
        ]);
        $this->assertSame(0, $code, 'CoA provisioning must succeed');

        $this->billing = app(BillingService::class);

        $this->actingAs($this->owner, 'web');
        Workspace::set($this->institute->id);
    }

    public function test_receipt_journal_has_ref_type_medical_invoice(): void
    {
        // Accrual: one invoice produces a sale journal AND (on payment) a
        // receipt journal — both must be stamped with the invoice linkage.
        $invoice = $this->billing->createInvoice($this->patient, 'opd', [
            ['description' => 'Care', 'amount' => 900, 'quantity' => 1, 'discount' => 0],
        ]);
        $this->assertNotNull($invoice->journal_id);

        $result = $this->billing->processPayment($invoice->fresh(), 900.0, 'cash', 'REF-RT1');
        $this->assertTrue($result['success'], $result['message']);

        $payment = Payment::where('invoice_id', $invoice->id)->firstOrFail();
        $this->assertNotNull($payment->journal_id);

        $receipt = Journal::find($payment->journal_id);
        $this->assertSame('receipt', $receipt->type);
        $this->assertSame('medical_invoice', $receipt->ref_type, 'receipt must carry ref_type medical_invoice');
        $this->assertSame((int) $invoice->id, (int) $receipt->ref_id, 'receipt must point at its invoice');

        // The sale keeps the same stamp, as separate rows on one invoice.
        $sale = Journal::find($invoice->journal_id);
        $this->assertSame('medical_invoice', $sale->ref_type);
        $this->assertSame((int) $invoice->id, (int) $sale->ref_id);
        $this->assertNotSame($sale->id, $receipt->id);

        $stamped = Journal::where('institute_id', $this->institute->id)
            ->where('ref_type', 'medical_invoice')
            ->where('ref_id', $invoice->id)
            ->pluck('id')
            ->all();
        sort($stamped);
        $expected = [(int) $sale->id, (int) $receipt->id];
        sort($expected);
        $this->assertSame($expected, $stamped);
    }

    public function test_fiscal_year_guard_sees_cash_mode_receipts(): void
    {
        // Cash-mode tenant: invoices create NO sale journals — the only
        // medical journals in the current FY are the three receipts.
        Setting::set("medical.billing.revenue_mode.{$this->institute->id}", 'cash');

        foreach ([300, 400, 500] as $i => $amount) {
            $invoice = $this->billing->createInvoice($this->patient, 'opd', [
                ['description' => 'Care '.$i, 'amount' => $amount, 'quantity' => 1, 'discount' => 0],
            ]);
            $this->assertNull($invoice->journal_id, 'cash mode creates no sale journal');

            $result = $this->billing->processPayment($invoice->fresh(), (float) $amount, 'cash', 'REF-CASH-'.$i);
            $this->assertTrue($result['success'], $result['message']);

            $payment = Payment::where('invoice_id', $invoice->id)->firstOrFail();
            $this->assertNotNull($payment->journal_id, 'each payment must produce a receipt journal');
        }

        $stampedReceipts = Journal::where('institute_id', $this->institute->id)
            ->where('type', 'receipt')
            ->where('ref_type', 'medical_invoice')
            ->count();
        $this->assertSame(3, $stampedReceipts, 'all cash-mode receipts must be stamped for the FY guard');

        // Switch cash -> accrual: blocked because receipts exist in the
        // current fiscal year.
        $this->put(route('settings.medical-billing.update'), [
            'revenue_mode' => 'accrual',
            'auto_post' => 1,
        ])->assertSessionHasErrors('revenue_mode');

        $errors = session('errors')->get('revenue_mode');
        $this->assertStringContainsString('fiscal year', strtolower(implode(' ', $errors)));

        // Nothing was written.
        $this->assertSame(
            'cash',
            Setting::get("medical.billing.revenue_mode.{$this->institute->id}")
        );
    }
}

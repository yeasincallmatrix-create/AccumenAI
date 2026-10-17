<?php

namespace Tests\Feature\Medical;

use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\Institute;
use App\Models\Journal;
use App\Models\Medical\Appointment;
use App\Models\Medical\Doctor;
use App\Models\Medical\Patient;
use App\Models\Medical\QueueAuditLog;
use App\Models\Membership;
use App\Models\Role;
use App\Models\User;
use App\Services\Accounting\TenantCoaSeederService;
use App\Services\Medical\MedicalFeePostingService;
use App\Support\Workspace;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Phase B (Option A) — OPD consultation fee → receipt journal.
 *
 * collectFee's terminal `complete` action posts
 *   Dr 1000.1 Cash (net) + Dr 4000.5 Discount Allowed (if any)
 *   Cr 4300.1 Consultation Fees (gross)
 * via MedicalFeePostingService → JournalPostingService, inside the same
 * DB transaction as the appointment update. Idempotent through
 * appointments.journal_id. No medical_invoice is created (Option A);
 * VAT does not apply to the net OPD fee.
 *
 * Phase C1 (two-stage posting) is covered by the service-level tests at the
 * bottom: advance receipt (Dr cash / Cr 2400.3 unearned), income
 * recognition (Dr 2400.3 / Cr 4300.1), direct collection (Dr cash+discount
 * / Cr 4300.1 in one journal), plus idempotency and the branch-null COA
 * fallback. The five controller tests are Phase B and stay untouched.
 */
class OpdFeeJournalTest extends TestCase
{
    use DatabaseTransactions;

    private Institute $institute;

    private User $owner;

    private Doctor $doctorProfile;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ValidateCsrfToken::class);

        $this->institute = Institute::create([
            'name' => 'OPD Fee Journal Hospital',
            'slug' => 'opd-fee-journal-'.uniqid(),
            'industry' => 'healthcare',
            'sub_industry' => 'hospital',
            'country' => 'Bangladesh',
            'status' => 'active',
        ]);

        // Full accounting stack for the tenant: healthcare CoA children
        // (1000.1 / 4300.1 / 4000.5 …) + fiscal year + open periods, which
        // JournalPostingService requires. Same provisioning path production
        // onboarding uses.
        app(TenantCoaSeederService::class)->seedFullProvisioning($this->institute->id);

        $this->owner = User::factory()->create([
            'account_type' => 'owner',
            'email_verified_at' => now(),
            'status' => 'active',
        ]);

        $roleId = Role::where('slug', 'institute-owner')->value('id');
        Membership::create([
            'user_id' => $this->owner->id,
            'institution_id' => $this->institute->id,
            'role_id' => $roleId,
            'status' => 'active',
        ]);

        $doctorUser = User::factory()->create([
            'account_type' => 'staff',
            'email_verified_at' => now(),
            'status' => 'active',
        ]);

        $this->doctorProfile = Doctor::create([
            'institute_id' => $this->institute->id,
            'user_id' => $doctorUser->id,
            'registration_number' => 'REG-'.strtoupper(uniqid()),
            'first_visit_fee' => 500,
        ]);

        $this->actingAs($this->owner, 'web');
        Workspace::set($this->institute->id);
    }

    private function checkedInAppointment(?int $branchId = null): Appointment
    {
        $patient = Patient::create([
            'institute_id' => $this->institute->id,
            'mr_number' => 'MR-'.uniqid(),
            'first_name' => 'OPD',
            'last_name' => 'Patient',
            'date_of_birth' => '1992-04-12',
            'gender' => 'male',
            'phone' => '01'.str_pad((string) random_int(100000000, 999999999), 9, '0', STR_PAD_LEFT),
        ]);

        return Appointment::create([
            'institute_id' => $this->institute->id,
            'branch_id' => $branchId,
            'patient_id' => $patient->id,
            'doctor_id' => $this->doctorProfile->user_id,
            'appointment_date' => today()->format('Y-m-d'),
            'appointment_time' => '10:00',
            'serial_number' => 1,
            'status' => 'checked_in',
        ]);
    }

    /** @return array<string, float> coa code => amount for the journal's entries */
    private function journalEntryTotals(Journal $journal): array
    {
        $totals = [];

        foreach ($journal->entries()->get() as $entry) {
            $code = ChartOfAccount::withoutGlobalScope('institute')
                ->whereKey($entry->coa_id)
                ->value('code');
            $amount = (float) $entry->debit > 0 ? (float) $entry->debit : (float) $entry->credit;
            $totals[$code] = ($totals[$code] ?? 0) + $amount;
        }

        return $totals;
    }

    public function test_opd_fee_collection_posts_receipt_journal(): void
    {
        $appointment = $this->checkedInAppointment();

        $this->post(route('medical.appointments.collect-fee', $appointment), [
            'action' => 'complete',
        ])->assertSessionHasNoErrors();

        $journals = Journal::where('institute_id', $this->institute->id)
            ->where('type', 'receipt')
            ->get();
        $this->assertCount(1, $journals);

        $journal = $journals->first();
        $this->assertSame('posted', $journal->status);

        $totals = $this->journalEntryTotals($journal);
        $this->assertSame(500.0, $totals['1000.1']);
        $this->assertSame(500.0, $totals['4300.1']);
        $this->assertArrayNotHasKey('4000.5', $totals);

        $appointment->refresh();
        $this->assertNotNull($appointment->journal_id);
        $this->assertSame($journal->id, (int) $appointment->journal_id);
        $this->assertSame('checked_in', $appointment->status);
        $this->assertSame(500.0, (float) $appointment->fee_collected_amount);
    }

    public function test_opd_fee_with_discount_posts_contra_entry(): void
    {
        $appointment = $this->checkedInAppointment();

        $this->post(route('medical.appointments.collect-fee', $appointment), [
            'action' => 'complete',
            'discount_type' => 'flat',
            'discount_value' => 50,
        ])->assertSessionHasNoErrors();

        $journal = Journal::where('institute_id', $this->institute->id)
            ->where('type', 'receipt')
            ->sole();

        $totals = $this->journalEntryTotals($journal);
        $this->assertSame(450.0, $totals['1000.1']);
        $this->assertSame(50.0, $totals['4000.5']);
        $this->assertSame(500.0, $totals['4300.1']);

        // Dr = Cr.
        $sumDebit = (float) $journal->entries()->sum('debit');
        $sumCredit = (float) $journal->entries()->sum('credit');
        $this->assertEqualsWithDelta(500.0, $sumDebit, 0.001);
        $this->assertEqualsWithDelta($sumDebit, $sumCredit, 0.001);

        $this->assertSame($journal->id, (int) $appointment->fresh()->journal_id);
    }

    public function test_opd_fee_is_idempotent(): void
    {
        $appointment = $this->checkedInAppointment();

        $this->post(route('medical.appointments.collect-fee', $appointment), [
            'action' => 'complete',
        ])->assertSessionHasNoErrors();

        $journalId = (int) $appointment->fresh()->journal_id;
        $this->assertNotSame(0, $journalId);

        // Force the row back into an active-queue state so the second POST
        // reaches the already-paid branch (the journal_id guard, not just
        // the status guard, must prevent the double post).
        $appointment->fresh()->forceFill(['status' => 'checked_in'])->save();

        $this->post(route('medical.appointments.collect-fee', $appointment), [
            'action' => 'complete',
        ])->assertSessionHasNoErrors();

        $this->assertSame(
            1,
            Journal::where('institute_id', $this->institute->id)->where('type', 'receipt')->count()
        );
        $this->assertSame($journalId, (int) $appointment->fresh()->journal_id);
        $this->assertSame('checked_in', $appointment->fresh()->status);
    }

    public function test_opd_fee_rolls_back_when_account_missing(): void
    {
        $appointment = $this->checkedInAppointment();

        // Remove the income account so posting must fail mid-transaction.
        ChartOfAccount::where('institute_id', $this->institute->id)
            ->where('code', '4300.1')
            ->delete();

        $this->post(route('medical.appointments.collect-fee', $appointment), [
            'action' => 'complete',
        ])->assertSessionHasErrors('fee');

        $appointment->refresh();
        $this->assertNull($appointment->fee_collected_at);
        $this->assertNull($appointment->journal_id);
        $this->assertSame('checked_in', $appointment->status);
        $this->assertSame(
            0,
            Journal::where('institute_id', $this->institute->id)->where('type', 'receipt')->count()
        );
        $this->assertSame(
            0,
            QueueAuditLog::where('appointment_id', $appointment->id)->count()
        );
    }

    public function test_opd_fee_does_not_post_when_gross_is_zero(): void
    {
        $this->doctorProfile->update(['first_visit_fee' => 0]);
        $appointment = $this->checkedInAppointment();

        $this->post(route('medical.appointments.collect-fee', $appointment), [
            'action' => 'complete',
        ])->assertSessionHasNoErrors();

        $appointment->refresh();
        $this->assertNotNull($appointment->fee_collected_at);
        $this->assertSame('checked_in', $appointment->status);
        $this->assertNull($appointment->journal_id);
        $this->assertSame(
            0,
            Journal::where('institute_id', $this->institute->id)->where('type', 'receipt')->count()
        );
    }

    public function test_advance_receipt_posts_cash_and_unearned_liability(): void
    {
        $appointment = $this->checkedInAppointment();

        $journal = app(MedicalFeePostingService::class)
            ->postAdvanceReceipt($appointment, 500.0, 0.0, $this->owner->id);

        $this->assertNotNull($journal);
        $this->assertSame('posted', $journal->status);

        $totals = $this->journalEntryTotals($journal);
        $this->assertSame(500.0, $totals['1000.1']);
        $this->assertSame(500.0, $totals['2400.3']);
        $this->assertArrayNotHasKey('4000.5', $totals);
        $this->assertArrayNotHasKey('4300.1', $totals);

        $appointment->refresh();
        $this->assertSame($journal->id, (int) $appointment->fee_receipt_journal_id);
        $this->assertNull($appointment->fee_recognition_journal_id);
        $this->assertNull($appointment->journal_id);
    }

    public function test_advance_receipt_discount_posts_contra_entry(): void
    {
        $appointment = $this->checkedInAppointment();

        $journal = app(MedicalFeePostingService::class)
            ->postAdvanceReceipt($appointment, 500.0, 50.0, $this->owner->id);

        $this->assertNotNull($journal);

        $totals = $this->journalEntryTotals($journal);
        $this->assertSame(450.0, $totals['1000.1']);
        $this->assertSame(50.0, $totals['4000.5']);
        $this->assertSame(500.0, $totals['2400.3']);

        $sumDebit = (float) $journal->entries()->sum('debit');
        $sumCredit = (float) $journal->entries()->sum('credit');
        $this->assertEqualsWithDelta(500.0, $sumDebit, 0.001);
        $this->assertEqualsWithDelta($sumDebit, $sumCredit, 0.001);
    }

    public function test_income_recognition_releases_liability_to_revenue(): void
    {
        $appointment = $this->checkedInAppointment();
        $service = app(MedicalFeePostingService::class);

        $receipt = $service->postAdvanceReceipt($appointment, 500.0, 0.0, $this->owner->id);
        $this->assertNotNull($receipt);

        $journal = $service->postIncomeRecognition($appointment, 500.0, $this->owner->id);

        $this->assertNotNull($journal);
        $this->assertSame('posted', $journal->status);
        $this->assertNotSame($receipt->id, $journal->id);

        $totals = $this->journalEntryTotals($journal);
        $this->assertSame(500.0, $totals['2400.3']);
        $this->assertSame(500.0, $totals['4300.1']);
        $this->assertArrayNotHasKey('1000.1', $totals);

        $appointment->refresh();
        $this->assertSame($receipt->id, (int) $appointment->fee_receipt_journal_id);
        $this->assertSame($journal->id, (int) $appointment->fee_recognition_journal_id);
    }

    public function test_income_recognition_requires_prior_advance_receipt(): void
    {
        $appointment = $this->checkedInAppointment();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Recognition requires a prior advance receipt journal.');

        app(MedicalFeePostingService::class)
            ->postIncomeRecognition($appointment, 500.0, $this->owner->id);
    }

    public function test_direct_collection_posts_cash_and_revenue_in_one_journal(): void
    {
        $appointment = $this->checkedInAppointment();

        $journal = app(MedicalFeePostingService::class)
            ->postDirectCollection($appointment, 500.0, 50.0, $this->owner->id);

        $this->assertNotNull($journal);
        $this->assertSame('posted', $journal->status);

        $totals = $this->journalEntryTotals($journal);
        $this->assertSame(450.0, $totals['1000.1']);
        $this->assertSame(50.0, $totals['4000.5']);
        $this->assertSame(500.0, $totals['4300.1']);
        $this->assertArrayNotHasKey('2400.3', $totals);

        $appointment->refresh();
        $this->assertSame($journal->id, (int) $appointment->fee_receipt_journal_id);
        $this->assertNull($appointment->fee_recognition_journal_id);
    }

    public function test_two_stage_and_direct_posting_are_idempotent(): void
    {
        $appointment = $this->checkedInAppointment();
        $service = app(MedicalFeePostingService::class);

        $receipt = $service->postAdvanceReceipt($appointment, 500.0, 0.0, $this->owner->id);
        $this->assertNotNull($receipt);
        $this->assertNull($service->postAdvanceReceipt($appointment, 500.0, 0.0, $this->owner->id));

        $recognition = $service->postIncomeRecognition($appointment, 500.0, $this->owner->id);
        $this->assertNotNull($recognition);
        $this->assertNull($service->postIncomeRecognition($appointment, 500.0, $this->owner->id));

        // Receipt link already present — a direct collection must not post.
        $this->assertNull($service->postDirectCollection($appointment, 500.0, 0.0, $this->owner->id));

        $this->assertSame(
            2,
            Journal::where('institute_id', $this->institute->id)->where('type', 'receipt')->count()
        );

        $appointment->refresh();
        $this->assertSame($receipt->id, (int) $appointment->fee_receipt_journal_id);
        $this->assertSame($recognition->id, (int) $appointment->fee_recognition_journal_id);
    }

    public function test_branch_scoped_account_lookup_falls_back_to_tenant_row(): void
    {
        $branch = Branch::create([
            'institute_id' => $this->institute->id,
            'name' => 'Main Campus '.uniqid(),
            'status' => 'active',
        ]);
        $appointment = $this->checkedInAppointment($branch->id);

        // Phase A medical COA install writes branch_id NULL rows; with the
        // appointment carrying a branch, the scoped lookup alone would miss.
        $this->assertSame(
            0,
            ChartOfAccount::where('institute_id', $this->institute->id)
                ->whereNotNull('branch_id')
                ->count()
        );

        $service = app(MedicalFeePostingService::class);
        $receipt = $service->postAdvanceReceipt($appointment, 500.0, 0.0, $this->owner->id);
        $this->assertNotNull($receipt);
        $this->assertNotNull($service->postIncomeRecognition($appointment, 500.0, $this->owner->id));

        $totals = $this->journalEntryTotals($receipt);
        $this->assertSame(500.0, $totals['1000.1']);
        $this->assertSame(500.0, $totals['2400.3']);
    }
}

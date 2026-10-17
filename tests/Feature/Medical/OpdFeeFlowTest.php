<?php

namespace Tests\Feature\Medical;

use App\Livewire\Medical\QueueManager;
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
use App\Support\Workspace;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Phase C2 — collectFee wired to the two-stage ledger.
 *
 * Pre-visit doctor (collect_fee_before_visit=true): Start posts the advance
 * receipt (Dr Cash 1000.1 / Dr Discount 4000.5 / Cr OPD Fees Advance
 * 2400.3); Complete releases it into income (Dr 2400.3 / Cr Consultation
 * Fees 4300.1). Post-visit doctor (false): Start stays record-only; Complete
 * posts one direct-collection journal (Dr Cash + Dr Discount / Cr 4300.1).
 * Cancelling a paid appointment reverses every posted journal and detaches
 * the FKs so no cash / liability / revenue trace survives.
 */
class OpdFeeFlowTest extends TestCase
{
    use DatabaseTransactions;

    private Institute $institute;

    private User $owner;

    private User $doctorUser;

    private string $date;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ValidateCsrfToken::class);

        $this->institute = Institute::create([
            'name' => 'OPD Fee Flow Hospital',
            'slug' => 'opd-fee-flow-'.uniqid(),
            'industry' => 'healthcare',
            'sub_industry' => 'hospital',
            'country' => 'Bangladesh',
            'status' => 'active',
        ]);

        // Full accounting stack: healthcare CoA children (1000.1 / 4300.1 /
        // 2400.3 / 4000.5 …) + fiscal year + open periods.
        app(TenantCoaSeederService::class)->seedFullProvisioning($this->institute->id);

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

        $this->doctorUser = User::factory()->create([
            'account_type' => 'staff',
            'email_verified_at' => now(),
            'status' => 'active',
        ]);

        $this->actingAs($this->owner, 'web');
        Workspace::set($this->institute->id);

        $this->date = today()->format('Y-m-d');
    }

    private function createDoctor(bool $preVisit): Doctor
    {
        return Doctor::create([
            'institute_id' => $this->institute->id,
            'user_id' => $this->doctorUser->id,
            'registration_number' => 'REG-'.strtoupper(uniqid()),
            'first_visit_fee' => 500,
            'collect_fee_before_visit' => $preVisit,
        ]);
    }

    private function checkedInAppointment(): Appointment
    {
        $patient = Patient::create([
            'institute_id' => $this->institute->id,
            'mr_number' => 'MR-'.uniqid(),
            'first_name' => 'OPD',
            'last_name' => 'Flow',
            'date_of_birth' => '1992-04-12',
            'gender' => 'male',
            'phone' => '01'.str_pad((string) random_int(100000000, 999999999), 9, '0', STR_PAD_LEFT),
        ]);

        return Appointment::create([
            'institute_id' => $this->institute->id,
            'patient_id' => $patient->id,
            'doctor_id' => $this->doctorUser->id,
            'appointment_date' => $this->date,
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

    private function instituteJournals()
    {
        return Journal::where('institute_id', $this->institute->id);
    }

    public function test_pre_visit_doctor_start_posts_advance_receipt(): void
    {
        $this->createDoctor(preVisit: true);
        $appointment = $this->checkedInAppointment();

        $this->post(route('medical.appointments.collect-fee', $appointment), [
            'action' => 'start',
        ])->assertSessionHasNoErrors();

        $journal = $this->instituteJournals()->where('type', 'receipt')->sole();
        $this->assertSame('posted', $journal->status);

        $totals = $this->journalEntryTotals($journal);
        $this->assertSame(500.0, $totals['1000.1']);
        $this->assertSame(500.0, $totals['2400.3']);
        $this->assertArrayNotHasKey('4300.1', $totals);
        $this->assertArrayNotHasKey('4000.5', $totals);

        $appointment->refresh();
        $this->assertSame($journal->id, (int) $appointment->fee_receipt_journal_id);
        $this->assertNull($appointment->fee_recognition_journal_id);
        $this->assertNull($appointment->journal_id);
        $this->assertNotNull($appointment->fee_collected_at);
        $this->assertSame('checked_in', $appointment->status);
    }

    public function test_pre_visit_doctor_complete_posts_recognition(): void
    {
        $this->createDoctor(preVisit: true);
        $appointment = $this->checkedInAppointment();

        $this->post(route('medical.appointments.collect-fee', $appointment), [
            'action' => 'start',
        ])->assertSessionHasNoErrors();

        $receipt = Journal::find($appointment->fresh()->fee_receipt_journal_id);
        $this->assertNotNull($receipt);

        $this->post(route('medical.appointments.collect-fee', $appointment), [
            'action' => 'complete',
        ])->assertSessionHasNoErrors();

        $this->assertSame(2, $this->instituteJournals()->where('type', 'receipt')->count());

        $appointment->refresh();
        $recognition = Journal::find($appointment->fee_recognition_journal_id);
        $this->assertNotNull($recognition);
        $this->assertNotSame($receipt->id, $recognition->id);
        $this->assertSame('posted', $recognition->status);

        $totals = $this->journalEntryTotals($recognition);
        $this->assertSame(500.0, $totals['2400.3']);
        $this->assertSame(500.0, $totals['4300.1']);
        $this->assertArrayNotHasKey('1000.1', $totals);

        // The receipt journal is untouched — no duplicate cash leg.
        $this->assertSame('posted', $receipt->fresh()->status);
        $receiptTotals = $this->journalEntryTotals($receipt->fresh());
        $this->assertSame(500.0, $receiptTotals['1000.1']);
        $this->assertSame(500.0, $receiptTotals['2400.3']);
        $this->assertCount(2, $receipt->entries()->get());

        $this->assertSame('checked_in', $appointment->status);
    }

    public function test_post_visit_doctor_complete_posts_direct_collection(): void
    {
        $this->createDoctor(preVisit: false);
        $appointment = $this->checkedInAppointment();

        $this->post(route('medical.appointments.collect-fee', $appointment), [
            'action' => 'complete',
        ])->assertSessionHasNoErrors();

        $journal = $this->instituteJournals()->where('type', 'receipt')->sole();
        $this->assertSame('posted', $journal->status);

        $totals = $this->journalEntryTotals($journal);
        $this->assertSame(500.0, $totals['1000.1']);
        $this->assertSame(500.0, $totals['4300.1']);
        $this->assertArrayNotHasKey('2400.3', $totals);

        $appointment->refresh();
        $this->assertSame($journal->id, (int) $appointment->fee_receipt_journal_id);
        $this->assertNull($appointment->fee_recognition_journal_id);
        $this->assertSame('checked_in', $appointment->status);
    }

    public function test_pre_visit_start_only_keeps_liability_no_income(): void
    {
        $this->createDoctor(preVisit: true);
        $appointment = $this->checkedInAppointment();

        $this->post(route('medical.appointments.collect-fee', $appointment), [
            'action' => 'start',
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, $this->instituteJournals()->count());

        // No consultation-income line anywhere — the fee is unearned.
        $incomeEntries = $this->instituteJournals()
            ->get()
            ->flatMap(fn (Journal $j) => $j->entries()->get())
            ->filter(function ($entry) {
                $code = ChartOfAccount::withoutGlobalScope('institute')
                    ->whereKey($entry->coa_id)
                    ->value('code');

                return $code === '4300.1';
            });
        $this->assertCount(0, $incomeEntries);

        $receipt = Journal::find($appointment->fresh()->fee_receipt_journal_id);
        $this->assertSame(500.0, $this->journalEntryTotals($receipt)['2400.3']);
        $this->assertSame('checked_in', $appointment->fresh()->status);
    }

    public function test_cancel_after_start_reverses_receipt(): void
    {
        $this->createDoctor(preVisit: true);
        $appointment = $this->checkedInAppointment();

        $this->post(route('medical.appointments.collect-fee', $appointment), [
            'action' => 'start',
        ])->assertSessionHasNoErrors();

        $receipt = Journal::find($appointment->fresh()->fee_receipt_journal_id);
        $this->assertNotNull($receipt);
        $this->assertSame('posted', $receipt->status);

        Livewire::test(QueueManager::class, [
            'doctorUserId' => $this->doctorUser->id,
            'date' => $this->date,
            'instituteId' => $this->institute->id,
        ])
            ->call('cancel', $appointment->id)
            ->assertSet('errorMessage', '')
            ->assertDispatched('queue-updated');

        $appointment->refresh();
        $this->assertSame('cancelled', $appointment->status);
        $this->assertNull($appointment->fee_collected_at);
        $this->assertNull($appointment->fee_receipt_journal_id);
        $this->assertNull($appointment->fee_recognition_journal_id);

        $this->assertSame('reversed', $receipt->fresh()->status);

        $reversal = Journal::where('institute_id', $this->institute->id)
            ->where('reversal_of', $receipt->id)
            ->sole();
        $this->assertSame('posted', $reversal->status);

        // The reversal swaps the legs — no orphaned unearned liability.
        $reversalTotals = $this->journalEntryTotals($reversal);
        $this->assertSame(500.0, $reversalTotals['2400.3']);
        $this->assertSame(500.0, $reversalTotals['1000.1']);

        $netLiability = $this->instituteJournals()
            ->get()
            ->flatMap(fn (Journal $j) => $j->entries()->get())
            ->filter(function ($entry) {
                $code = ChartOfAccount::withoutGlobalScope('institute')
                    ->whereKey($entry->coa_id)
                    ->value('code');

                return $code === '2400.3';
            })
            ->sum(fn ($entry) => (float) $entry->credit - (float) $entry->debit);
        $this->assertSame(0.0, round($netLiability, 2));
    }

    public function test_missing_account_rolls_back_and_returns_error(): void
    {
        $this->createDoctor(preVisit: true);
        $appointment = $this->checkedInAppointment();

        ChartOfAccount::where('institute_id', $this->institute->id)
            ->where('code', '2400.3')
            ->delete();

        $this->post(route('medical.appointments.collect-fee', $appointment), [
            'action' => 'start',
        ])->assertSessionHasErrors('fee');

        $appointment->refresh();
        $this->assertNull($appointment->fee_collected_at);
        $this->assertNull($appointment->fee_receipt_journal_id);
        $this->assertNull($appointment->fee_recognition_journal_id);
        $this->assertSame('checked_in', $appointment->status);
        $this->assertSame(0, $this->instituteJournals()->count());
        $this->assertSame(
            0,
            QueueAuditLog::where('appointment_id', $appointment->id)->count()
        );
    }

    public function test_collect_fee_rx_continue_lands_on_prescription_create_without_completing_visit(): void
    {
        // Auto-continue chain: the Rx form's fee popup posts redirect_to
        // (prescriptions/create?patient_id=…&fee_appointment_id=…&rx_continue=1),
        // the controller carries doctor_id into it, and the landing page
        // renders — while the appointment stays open (payment alone never
        // completes a visit).
        $this->createDoctor(preVisit: false);
        $appointment = $this->checkedInAppointment();

        $this->post(route('medical.appointments.start', $appointment))
            ->assertSessionHasNoErrors();
        $this->assertSame('in_progress', $appointment->fresh()->status);

        $landing = route('medical.prescriptions.create', [
            'patient_id' => $appointment->patient_id,
            'fee_appointment_id' => $appointment->id,
            'rx_continue' => 1,
        ]);

        $response = $this->post(route('medical.appointments.collect-fee', $appointment), [
            'action' => 'collect',
            'redirect_to' => $landing,
        ]);
        $response->assertSessionHasNoErrors();

        // Fee on record, direct-collection journal posted…
        $appointment->refresh();
        $this->assertNotNull($appointment->fee_collected_at);
        $journal = Journal::find($appointment->fee_receipt_journal_id);
        $this->assertNotNull($journal);
        $this->assertSame('posted', $journal->status);

        // …but the visit is untouched: payment never completes it.
        $this->assertSame('in_progress', $appointment->status);
        $this->assertSame(
            1,
            $this->instituteJournals()->where('type', 'receipt')->count()
        );

        // The landing URL keeps the chain params and gains doctor_id.
        $expected = $landing.'&doctor_id='.$appointment->doctor_id;
        $response->assertRedirect($expected);
        $this->assertStringContainsString('rx_continue=1', $expected);
        $this->assertStringContainsString('patient_id='.$appointment->patient_id, $expected);
        $this->assertStringContainsString('fee_appointment_id='.$appointment->id, $expected);

        // And the chain landing page actually renders for the Rx form.
        $this->get($expected)->assertOk();
    }
}

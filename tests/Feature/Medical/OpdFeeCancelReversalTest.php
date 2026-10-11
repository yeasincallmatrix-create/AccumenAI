<?php

namespace Tests\Feature\Medical;

use App\Console\Commands\QueueRollover;
use App\Models\ChartOfAccount;
use App\Models\Institute;
use App\Models\Journal;
use App\Models\Medical\Appointment;
use App\Models\Medical\Doctor;
use App\Models\Medical\Patient;
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
 * Phase D — every cancel/destroy/rollover path reverses posted fee journals
 * and detaches the three FK columns (fee_recognition_journal_id,
 * fee_receipt_journal_id, journal_id mirror), so a cancelled visit never
 * leaves cash / liability / revenue live in the ledger.
 */
class OpdFeeCancelReversalTest extends TestCase
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
            'name' => 'OPD Cancel Reversal Hospital',
            'slug' => 'opd-cancel-reversal-'.uniqid(),
            'industry' => 'healthcare',
            'sub_industry' => 'hospital',
            'country' => 'Bangladesh',
            'status' => 'active',
        ]);

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

    private function checkedInAppointment(?string $onDate = null): Appointment
    {
        $patient = Patient::create([
            'institute_id' => $this->institute->id,
            'mr_number' => 'MR-'.uniqid(),
            'first_name' => 'OPD',
            'last_name' => 'Cancel',
            'date_of_birth' => '1992-04-12',
            'gender' => 'male',
            'phone' => '01'.str_pad((string) random_int(100000000, 999999999), 9, '0', STR_PAD_LEFT),
        ]);

        return Appointment::create([
            'institute_id' => $this->institute->id,
            'patient_id' => $patient->id,
            'doctor_id' => $this->doctorUser->id,
            'appointment_date' => $onDate ?? $this->date,
            'appointment_time' => '10:00',
            'serial_number' => 1,
            'status' => 'checked_in',
        ]);
    }

    private function startFee(Appointment $appointment): void
    {
        $this->post(route('medical.appointments.collect-fee', $appointment), [
            'action' => 'start',
        ])->assertSessionHasNoErrors();
    }

    private function completeFee(Appointment $appointment): void
    {
        $this->post(route('medical.appointments.collect-fee', $appointment), [
            'action' => 'complete',
        ])->assertSessionHasNoErrors();
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

    /** Net ledger impact (credit - debit) across all institute journals for a coa code. */
    private function netLedgerImpact(string $code): float
    {
        return round($this->instituteJournals()
            ->get()
            ->flatMap(fn (Journal $j) => $j->entries()->get())
            ->filter(function ($entry) use ($code) {
                return ChartOfAccount::withoutGlobalScope('institute')
                    ->whereKey($entry->coa_id)
                    ->value('code') === $code;
            })
            ->sum(fn ($entry) => (float) $entry->credit - (float) $entry->debit), 2);
    }

    private function assertAllFksNull(Appointment $appointment): void
    {
        $appointment->refresh();
        $this->assertNull($appointment->journal_id);
        $this->assertNull($appointment->fee_receipt_journal_id);
        $this->assertNull($appointment->fee_recognition_journal_id);
    }

    public function test_controller_cancel_reverses_receipt_and_nulls_fks(): void
    {
        $this->createDoctor(preVisit: true);
        $appointment = $this->checkedInAppointment();
        $this->startFee($appointment);

        $receipt = Journal::find($appointment->fresh()->fee_receipt_journal_id);
        $this->assertNotNull($receipt);
        $this->assertSame('posted', $receipt->status);

        $this->post(route('medical.appointments.cancel', $appointment))
            ->assertSessionHasNoErrors();

        $this->assertSame('reversed', $receipt->fresh()->status);

        $reversal = Journal::where('institute_id', $this->institute->id)
            ->where('reversal_of', $receipt->id)
            ->sole();
        $this->assertSame('posted', $reversal->status);

        $this->assertAllFksNull($appointment);
        $this->assertNull($appointment->fresh()->fee_collected_amount);
        $this->assertSame('cancelled', $appointment->fresh()->status);

        // The ledger nets to zero on cash and the advance-liability account.
        $this->assertSame(0.0, $this->netLedgerImpact('1000.1'));
        $this->assertSame(0.0, $this->netLedgerImpact('2400.3'));
    }

    public function test_destroy_reverses_both_receipt_and_recognition(): void
    {
        // Both journals exist only after Complete, which sets status
        // 'completed' — the cancel route guards active-queue rows only, so
        // the reachable path for a completed visit is destroy (admin-gated).
        $this->createDoctor(preVisit: true);
        $appointment = $this->checkedInAppointment();
        $this->startFee($appointment);
        $this->completeFee($appointment);

        $appointment->refresh();
        $receipt = Journal::find($appointment->fee_receipt_journal_id);
        $recognition = Journal::find($appointment->fee_recognition_journal_id);
        $this->assertNotNull($receipt);
        $this->assertNotNull($recognition);

        $this->delete(route('medical.appointments.destroy', $appointment))
            ->assertSessionHasNoErrors();

        $this->assertSame('reversed', $receipt->fresh()->status);
        $this->assertSame('reversed', $recognition->fresh()->status);

        $this->assertSame(1, Journal::where('institute_id', $this->institute->id)
            ->where('reversal_of', $receipt->id)->count());
        $this->assertSame(1, Journal::where('institute_id', $this->institute->id)
            ->where('reversal_of', $recognition->id)->count());

        $this->assertAllFksNull($appointment);

        // Cash, liability and income all net to zero after the two reversals.
        $this->assertSame(0.0, $this->netLedgerImpact('1000.1'));
        $this->assertSame(0.0, $this->netLedgerImpact('2400.3'));
        $this->assertSame(0.0, $this->netLedgerImpact('4300.1'));
    }

    public function test_destroy_reverses_prior_journal(): void
    {
        $this->createDoctor(preVisit: true);
        $appointment = $this->checkedInAppointment();
        $this->startFee($appointment);

        $receipt = Journal::find($appointment->fresh()->fee_receipt_journal_id);
        $this->assertNotNull($receipt);

        $this->delete(route('medical.appointments.destroy', $appointment))
            ->assertSessionHasNoErrors();

        $this->assertSame('reversed', $receipt->fresh()->status);
        $this->assertAllFksNull($appointment);

        // destroy is a status change, not a row delete (record kept).
        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'status' => 'cancelled',
        ]);
        $this->assertSame(0.0, $this->netLedgerImpact('1000.1'));
        $this->assertSame(0.0, $this->netLedgerImpact('2400.3'));
    }

    public function test_rollover_reverses_prior_journal(): void
    {
        $this->createDoctor(preVisit: true);
        $yesterday = today()->subDay()->format('Y-m-d');
        $appointment = $this->checkedInAppointment($yesterday);
        $this->startFee($appointment);

        $receipt = Journal::find($appointment->fresh()->fee_receipt_journal_id);
        $this->assertNotNull($receipt);

        $this->artisan(QueueRollover::class, [
            '--date' => $yesterday,
            '--institute' => $this->institute->id,
        ])->assertSuccessful();

        $this->assertSame('reversed', $receipt->fresh()->status);

        $reversal = Journal::where('institute_id', $this->institute->id)
            ->where('reversal_of', $receipt->id)
            ->sole();
        $this->assertSame('posted', $reversal->status);

        $this->assertAllFksNull($appointment);
        $this->assertNull($appointment->fresh()->fee_collected_at);
        $this->assertSame('scheduled', $appointment->fresh()->status);
        $this->assertSame(0.0, $this->netLedgerImpact('1000.1'));
        $this->assertSame(0.0, $this->netLedgerImpact('2400.3'));
    }

    public function test_cancel_noop_when_no_journal(): void
    {
        $this->createDoctor(preVisit: true);
        $appointment = $this->checkedInAppointment();

        $this->post(route('medical.appointments.cancel', $appointment))
            ->assertSessionHasNoErrors();

        $appointment->refresh();
        $this->assertSame('cancelled', $appointment->status);
        $this->assertNull($appointment->fee_collected_amount);
        $this->assertNull($appointment->fee_collected_at);
        $this->assertAllFksNull($appointment);
        $this->assertSame(0, $this->instituteJournals()->count());
    }

    public function test_cancel_is_idempotent_when_called_twice(): void
    {
        $this->createDoctor(preVisit: true);
        $appointment = $this->checkedInAppointment();
        $this->startFee($appointment);

        $receipt = Journal::find($appointment->fresh()->fee_receipt_journal_id);
        $this->assertNotNull($receipt);

        $this->post(route('medical.appointments.cancel', $appointment))
            ->assertSessionHasNoErrors();

        $this->assertSame('reversed', $receipt->fresh()->status);
        $reversalsAfterFirst = Journal::where('institute_id', $this->institute->id)
            ->where('reversal_of', $receipt->id)->count();
        $this->assertSame(1, $reversalsAfterFirst);

        // Second POST hits the active-queue status guard (row is cancelled)
        // and must not create another reversal or throw.
        $second = $this->post(route('medical.appointments.cancel', $appointment));
        $second->assertSessionHasErrors('queue');

        $this->assertSame(1, Journal::where('institute_id', $this->institute->id)
            ->where('reversal_of', $receipt->id)->count());
        $this->assertAllFksNull($appointment);

        // Service-level idempotency: a direct second call is a clean no-op
        // (FKs already null — reverse() is never reached).
        app(MedicalFeePostingService::class)->reverseFeeJournals(
            $appointment->fresh(),
            $this->owner->id,
            'Second call — must no-op',
        );

        $this->assertSame(1, Journal::where('institute_id', $this->institute->id)
            ->where('reversal_of', $receipt->id)->count());
    }

    public function test_cancel_does_not_orphan_legacy_journal_id_mirror(): void
    {
        // Post-visit doctor: Complete posts one direct-collection journal and
        // mirrors its id into the legacy journal_id column. A completed visit
        // is reachable through destroy (admin), not the cancel guard.
        $this->createDoctor(preVisit: false);
        $appointment = $this->checkedInAppointment();
        $this->completeFee($appointment);

        $appointment->refresh();
        $this->assertNotNull($appointment->fee_receipt_journal_id);
        $this->assertSame(
            (int) $appointment->fee_receipt_journal_id,
            (int) $appointment->journal_id
        );
        $this->assertNull($appointment->fee_recognition_journal_id);

        $receiptId = (int) $appointment->fee_receipt_journal_id;

        $this->delete(route('medical.appointments.destroy', $appointment))
            ->assertSessionHasNoErrors();

        $this->assertAllFksNull($appointment);

        // Exactly ONE reversal — the journal_id mirror must be deduped.
        $reversals = Journal::where('institute_id', $this->institute->id)
            ->where('reversal_of', $receiptId)
            ->get();
        $this->assertCount(1, $reversals);
        $this->assertSame('reversed', Journal::find($receiptId)->status);

        $this->assertSame(0.0, $this->netLedgerImpact('1000.1'));
        $this->assertSame(0.0, $this->netLedgerImpact('4300.1'));
    }
}

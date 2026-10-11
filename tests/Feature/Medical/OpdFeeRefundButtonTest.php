<?php

namespace Tests\Feature\Medical;

use App\Http\Controllers\Medical\AppointmentController;
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
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Phase F — the Refund button is a customer-facing alias of Cancel: same
 * reversal semantics through MedicalFeePostingService::reverseFeeJournals,
 * same field clears, distinct audit action (fee_refunded) and wording.
 * No partial refunds, no amount input, no schema change.
 */
class OpdFeeRefundButtonTest extends TestCase
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
            'name' => 'OPD Refund Button Hospital',
            'slug' => 'opd-refund-button-'.uniqid(),
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

    private function checkedInAppointment(): Appointment
    {
        $patient = Patient::create([
            'institute_id' => $this->institute->id,
            'mr_number' => 'MR-'.uniqid(),
            'first_name' => 'OPD',
            'last_name' => 'Refund',
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

    private function startFee(Appointment $appointment): void
    {
        $this->post(route('medical.appointments.collect-fee', $appointment), [
            'action' => 'start',
        ])->assertSessionHasNoErrors();
    }

    private function assertAllFksNull(Appointment $appointment): void
    {
        $appointment->refresh();
        $this->assertNull($appointment->journal_id);
        $this->assertNull($appointment->fee_receipt_journal_id);
        $this->assertNull($appointment->fee_recognition_journal_id);
    }

    private function netLedgerImpact(string $code): float
    {
        return round(Journal::where('institute_id', $this->institute->id)
            ->get()
            ->flatMap(fn (Journal $j) => $j->entries()->get())
            ->filter(function ($entry) use ($code) {
                return ChartOfAccount::withoutGlobalScope('institute')
                    ->whereKey($entry->coa_id)
                    ->value('code') === $code;
            })
            ->sum(fn ($entry) => (float) $entry->credit - (float) $entry->debit), 2);
    }

    public function test_refund_reverses_journals_nulls_fks_and_logs_fee_refunded(): void
    {
        $this->createDoctor(preVisit: true);
        $appointment = $this->checkedInAppointment();
        $this->startFee($appointment);

        $receipt = Journal::find($appointment->fresh()->fee_receipt_journal_id);
        $this->assertNotNull($receipt);
        $this->assertSame('posted', $receipt->status);

        $this->post(route('medical.appointments.refund', $appointment))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status');

        $statusMessage = session('status');
        $this->assertIsString($statusMessage);
        $this->assertStringContainsString('Refund', $statusMessage);

        $this->assertSame('reversed', $receipt->fresh()->status);

        $reversal = Journal::where('institute_id', $this->institute->id)
            ->where('reversal_of', $receipt->id)
            ->sole();
        $this->assertSame('posted', $reversal->status);

        $this->assertAllFksNull($appointment);
        $this->assertNull($appointment->fresh()->fee_collected_amount);
        $this->assertSame('cancelled', $appointment->fresh()->status);

        // Ledger nets to zero on cash and the advance-liability account.
        $this->assertSame(0.0, $this->netLedgerImpact('1000.1'));
        $this->assertSame(0.0, $this->netLedgerImpact('2400.3'));

        // Audit: distinct action from cancel's fee_reversed.
        $audit = QueueAuditLog::where('institute_id', $this->institute->id)
            ->where('appointment_id', $appointment->id)
            ->where('action', 'fee_refunded')
            ->sole();
        $this->assertSame(500.0, (float) $audit->amount);
        $this->assertSame(
            0,
            QueueAuditLog::where('institute_id', $this->institute->id)
                ->where('appointment_id', $appointment->id)
                ->where('action', 'fee_reversed')
                ->count()
        );
    }

    public function test_refund_on_unpaid_is_noop_without_journals(): void
    {
        $this->createDoctor(preVisit: true);
        $appointment = $this->checkedInAppointment();

        $this->post(route('medical.appointments.refund', $appointment))
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $appointment->refresh();
        $this->assertSame('cancelled', $appointment->status);
        $this->assertNull($appointment->fee_collected_amount);
        $this->assertNull($appointment->fee_collected_at);
        $this->assertAllFksNull($appointment);
        $this->assertSame(0, Journal::where('institute_id', $this->institute->id)->count());
        $this->assertSame(
            0,
            QueueAuditLog::where('institute_id', $this->institute->id)
                ->where('appointment_id', $appointment->id)
                ->count()
        );
    }

    public function test_refund_route_shares_cancel_middleware(): void
    {
        $cancel = Route::getRoutes()->getByName('medical.appointments.cancel');
        $refund = Route::getRoutes()->getByName('medical.appointments.refund');

        $this->assertNotNull($cancel);
        $this->assertNotNull($refund);
        $this->assertStringContainsString(
            AppointmentController::class,
            (string) $refund->getAction('controller')
        );
        $this->assertSame($cancel->gatherMiddleware(), $refund->gatherMiddleware());
        $this->assertContains('POST', $refund->methods());
    }

    public function test_cancel_route_still_uses_fee_reversed_audit_action(): void
    {
        // Sanity: cancel writes action='fee_reversed' (the pre-Phase-F
        // baseline; NOT 'fee_refunded', and the historically assumed
        // 'fee_cancelled' never existed) and no fee_refunded rows.
        $this->createDoctor(preVisit: true);
        $appointment = $this->checkedInAppointment();
        $this->startFee($appointment);

        $this->post(route('medical.appointments.cancel', $appointment))
            ->assertSessionHasNoErrors();

        QueueAuditLog::where('institute_id', $this->institute->id)
            ->where('appointment_id', $appointment->id)
            ->where('action', 'fee_reversed')
            ->sole();
        $this->assertSame(
            0,
            QueueAuditLog::where('institute_id', $this->institute->id)
                ->where('appointment_id', $appointment->id)
                ->where('action', 'fee_refunded')
                ->count()
        );

        $statusMessage = session('status');
        $this->assertIsString($statusMessage);
        $this->assertStringContainsString('cancelled', $statusMessage);
        $this->assertStringNotContainsString('Refund', $statusMessage);
    }
}

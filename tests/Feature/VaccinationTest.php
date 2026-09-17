<?php

namespace Tests\Feature;

use App\Models\Institute;
use App\Models\Membership;
use App\Models\ModuleRegistry;
use App\Models\Role;
use App\Models\User;
use App\Models\Medical\Patient;
use App\Models\Medical\VaccineMaster;
use App\Models\Medical\VaccinationSchedule;
use App\Models\Medical\VaccinationRecord;
use App\Models\Medical\VaccineStock;
use App\Models\Medical\NumberSequence;
use App\Services\Medical\VaccinationService;
use App\Services\Medical\NumberSequenceService;
use App\Services\ModuleAccessService;
use App\Support\Workspace;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Tests\TestCase;

class VaccinationTest extends TestCase
{
    use DatabaseTransactions;

    private Institute $institute;
    private User $owner;
    private Patient $patient;
    private VaccineMaster $vaccine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $this->institute = Institute::create([
            'name' => 'Vaccination Test Hospital',
            'slug' => 'vacc-test-' . uniqid(),
            'industry' => 'healthcare',
            'sub_industry' => 'hospital',
            'country' => 'Bangladesh',
            'status' => 'active',
        ]);

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

        $this->actingAs($this->owner, 'web');
        Workspace::set($this->institute->id);

        app(ModuleAccessService::class)->enableModule($this->institute, 'medical.vaccination');

        $this->patient = Patient::create([
            'institute_id' => $this->institute->id,
            'first_name' => 'Test',
            'last_name' => 'Patient',
            'gender' => 'male',
            'date_of_birth' => '2024-01-01',
            'phone' => '01712345678',
            'is_patient' => true,
            'mr_number' => app(NumberSequenceService::class)->next(NumberSequence::TYPE_MR, $this->institute->id),
        ]);

        $this->vaccine = VaccineMaster::create([
            'institute_id' => $this->institute->id,
            'name' => 'BCG',
            'code' => 'BCG',
            'category' => 'EPI',
            'doses_in_series' => 1,
            'route' => 'intradermal',
            'site' => 'left_arm',
            'dose_volume' => '0.05ml',
            'min_age_days' => 0,
            'is_active' => true,
        ]);
    }

    public function test_vaccine_master_crud(): void
    {
        $response = $this->get(route('medical.vaccination.vaccine-masters.index'));
        $response->assertStatus(200);

        $response = $this->get(route('medical.vaccination.vaccine-masters.create'));
        $response->assertStatus(200);

        $response = $this->post(route('medical.vaccination.vaccine-masters.store'), [
            'name' => 'OPV',
            'code' => 'OPV',
            'category' => 'EPI',
            'doses_in_series' => 3,
            'route' => 'oral',
            'site' => 'oral',
            'dose_volume' => '2 drops',
            'default_fee' => 0,
        ]);
        $response->assertRedirect();

        $vaccine = VaccineMaster::where('code', 'OPV')->where('institute_id', $this->institute->id)->first();
        $this->assertNotNull($vaccine);

        $response = $this->get(route('medical.vaccination.vaccine-masters.show', $vaccine));
        $response->assertStatus(200);

        $response = $this->get(route('medical.vaccination.vaccine-masters.edit', $vaccine));
        $response->assertStatus(200);

        $response = $this->put(route('medical.vaccination.vaccine-masters.update', $vaccine), [
            'name' => 'OPV Updated',
            'category' => 'EPI',
            'doses_in_series' => 3,
        ]);
        $response->assertRedirect();

        $vaccine->refresh();
        $this->assertEquals('OPV Updated', $vaccine->name);
    }

    public function test_schedule_crud(): void
    {
        $response = $this->get(route('medical.vaccination.schedules.index'));
        $response->assertStatus(200);

        $response = $this->get(route('medical.vaccination.schedules.create'));
        $response->assertStatus(200);

        $response = $this->post(route('medical.vaccination.schedules.store'), [
            'patient_id' => $this->patient->id,
            'vaccine_master_id' => $this->vaccine->id,
            'due_date' => now()->addDays(30)->format('Y-m-d'),
            'notes' => 'Test schedule',
        ]);
        $response->assertRedirect();

        $schedule = VaccinationSchedule::where('patient_id', $this->patient->id)
            ->where('vaccine_master_id', $this->vaccine->id)
            ->first();
        $this->assertNotNull($schedule);
        $this->assertEquals('scheduled', $schedule->status);

        $response = $this->get(route('medical.vaccination.schedules.show', $schedule));
        $response->assertStatus(200);
    }

    public function test_vaccination_administration(): void
    {
        $schedule = VaccinationSchedule::create([
            'institute_id' => $this->institute->id,
            'patient_id' => $this->patient->id,
            'vaccine_master_id' => $this->vaccine->id,
            'dose_number' => 1,
            'due_date' => today(),
            'status' => 'scheduled',
        ]);

        $response = $this->get(route('medical.vaccination.schedules.administer', $schedule));
        $response->assertStatus(200);

        $response = $this->post(route('medical.vaccination.schedules.record', $schedule), [
            'administered_date' => now()->format('Y-m-d'),
            'site' => 'left_arm',
            'route' => 'intradermal',
            'dose_volume' => '0.05ml',
            'batch_number' => 'BCG-001',
            'adverse_event' => 'none',
        ]);
        $response->assertRedirect();

        $schedule->refresh();
        $this->assertEquals('given', $schedule->status);

        $record = VaccinationRecord::where('patient_id', $this->patient->id)->first();
        $this->assertNotNull($record);
        $this->assertNotNull($record->certificate_number);
        $this->assertNotNull($record->record_number);
    }

    public function test_record_vaccination_standalone(): void
    {
        $response = $this->get(route('medical.vaccination.records.index'));
        $response->assertStatus(200);

        $response = $this->get(route('medical.vaccination.records.create'));
        $response->assertStatus(200);
    }

    public function test_stock_management(): void
    {
        $response = $this->get(route('medical.vaccination.stocks.index'));
        $response->assertStatus(200);

        $response = $this->get(route('medical.vaccination.stocks.create'));
        $response->assertStatus(200);

        $response = $this->post(route('medical.vaccination.stocks.store'), [
            'vaccine_master_id' => $this->vaccine->id,
            'batch_number' => 'BCG-BATCH-001',
            'expiry_date' => now()->addYear()->format('Y-m-d'),
            'quantity_received' => 100,
            'storage_location' => 'Cold Room A',
        ]);
        $response->assertRedirect();

        $stock = VaccineStock::where('batch_number', 'BCG-BATCH-001')->first();
        $this->assertNotNull($stock);
        $this->assertEquals(100, $stock->quantity_available);

        $response = $this->get(route('medical.vaccination.stocks.edit', $stock));
        $response->assertStatus(200);

        $response = $this->put(route('medical.vaccination.stocks.update', $stock), [
            'storage_location' => 'Cold Room B',
            'status' => 'available',
        ]);
        $response->assertRedirect();

        $stock->refresh();
        $this->assertEquals('Cold Room B', $stock->storage_location);
    }

    public function test_dashboard(): void
    {
        $response = $this->get(route('medical.vaccination.dashboard'));
        $response->assertStatus(200);
    }

    public function test_epi_schedule_generation(): void
    {
        $service = app(VaccinationService::class);
        $created = $service->generateEpiSchedule($this->institute->id, $this->patient->id, now()->subYear());

        $this->assertNotEmpty($created);
        foreach ($created as $schedule) {
            $this->assertEquals($this->patient->id, $schedule->patient_id);
            $this->assertEquals('scheduled', $schedule->status);
        }
    }

    public function test_certificate_generation(): void
    {
        $record = VaccinationRecord::create([
            'institute_id' => $this->institute->id,
            'record_number' => 'VR-TEST-001',
            'patient_id' => $this->patient->id,
            'vaccine_master_id' => $this->vaccine->id,
            'dose_number' => 1,
            'administered_date' => now(),
            'administered_at' => now(),
            'administered_by' => $this->owner->id,
            'adverse_event' => 'none',
            'fee' => 0,
            'payment_status' => 'pending',
        ]);

        $service = app(VaccinationService::class);
        $certNumber = $service->generateCertificate($record);

        $this->assertNotEmpty($certNumber);
        $this->assertStringStartsWith('VC-', $certNumber);

        $record->refresh();
        $this->assertEquals($certNumber, $record->certificate_number);
    }

    public function test_number_sequence_types(): void
    {
        $seq = app(NumberSequenceService::class);
        $vr = $seq->next(NumberSequence::TYPE_VACCINATION_RECORD, $this->institute->id);
        $this->assertStringStartsWith('VR-', $vr);

        $vc = $seq->next(NumberSequence::TYPE_VACCINATION_CERTIFICATE, $this->institute->id);
        $this->assertStringStartsWith('VC-', $vc);
    }

    public function test_vaccine_stock_decrement(): void
    {
        $stock = VaccineStock::create([
            'institute_id' => $this->institute->id,
            'vaccine_master_id' => $this->vaccine->id,
            'batch_number' => 'DEC-001',
            'expiry_date' => now()->addYear(),
            'quantity_received' => 50,
            'quantity_available' => 50,
            'quantity_used' => 0,
            'status' => 'available',
        ]);

        $schedule = VaccinationSchedule::create([
            'institute_id' => $this->institute->id,
            'patient_id' => $this->patient->id,
            'vaccine_master_id' => $this->vaccine->id,
            'dose_number' => 1,
            'due_date' => today(),
            'status' => 'scheduled',
        ]);

        $service = app(VaccinationService::class);
        $service->recordVaccination($schedule, [
            'administered_date' => now()->format('Y-m-d'),
            'batch_number' => 'DEC-001',
            'adverse_event' => 'none',
        ], $this->owner->id);

        $stock->refresh();
        $this->assertEquals(49, $stock->quantity_available);
        $this->assertEquals(1, $stock->quantity_used);
    }

    public function test_patient_scope(): void
    {
        $response = $this->get(route('medical.vaccination.schedules.index'));
        $response->assertStatus(200);

        $response = $this->get(route('medical.vaccination.records.index'));
        $response->assertStatus(200);
    }
}

<?php

namespace Tests\Feature;

use App\Models\Institute;
use App\Models\Membership;
use App\Models\Role;
use App\Models\User;
use App\Models\Medical\Ambulance;
use App\Models\Medical\AmbulanceDriver;
use App\Models\Medical\AmbulanceTrip;
use App\Models\Medical\ClinicalAuditLog;
use App\Models\Medical\NumberSequence;
use App\Models\Medical\Patient;
use App\Services\Medical\AmbulanceDispatchService;
use App\Services\Medical\NumberSequenceService;
use App\Services\ModuleAccessService;
use App\Support\Workspace;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Tests\TestCase;

class AmbulanceTest extends TestCase
{
    use DatabaseTransactions;

    private Institute $institute;
    private User $owner;
    private Patient $patient;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $this->institute = Institute::create([
            'name' => 'Ambulance Test Hospital',
            'slug' => 'amb-test-' . uniqid(),
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

        app(ModuleAccessService::class)->enableModule($this->institute, 'medical.ambulance');

        $this->patient = Patient::create([
            'institute_id' => $this->institute->id,
            'first_name' => 'Amb',
            'last_name' => 'Patient',
            'gender' => 'male',
            'date_of_birth' => '1990-01-01',
            'phone' => '01712345678',
            'is_patient' => true,
            'mr_number' => app(NumberSequenceService::class)->next(NumberSequence::TYPE_MR, $this->institute->id),
        ]);
    }

    private function makeVehicle(array $overrides = []): Ambulance
    {
        return Ambulance::create(array_merge([
            'institute_id' => $this->institute->id,
            'vehicle_number' => 'AMB-' . uniqid(),
            'type' => 'basic',
            'status' => 'available',
            'is_active' => true,
        ], $overrides));
    }

    private function makeDriver(array $overrides = []): AmbulanceDriver
    {
        return AmbulanceDriver::create(array_merge([
            'institute_id' => $this->institute->id,
            'driver_number' => 'AD-T-' . uniqid(),
            'name' => 'Test Driver',
            'phone' => '01812345678',
            'status' => 'active',
        ], $overrides));
    }

    private function makeTrip(array $overrides = []): AmbulanceTrip
    {
        return AmbulanceTrip::create(array_merge([
            'institute_id' => $this->institute->id,
            'trip_number' => 'AT-T-' . uniqid(),
            'trip_type' => 'emergency_pickup',
            'pickup_location' => 'Home',
            'dropoff_location' => 'Hospital ER',
            'requested_at' => now(),
            'status' => 'requested',
            'priority' => 'urgent',
        ], $overrides));
    }

    // ---------- Vehicle CRUD ----------

    public function test_creates_ambulance(): void
    {
        $response = $this->get(route('medical.ambulance.vehicles.index'));
        $response->assertStatus(200);

        $response = $this->get(route('medical.ambulance.vehicles.create'));
        $response->assertStatus(200);

        $response = $this->post(route('medical.ambulance.vehicles.store'), [
            'vehicle_number' => 'DHK-AMB-001',
            'type' => 'advanced_life_support',
            'make' => 'Toyota',
            'model' => 'Hiace',
            'odometer_km' => 50000,
        ]);
        $response->assertRedirect();

        $vehicle = Ambulance::where('vehicle_number', 'DHK-AMB-001')->first();
        $this->assertNotNull($vehicle);
        $this->assertEquals('available', $vehicle->status);
        $this->assertTrue($vehicle->isAvailable());

        $response = $this->get(route('medical.ambulance.vehicles.show', $vehicle));
        $response->assertStatus(200);

        $response = $this->get(route('medical.ambulance.vehicles.edit', $vehicle));
        $response->assertStatus(200);
    }

    public function test_vehicle_number_unique_per_institute(): void
    {
        $this->makeVehicle(['vehicle_number' => 'DUP-001']);

        $response = $this->post(route('medical.ambulance.vehicles.store'), [
            'vehicle_number' => 'DUP-001',
            'type' => 'basic',
        ]);
        $response->assertSessionHasErrors('vehicle_number');
    }

    public function test_updates_vehicle_status(): void
    {
        $vehicle = $this->makeVehicle();

        $response = $this->post(route('medical.ambulance.vehicles.status', $vehicle), [
            'status' => 'maintenance',
        ]);
        $response->assertRedirect();

        $this->assertEquals('maintenance', $vehicle->refresh()->status);
        $this->assertFalse($vehicle->refresh()->isAvailable());
    }

    // ---------- Drivers ----------

    public function test_creates_driver(): void
    {
        $response = $this->get(route('medical.ambulance.drivers.index'));
        $response->assertStatus(200);

        $response = $this->post(route('medical.ambulance.drivers.store'), [
            'name' => 'Karim Driver',
            'phone' => '01912345678',
            'license_number' => 'DL-12345',
            'license_type' => 'ambulance',
            'license_expiry' => now()->addYear()->format('Y-m-d'),
        ]);
        $response->assertRedirect();

        $driver = AmbulanceDriver::where('phone', '01912345678')->first();
        $this->assertNotNull($driver);
        $this->assertStringStartsWith('AD-', $driver->driver_number);
        $this->assertTrue($driver->isActive());
    }

    public function test_driver_number_unique_per_institute(): void
    {
        $seq = app(NumberSequenceService::class);
        $n1 = $seq->next(NumberSequence::TYPE_AMBULANCE_DRIVER, $this->institute->id);
        $n2 = $seq->next(NumberSequence::TYPE_AMBULANCE_DRIVER, $this->institute->id);

        $this->assertStringStartsWith('AD-', $n1);
        $this->assertNotEquals($n1, $n2);
    }

    public function test_license_expiry_detection(): void
    {
        $expiring = $this->makeDriver(['license_expiry' => now()->addDays(10)->toDateString()]);
        $valid = $this->makeDriver(['license_expiry' => now()->addYear()->toDateString()]);

        $this->assertTrue($expiring->isLicenseExpiring());
        $this->assertFalse($valid->isLicenseExpiring());
    }

    // ---------- Trip workflow ----------

    public function test_creates_trip(): void
    {
        $response = $this->get(route('medical.ambulance.trips.index'));
        $response->assertStatus(200);

        $response = $this->get(route('medical.ambulance.trips.create'));
        $response->assertStatus(200);

        $response = $this->post(route('medical.ambulance.trips.store'), [
            'trip_type' => 'emergency_pickup',
            'pickup_location' => 'Mirpur 10',
            'dropoff_location' => 'Hospital ER',
            'patient_id' => $this->patient->id,
            'priority' => 'critical',
        ]);
        $response->assertRedirect();

        $trip = AmbulanceTrip::where('patient_id', $this->patient->id)->first();
        $this->assertNotNull($trip);
        $this->assertStringStartsWith('AT-', $trip->trip_number);
        $this->assertEquals('requested', $trip->status);
    }

    public function test_trip_number_unique_per_institute(): void
    {
        $seq = app(NumberSequenceService::class);
        $n1 = $seq->next(NumberSequence::TYPE_AMBULANCE_TRIP, $this->institute->id);
        $n2 = $seq->next(NumberSequence::TYPE_AMBULANCE_TRIP, $this->institute->id);

        $this->assertStringStartsWith('AT-', $n1);
        $this->assertNotEquals($n1, $n2);
    }

    public function test_dispatches_trip_assigns_ambulance_and_driver(): void
    {
        $vehicle = $this->makeVehicle();
        $driver = $this->makeDriver();
        $trip = $this->makeTrip();

        $response = $this->post(route('medical.ambulance.trips.dispatch', $trip), [
            'ambulance_id' => $vehicle->id,
            'driver_id' => $driver->id,
        ]);
        $response->assertRedirect();

        $trip->refresh();
        $this->assertEquals('dispatched', $trip->status);
        $this->assertEquals($vehicle->id, $trip->ambulance_id);
        $this->assertEquals($driver->id, $trip->driver_id);
        $this->assertNotNull($trip->dispatched_at);
    }

    public function test_dispatch_updates_ambulance_status(): void
    {
        $vehicle = $this->makeVehicle();
        $driver = $this->makeDriver();
        $trip = $this->makeTrip();

        app(AmbulanceDispatchService::class)->dispatch($trip, $vehicle, $driver);

        $this->assertEquals('dispatched', $vehicle->refresh()->status);
    }

    public function test_status_updates_set_timestamps(): void
    {
        $vehicle = $this->makeVehicle();
        $driver = $this->makeDriver();
        $trip = $this->makeTrip();
        $service = app(AmbulanceDispatchService::class);
        $service->dispatch($trip, $vehicle, $driver);

        $this->post(route('medical.ambulance.trips.status', $trip), ['status' => 'en_route_to_pickup']);
        $this->post(route('medical.ambulance.trips.status', $trip), ['status' => 'at_pickup']);

        $trip->refresh();
        $this->assertEquals('at_pickup', $trip->status);
        $this->assertNotNull($trip->arrived_at_pickup);
        $this->assertEquals('on_trip', $vehicle->refresh()->status);
    }

    public function test_complete_trip_frees_ambulance(): void
    {
        $vehicle = $this->makeVehicle();
        $driver = $this->makeDriver();
        $trip = $this->makeTrip();
        $service = app(AmbulanceDispatchService::class);
        $service->dispatch($trip, $vehicle, $driver);
        $service->updateStatus($trip, 'completed');

        $this->assertEquals('completed', $trip->refresh()->status);
        $this->assertEquals('available', $vehicle->refresh()->status);
    }

    public function test_complete_trip_calculates_distance_and_duration(): void
    {
        $vehicle = $this->makeVehicle();
        $driver = $this->makeDriver();
        $trip = $this->makeTrip([
            'requested_at' => now()->subMinutes(45),
            'odometer_start_km' => 10000,
            'odometer_end_km' => 10025.5,
        ]);
        $service = app(AmbulanceDispatchService::class);
        $service->dispatch($trip, $vehicle, $driver);
        $service->updateStatus($trip, 'completed');

        $trip->refresh();
        $this->assertEquals(25.5, (float) $trip->distance_km);
        $this->assertGreaterThanOrEqual(44, $trip->duration_minutes);
    }

    public function test_complete_trip_calculates_total_fee(): void
    {
        $trip = $this->makeTrip([
            'base_fee' => 500,
            'distance_fee' => 300,
            'waiting_fee' => 50,
        ]);

        $this->assertEquals(850.0, $trip->calculateTotalFee());

        $service = app(AmbulanceDispatchService::class);
        $vehicle = $this->makeVehicle();
        $driver = $this->makeDriver();
        $service->dispatch($trip, $vehicle, $driver);
        $service->updateStatus($trip, 'completed');

        $this->assertEquals(850.0, (float) $trip->refresh()->total_fee);
    }

    public function test_cancel_trip_frees_ambulance(): void
    {
        $vehicle = $this->makeVehicle();
        $driver = $this->makeDriver();
        $trip = $this->makeTrip();
        app(AmbulanceDispatchService::class)->dispatch($trip, $vehicle, $driver);

        $response = $this->post(route('medical.ambulance.trips.cancel', $trip), [
            'cancellation_reason' => 'Caller cancelled',
        ]);
        $response->assertRedirect();

        $trip->refresh();
        $this->assertEquals('cancelled', $trip->status);
        $this->assertEquals('Caller cancelled', $trip->cancellation_reason);
        $this->assertEquals('available', $vehicle->refresh()->status);
    }

    public function test_find_available_ambulances(): void
    {
        $free = $this->makeVehicle(['type' => 'basic']);
        $busy = $this->makeVehicle(['type' => 'basic', 'status' => 'dispatched']);
        $this->makeVehicle(['type' => 'mobile_icu', 'is_active' => false]);

        $found = app(AmbulanceDispatchService::class)->findAvailableAmbulances($this->institute->id);

        $this->assertTrue($found->contains('id', $free->id));
        $this->assertFalse($found->contains('id', $busy->id));
        $this->assertCount(1, $found);
    }

    public function test_find_available_drivers_excludes_active_trips(): void
    {
        $free = $this->makeDriver();
        $busy = $this->makeDriver();

        $vehicle = $this->makeVehicle();
        $trip = $this->makeTrip();
        app(AmbulanceDispatchService::class)->dispatch($trip, $vehicle, $busy);

        $found = app(AmbulanceDispatchService::class)->findAvailableDrivers($this->institute->id);

        $this->assertTrue($found->contains('id', $free->id));
        $this->assertFalse($found->contains('id', $busy->id));
    }

    // ---------- Dashboard ----------

    public function test_fleet_summary_returns_correct_counts(): void
    {
        $this->makeVehicle(['status' => 'available']);
        $this->makeVehicle(['status' => 'dispatched']);
        $this->makeVehicle(['status' => 'on_trip']);
        $this->makeVehicle(['status' => 'maintenance']);

        $summary = app(AmbulanceDispatchService::class)->fleetSummary($this->institute->id);

        $this->assertEquals(4, $summary['total']);
        $this->assertEquals(1, $summary['available']);
        $this->assertEquals(1, $summary['dispatched']);
        $this->assertEquals(1, $summary['on_trip']);
        $this->assertEquals(1, $summary['maintenance']);
    }

    public function test_dispatch_board_shows_active_trips(): void
    {
        $vehicle = $this->makeVehicle();
        $driver = $this->makeDriver();
        $trip = $this->makeTrip();
        app(AmbulanceDispatchService::class)->dispatch($trip, $vehicle, $driver);

        $response = $this->get(route('medical.ambulance.dashboard'));
        $response->assertStatus(200);
        $response->assertSee($trip->trip_number);

        $response = $this->get(route('medical.ambulance.dispatch-board'));
        $response->assertStatus(200);
        $response->assertSee($trip->trip_number);
    }

    // ---------- Safety ----------

    public function test_tenant_isolation(): void
    {
        $vehicle = $this->makeVehicle(['vehicle_number' => 'HOME-001']);

        $other = Institute::create([
            'name' => 'Other Hospital',
            'slug' => 'amb-other-' . uniqid(),
            'industry' => 'healthcare',
            'sub_industry' => 'hospital',
            'country' => 'Bangladesh',
            'status' => 'active',
        ]);

        $this->assertEquals(1, Ambulance::forInstitute($this->institute->id)->count());
        $this->assertEquals(0, Ambulance::forInstitute($other->id)->count());

        $foreign = Ambulance::create([
            'institute_id' => $other->id,
            'vehicle_number' => 'FOREIGN-001',
            'type' => 'basic',
        ]);

        $this->get(route('medical.ambulance.vehicles.show', $foreign))->assertStatus(403);
        $this->assertEquals('HOME-001', $vehicle->vehicle_number);
    }

    public function test_audit_log_records_actions(): void
    {
        $this->post(route('medical.ambulance.vehicles.store'), [
            'vehicle_number' => 'AUDIT-001',
            'type' => 'basic',
        ]);
        $vehicle = Ambulance::where('vehicle_number', 'AUDIT-001')->first();
        $this->assertNotNull($vehicle);

        $this->assertTrue(ClinicalAuditLog::where('auditable_type', Ambulance::class)
            ->where('auditable_id', $vehicle->id)
            ->where('action', 'created')
            ->exists());

        $trip = $this->makeTrip();
        $driver = $this->makeDriver();
        $this->post(route('medical.ambulance.trips.dispatch', $trip), [
            'ambulance_id' => $vehicle->id,
            'driver_id' => $driver->id,
        ]);

        $this->assertTrue(ClinicalAuditLog::where('auditable_type', AmbulanceTrip::class)
            ->where('auditable_id', $trip->id)
            ->where('action', 'dispatched')
            ->exists());
    }

    public function test_permission_enforcement(): void
    {
        $viewPermission = \App\Models\Permission::firstOrCreate(
            ['slug' => 'medical.ambulance.view'],
            ['name' => 'View Ambulance', 'module' => 'medical.ambulance']
        );
        $limitedRole = Role::create([
            'name' => 'Ambulance View Only',
            'slug' => 'amb-view-only-' . uniqid(),
            'status' => 'active',
        ]);
        $limitedRole->permissions()->attach($viewPermission->id);

        $staff = User::factory()->create([
            'account_type' => 'staff',
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
        Membership::create([
            'user_id' => $staff->id,
            'institution_id' => $this->institute->id,
            'role_id' => $limitedRole->id,
            'status' => 'active',
        ]);

        $this->actingAs($staff, 'web');
        Workspace::set($this->institute->id);

        // View allowed…
        $this->get(route('medical.ambulance.dashboard'))->assertStatus(200);
        // …but fleet management denied (no fleet.manage grant)
        $this->get(route('medical.ambulance.vehicles.create'))->assertStatus(403);
    }
}

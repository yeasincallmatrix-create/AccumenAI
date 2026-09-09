<?php

namespace Tests\Feature;

use App\Models\Institute;
use App\Models\Medical\Appointment;
use App\Models\Medical\Doctor;
use App\Models\Medical\LabTest;
use App\Models\Medical\Medicine;
use App\Models\Medical\Patient;
use App\Models\Membership;
use App\Models\Role;
use App\Models\User;
use App\Support\MedicalScope;
use App\Support\Workspace;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Phase 02 — Multi-tenant security hardening: focused negative tests.
 *
 * Every test builds fixtures in TWO institutes (A = actor context,
 * B = foreign) so denial is proven to come from the tenant boundary —
 * B-side records are fully valid inside B (membership/profile ownership),
 * leaving boundary enforcement as the only possible cause of refusal.
 *
 * Same-tenant positives guard against over-blocking.
 */
class TenantSecurityTest extends TestCase
{
    use DatabaseTransactions;

    private Institute $a;

    private Institute $b;

    private User $ownerA;

    private User $doctorA;

    private User $ownerB;

    private User $outsiderB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ValidateCsrfToken::class);

        $this->a = $this->makeInstitute('Tenant Security A');
        $this->b = $this->makeInstitute('Tenant Security B');

        $this->ownerA = $this->makeOwner($this->a);
        $this->ownerB = $this->makeOwner($this->b);

        // Doctor of A (profile in A) and an outsider linked only to B.
        $this->doctorA = $this->makeUser();
        Doctor::create([
            'institute_id' => $this->a->id,
            'user_id' => $this->doctorA->id,
            'registration_number' => 'REG-A-'.strtoupper(uniqid()),
        ]);

        $this->outsiderB = $this->makeUser();
        Doctor::create([
            'institute_id' => $this->b->id,
            'user_id' => $this->outsiderB->id,
            'registration_number' => 'REG-B-'.strtoupper(uniqid()),
        ]);

        $this->actingAs($this->ownerA, 'web');
        Workspace::set($this->a->id);
    }

    private function makeInstitute(string $name): Institute
    {
        return Institute::create([
            'name' => $name,
            'slug' => \Str::slug($name).'-'.uniqid(),
            'industry' => 'healthcare',
            'sub_industry' => 'hospital',
            'country' => 'Bangladesh',
            'status' => 'active',
        ]);
    }

    private function makeUser(): User
    {
        return User::factory()->create([
            'account_type' => 'staff',
            'email_verified_at' => now(),
            'status' => 'active',
        ]);
    }

    private function makeOwner(Institute $institute): User
    {
        $owner = User::factory()->create([
            'account_type' => 'owner',
            'email_verified_at' => now(),
            'status' => 'active',
        ]);
        Membership::create([
            'user_id' => $owner->id,
            'institution_id' => $institute->id,
            'role_id' => Role::where('slug', 'institute-owner')->value('id'),
            'status' => 'active',
        ]);

        return $owner;
    }

    private function asOwner(User $owner, Institute $institute, callable $fn)
    {
        $this->actingAs($owner, 'web');
        Workspace::set($institute->id);
        try {
            return $fn();
        } finally {
            $this->actingAs($this->ownerA, 'web');
            Workspace::set($this->a->id);
        }
    }

    private function patientPayload(string $phone): array
    {
        return [
            'first_name' => 'Tenant',
            'last_name' => 'Probe',
            'date_of_birth' => '1990-05-15',
            'gender' => 'male',
            'phone' => $phone,
            'blood_group' => 'O+',
        ];
    }

    private function createPatient(string $phone): Patient
    {
        $this->post(route('medical.patients.store'), $this->patientPayload($phone))
            ->assertSessionHasNoErrors();

        // Phone is E.164-normalized on write; fetch newest in this workspace.
        return Patient::where('institute_id', MedicalScope::instituteId())->latest('id')->firstOrFail();
    }

    private function makeBPatient(): Patient
    {
        return $this->asOwner($this->ownerB, $this->b, fn () => $this->createPatient(
            '019'.str_pad((string) random_int(0, 99999999), 8, '0', STR_PAD_LEFT)
        ));
    }

    private function makeBMedicine(): Medicine
    {
        return Medicine::create([
            'institute_id' => $this->b->id,
            'code' => 'B-'.strtoupper(uniqid()),
            'generic_name' => 'Foreignmycin',
            'brand_name' => 'Foreignmycin 250',
            'category' => 'Antibiotic',
            'dosage_form' => 'Tablet',
            'strength' => '250mg',
            'unit' => 'Strip',
            'pack_size' => 10,
            'purchase_price' => 5,
            'selling_price' => 8,
            'reorder_level' => 10,
            'reorder_quantity' => 50,
            'is_active' => true,
        ]);
    }

    // 1. Cross-tenant patient lookup returns not-found, never the record.
    public function test_cross_tenant_patient_lookup_returns_not_found(): void
    {
        $foreign = $this->makeBPatient();

        $response = $this->get(route('medical.patients.lookup', ['phone' => $foreign->phone]));
        $response->assertOk();
        $this->assertFalse((bool) $response->json('found'));
        $this->assertStringNotContainsString($foreign->mr_number, (string) $response->getContent());
    }

    // 2. Cross-tenant patient search returns nothing (HTTP + scope unit).
    public function test_cross_tenant_patient_search_returns_nothing(): void
    {
        $foreign = $this->makeBPatient();
        // The search box echoes the query, so assert on the record's name
        // (never echoed) rather than the searched MR number.
        $unique = 'Foreign'.substr(strtoupper(uniqid()), 0, 6);
        $foreign->update(['first_name' => $unique]);

        $this->get(route('medical.patients.index', ['search' => $foreign->mr_number]))
            ->assertOk()
            ->assertDontSee($unique);

        $this->assertSame(
            0,
            Patient::where('institute_id', $this->a->id)->search($foreign->mr_number)->count()
        );
    }

    // 3. Doctor picker contains own doctors, never the other institute's.
    public function test_cross_tenant_doctor_excluded_from_picker(): void
    {
        $ids = MedicalScope::instituteDoctorUserIds($this->a->id);

        $this->assertContains($this->doctorA->id, $ids);
        $this->assertNotContains($this->outsiderB->id, $ids);
        $this->assertTrue(MedicalScope::isDoctorInInstitute($this->doctorA->id, $this->a->id));
        $this->assertFalse(MedicalScope::isDoctorInInstitute($this->outsiderB->id, $this->a->id));
    }

    // 4. Linking a foreign account as a doctor profile is rejected.
    public function test_cross_tenant_user_cannot_be_linked_as_doctor(): void
    {
        $this->post(route('medical.doctors.store'), [
            'user_id' => $this->outsiderB->id,
            'registration_number' => 'REG-X-'.strtoupper(uniqid()),
        ])->assertSessionHasErrors(['user_id']);

        $this->assertSame(
            0,
            Doctor::where('institute_id', $this->a->id)->where('user_id', $this->outsiderB->id)->count()
        );
    }

    // 5. Cross-tenant medicine lookup returns nothing (HTTP + scope unit).
    public function test_cross_tenant_medicine_lookup_returns_nothing(): void
    {
        $foreign = $this->makeBMedicine();

        // The search box echoes the query; assert on the brand name instead.
        $this->get(route('medical.pharmacy.medicines.index', ['search' => $foreign->code]))
            ->assertOk()
            ->assertDontSee('Foreignmycin');

        $this->assertSame(
            0,
            Medicine::where('institute_id', $this->a->id)->search($foreign->code)->count()
        );
    }

    // 6. Prescribing another institute's medicine is refused.
    public function test_cross_tenant_medicine_prescription_rejected(): void
    {
        $patient = $this->createPatient('017'.str_pad((string) random_int(0, 99999999), 8, '0', STR_PAD_LEFT));
        $foreign = $this->makeBMedicine();

        $this->post(route('medical.prescriptions.store'), [
            'patient_id' => $patient->id,
            'doctor_id' => $this->doctorA->id,
            'prescription_date' => now()->format('Y-m-d'),
            'items' => [[
                'medicine_id' => $foreign->id,
                'medicine_name' => $foreign->brand_name,
                'dosage' => '250mg',
                'frequency' => '1+0+1',
                'quantity' => 10,
            ]],
        ])->assertSessionHasErrors(['items.0.medicine_id']);

        $this->assertSame(0, \App\Models\Medical\Prescription::where('institute_id', $this->a->id)->count());
    }

    // 7. Another institute's visit history never influences fees here.
    public function test_cross_tenant_fee_history_ignored(): void
    {
        $patientA = $this->createPatient('016'.str_pad((string) random_int(0, 99999999), 8, '0', STR_PAD_LEFT));
        $patientB = $this->makeBPatient();

        // Same doctor user completes a visit in B.
        Appointment::create([
            'institute_id' => $this->b->id,
            'patient_id' => $patientB->id,
            'doctor_id' => $this->doctorA->id,
            'appointment_date' => now()->subDays(5)->format('Y-m-d'),
            'appointment_time' => '09:00',
            'status' => 'completed',
        ]);

        // A fresh appointment in A must see no history.
        $fresh = Appointment::create([
            'institute_id' => $this->a->id,
            'patient_id' => $patientA->id,
            'doctor_id' => $this->doctorA->id,
            'appointment_date' => now()->addDay()->format('Y-m-d'),
            'appointment_time' => '09:00',
            'status' => 'scheduled',
        ]);

        $this->assertNull($fresh->daysSinceLastVisit());
    }

    // 8. Booking an appointment for a foreign doctor is refused.
    public function test_cross_tenant_appointment_doctor_rejected(): void
    {
        $patient = $this->createPatient('015'.str_pad((string) random_int(0, 99999999), 8, '0', STR_PAD_LEFT));

        $this->post(route('medical.appointments.store'), [
            'patient_id' => $patient->id,
            'doctor_id' => $this->outsiderB->id,
            'appointment_date' => now()->addDay()->format('Y-m-d'),
            'appointment_time' => '09:00',
        ])->assertSessionHasErrors(['doctor_id']);

        $this->assertSame(0, Appointment::where('institute_id', $this->a->id)->count());
    }

    // 9. Ordering labs under a foreign doctor is refused.
    public function test_cross_tenant_lab_doctor_rejected(): void
    {
        $patient = $this->createPatient('014'.str_pad((string) random_int(0, 99999999), 8, '0', STR_PAD_LEFT));
        $test = LabTest::create([
            'institute_id' => $this->a->id,
            'code' => 'LT-'.strtoupper(uniqid()),
            'name' => 'Tenant Panel',
            'price' => 100,
            'is_active' => true,
        ]);

        $this->post(route('medical.lab.orders.store'), [
            'patient_id' => $patient->id,
            'doctor_id' => $this->outsiderB->id,
            'priority' => 'routine',
            'tests' => [['lab_test_id' => $test->id]],
        ])->assertSessionHasErrors(['doctor_id']);
    }

    // 10. Admitting under a foreign doctor is refused.
    public function test_cross_tenant_admission_doctor_rejected(): void
    {
        $patient = $this->createPatient('013'.str_pad((string) random_int(0, 99999999), 8, '0', STR_PAD_LEFT));

        $this->post(route('medical.admissions.store'), [
            'patient_id' => $patient->id,
            'admitting_doctor_id' => $this->outsiderB->id,
            'admission_date' => now()->format('Y-m-d'),
            'admission_time' => '10:00',
        ])->assertSessionHasErrors(['admitting_doctor_id']);
    }

    // 11. Foreign patients/admissions cannot be attached to local records.
    public function test_cross_tenant_relationship_attachment_rejected(): void
    {
        $foreignPatient = $this->makeBPatient();
        $foreignAdmission = $this->asOwner($this->ownerB, $this->b, function () use ($foreignPatient) {
            $this->post(route('medical.admissions.store'), [
                'patient_id' => $foreignPatient->id,
                'admitting_doctor_id' => $this->outsiderB->id,
                'admission_date' => now()->format('Y-m-d'),
                'admission_time' => '10:00',
            ])->assertSessionHasNoErrors();

            return \App\Models\Medical\Admission::where('institute_id', $this->b->id)->latest('id')->firstOrFail();
        });

        // Prescription in A with B's patient.
        $this->post(route('medical.prescriptions.store'), [
            'patient_id' => $foreignPatient->id,
            'doctor_id' => $this->doctorA->id,
            'prescription_date' => now()->format('Y-m-d'),
            'items' => [[
                'medicine_id' => null,
                'medicine_name' => 'Free Text',
                'dosage' => '1mg',
                'frequency' => '1+0+0',
                'quantity' => 1,
            ]],
        ])->assertSessionHasErrors(['patient_id']);

        // Invoice in A with B's admission.
        $patient = $this->createPatient('012'.str_pad((string) random_int(0, 99999999), 8, '0', STR_PAD_LEFT));
        $this->post(route('medical.billing.invoices.store'), [
            'patient_id' => $patient->id,
            'admission_id' => $foreignAdmission->id,
            'type' => 'ipd',
            'items' => [['description' => 'Bed', 'amount' => 500, 'quantity' => 1, 'discount' => 0]],
        ])->assertSessionHasErrors(['admission_id']);
    }

    // 12. Foreign record URLs are forbidden and leak no clinical content.
    public function test_cross_tenant_route_access_forbidden_without_data(): void
    {
        $foreign = $this->makeBPatient();

        $response = $this->get(route('medical.patients.show', $foreign));
        $response->assertForbidden();
        $this->assertStringNotContainsString($foreign->mr_number, (string) $response->getContent());
        $this->assertStringNotContainsString($foreign->phone, (string) $response->getContent());
    }

    // 13. Grouped search scopes never escape the tenant, even on shared keywords.
    public function test_grouped_search_scopes_never_escape_tenant(): void
    {
        $keyword = 'Escape'.substr(strtoupper(uniqid()), 0, 6);

        $this->createPatient('011'.str_pad((string) random_int(0, 99999999), 8, '0', STR_PAD_LEFT));
        Patient::where('institute_id', $this->a->id)->latest('id')->firstOrFail()
            ->update(['first_name' => $keyword]);

        $this->asOwner($this->ownerB, $this->b, function () use ($keyword) {
            $p = $this->createPatient('010'.str_pad((string) random_int(0, 99999999), 8, '0', STR_PAD_LEFT));
            $p->update(['first_name' => $keyword]);
        });

        $hits = Patient::where('institute_id', $this->a->id)->search($keyword)->get();
        $this->assertSame(1, $hits->count());
        $this->assertSame($this->a->id, (int) $hits->first()->institute_id);

        // Medicine + LabTest scopes behave identically.
        $medA = Medicine::create([
            'institute_id' => $this->a->id,
            'code' => 'A-'.strtoupper(uniqid()),
            'generic_name' => $keyword.'mycin',
            'dosage_form' => 'Tablet',
            'unit' => 'Strip',
            'pack_size' => 10,
            'purchase_price' => 1,
            'selling_price' => 2,
            'reorder_level' => 1,
            'reorder_quantity' => 5,
            'is_active' => true,
        ]);
        $this->makeBMedicine()->update(['generic_name' => $keyword.'mycin']);

        $medHits = Medicine::where('institute_id', $this->a->id)->search($keyword)->get();
        $this->assertSame(1, $medHits->count());
        $this->assertSame($medA->id, $medHits->first()->id);

        $labCode = 'LT-'.strtoupper(uniqid());
        LabTest::create([
            'institute_id' => $this->a->id, 'code' => $labCode, 'name' => $keyword.' Panel',
            'price' => 100, 'is_active' => true,
        ]);
        LabTest::create([
            'institute_id' => $this->b->id, 'code' => 'LT-'.strtoupper(uniqid()), 'name' => $keyword.' Panel',
            'price' => 100, 'is_active' => true,
        ]);
        $this->assertSame(1, LabTest::where('institute_id', $this->a->id)->search($keyword)->count());
    }

    // Positive: same-institute booking + prescribing keep working.
    public function test_same_tenant_booking_and_prescription_succeed(): void
    {
        $patient = $this->createPatient('018'.str_pad((string) random_int(0, 99999999), 8, '0', STR_PAD_LEFT));

        $this->post(route('medical.appointments.store'), [
            'patient_id' => $patient->id,
            'doctor_id' => $this->doctorA->id,
            'appointment_date' => now()->addDay()->format('Y-m-d'),
            'appointment_time' => '09:00',
        ])->assertSessionHasNoErrors();

        $this->post(route('medical.prescriptions.store'), [
            'patient_id' => $patient->id,
            'doctor_id' => $this->doctorA->id,
            'prescription_date' => now()->format('Y-m-d'),
            'items' => [[
                'medicine_id' => null,
                'medicine_name' => 'Local Free Text',
                'dosage' => '1mg',
                'frequency' => '1+0+0',
                'quantity' => 1,
            ]],
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, Appointment::where('institute_id', $this->a->id)->count());
    }
}

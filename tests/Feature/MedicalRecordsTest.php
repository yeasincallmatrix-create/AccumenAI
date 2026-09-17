<?php

namespace Tests\Feature;

use App\Models\Institute;
use App\Models\Membership;
use App\Models\Role;
use App\Models\User;
use App\Models\Medical\Admission;
use App\Models\Medical\Appointment;
use App\Models\Medical\ClinicalAuditLog;
use App\Models\Medical\ClinicalNote;
use App\Models\Medical\DischargeSummary;
use App\Models\Medical\MedicalDocument;
use App\Models\Medical\NumberSequence;
use App\Models\Medical\Patient;
use App\Models\Medical\PatientTimelineEvent;
use App\Models\Medical\Prescription;
use App\Models\Medical\VitalSign;
use App\Services\Medical\NumberSequenceService;
use App\Services\Medical\PatientTimelineEventService;
use App\Services\ModuleAccessService;
use App\Support\Workspace;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MedicalRecordsTest extends TestCase
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
            'name' => 'EMR Test Hospital',
            'slug' => 'emr-test-' . uniqid(),
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

        app(ModuleAccessService::class)->enableModule($this->institute, 'medical.records');

        $this->patient = Patient::create([
            'institute_id' => $this->institute->id,
            'first_name' => 'Emr',
            'last_name' => 'Patient',
            'gender' => 'male',
            'date_of_birth' => '1990-01-01',
            'phone' => '01712345678',
            'is_patient' => true,
            'mr_number' => app(NumberSequenceService::class)->next(NumberSequence::TYPE_MR, $this->institute->id),
        ]);
    }

    private function makeAdmission(): Admission
    {
        return Admission::create([
            'institute_id' => $this->institute->id,
            'patient_id' => $this->patient->id,
            'admitting_doctor_id' => $this->owner->id,
            'admission_date' => today()->subDays(5)->toDateString(),
            'admission_time' => '10:00',
            'primary_diagnosis' => 'Typhoid fever',
            'status' => 'active',
        ]);
    }

    public function test_uploads_document(): void
    {
        Storage::fake('local');

        $response = $this->post(route('medical.records.documents.store'), [
            'patient_id' => $this->patient->id,
            'document_type' => 'lab_report',
            'title' => 'CBC Report',
            'file' => UploadedFile::fake()->create('cbc.pdf', 100, 'application/pdf'),
        ]);
        $response->assertRedirect();

        $doc = MedicalDocument::where('patient_id', $this->patient->id)->first();
        $this->assertNotNull($doc);
        $this->assertStringStartsWith('DOC-', $doc->document_number);
        $this->assertEquals('lab_report', $doc->document_type);
    }

    public function test_document_requires_file(): void
    {
        $response = $this->post(route('medical.records.documents.store'), [
            'patient_id' => $this->patient->id,
            'document_type' => 'lab_report',
            'title' => 'Missing file',
        ]);
        $response->assertSessionHasErrors('file');
    }

    public function test_document_type_validation(): void
    {
        Storage::fake('local');

        $response = $this->post(route('medical.records.documents.store'), [
            'patient_id' => $this->patient->id,
            'document_type' => 'not_a_real_type',
            'title' => 'Bad type',
            'file' => UploadedFile::fake()->create('x.pdf', 100, 'application/pdf'),
        ]);
        $response->assertSessionHasErrors('document_type');
    }

    public function test_timeline_aggregates_prescriptions(): void
    {
        Prescription::create([
            'institute_id' => $this->institute->id,
            'patient_id' => $this->patient->id,
            'doctor_id' => $this->owner->id,
            'prescription_number' => 'RX-TEST-001',
            'prescription_date' => today(),
            'diagnosis' => 'Viral fever',
        ]);

        $count = app(PatientTimelineEventService::class)->backfillForPatient($this->institute->id, $this->patient->id);
        $this->assertGreaterThanOrEqual(1, $count);

        $event = PatientTimelineEvent::where('patient_id', $this->patient->id)
            ->where('event_type', 'prescription')->first();
        $this->assertNotNull($event);
        $this->assertStringContainsString('RX-TEST-001', $event->title);
    }

    public function test_timeline_aggregates_admissions(): void
    {
        $adm = $this->makeAdmission();

        app(PatientTimelineEventService::class)->backfillForPatient($this->institute->id, $this->patient->id);

        $event = PatientTimelineEvent::where('patient_id', $this->patient->id)
            ->where('event_type', 'admission')
            ->where('source_id', $adm->id)->first();
        $this->assertNotNull($event);
    }

    public function test_timeline_aggregates_vitals(): void
    {
        $vital = VitalSign::create([
            'patient_id' => $this->patient->id,
            'blood_pressure_systolic' => 120,
            'blood_pressure_diastolic' => 80,
            'spo2' => 98,
            'recorded_by' => $this->owner->id,
            'recorded_at' => now(),
        ]);

        app(PatientTimelineEventService::class)->backfillForPatient($this->institute->id, $this->patient->id);

        $event = PatientTimelineEvent::where('patient_id', $this->patient->id)
            ->where('event_type', 'vitals')
            ->where('source_id', $vital->id)->first();
        $this->assertNotNull($event);
    }

    public function test_timeline_filters_by_type(): void
    {
        $service = app(PatientTimelineEventService::class);
        $service->recordEvent([
            'institute_id' => $this->institute->id,
            'patient_id' => $this->patient->id,
            'event_type' => 'prescription',
            'title' => 'RX event',
            'event_at' => now(),
        ]);
        $service->recordEvent([
            'institute_id' => $this->institute->id,
            'patient_id' => $this->patient->id,
            'event_type' => 'vitals',
            'title' => 'Vitals event',
            'event_at' => now(),
        ]);

        $response = $this->get(route('medical.records.patients.timeline', [
            'patient' => $this->patient->id,
            'event_types' => ['prescription'],
        ]));
        $response->assertStatus(200);
        $response->assertSee('RX event');
        $response->assertDontSee('Vitals event');
    }

    public function test_timeline_filters_by_date_range(): void
    {
        $service = app(PatientTimelineEventService::class);
        $service->recordEvent([
            'institute_id' => $this->institute->id,
            'patient_id' => $this->patient->id,
            'event_type' => 'note',
            'title' => 'Old note event',
            'event_at' => now()->subMonths(3),
            'event_date' => now()->subMonths(3)->toDateString(),
        ]);
        $service->recordEvent([
            'institute_id' => $this->institute->id,
            'patient_id' => $this->patient->id,
            'event_type' => 'note',
            'title' => 'Recent note event',
            'event_at' => now(),
        ]);

        $response = $this->get(route('medical.records.patients.timeline', [
            'patient' => $this->patient->id,
            'from' => now()->subDays(7)->format('Y-m-d'),
        ]));
        $response->assertStatus(200);
        $response->assertSee('Recent note event');
        $response->assertDontSee('Old note event');
    }

    public function test_backfill_creates_timeline_events(): void
    {
        Prescription::create([
            'institute_id' => $this->institute->id,
            'patient_id' => $this->patient->id,
            'doctor_id' => $this->owner->id,
            'prescription_number' => 'RX-BACKFILL-1',
            'prescription_date' => today(),
            'diagnosis' => 'Test',
        ]);
        Appointment::create([
            'institute_id' => $this->institute->id,
            'patient_id' => $this->patient->id,
            'doctor_id' => $this->owner->id,
            'appointment_date' => today(),
            'appointment_time' => '10:00',
            'status' => 'completed',
        ]);
        $this->makeAdmission();

        $response = $this->post(route('medical.records.patients.timeline.backfill', $this->patient));
        $response->assertRedirect();

        $count = PatientTimelineEvent::where('institute_id', $this->institute->id)
            ->where('patient_id', $this->patient->id)->count();
        $this->assertGreaterThanOrEqual(3, $count);

        // Idempotent: second backfill creates nothing new
        $again = app(PatientTimelineEventService::class)->backfillForPatient($this->institute->id, $this->patient->id);
        $this->assertEquals(0, $again);
    }

    public function test_creates_discharge_summary(): void
    {
        $adm = $this->makeAdmission();

        $response = $this->post(route('medical.records.discharge-summaries.store'), [
            'admission_id' => $adm->id,
            'admission_diagnosis' => 'Typhoid fever',
            'hospital_course' => 'IV antibiotics, improved',
            'discharge_medications' => 'Cefixime 200mg BD x 7 days',
            'discharge_instructions' => 'Rest, fluids',
            'condition_on_discharge' => 'improved',
            'discharge_date' => today()->toDateString(),
        ]);
        $response->assertRedirect();

        $summary = DischargeSummary::where('admission_id', $adm->id)->first();
        $this->assertNotNull($summary);
        $this->assertStringStartsWith('DS-', $summary->summary_number);
    }

    public function test_discharge_summary_calculates_los(): void
    {
        $this->assertEquals(6, DischargeSummary::computeLos(
            today()->subDays(5)->toDateString(),
            today()->toDateString()
        ));
    }

    public function test_generates_discharge_summary_pdf(): void
    {
        $adm = $this->makeAdmission();
        $summary = DischargeSummary::create([
            'institute_id' => $this->institute->id,
            'summary_number' => 'DS-TEST-001',
            'admission_id' => $adm->id,
            'patient_id' => $this->patient->id,
            'prepared_by' => $this->owner->id,
            'admission_date' => $adm->admission_date,
            'discharge_date' => today(),
            'length_of_stay_days' => 6,
            'admission_diagnosis' => 'Typhoid',
            'hospital_course' => 'Improved',
            'discharge_medications' => 'Meds',
            'discharge_instructions' => 'Rest',
            'condition_on_discharge' => 'improved',
        ]);

        $response = $this->get(route('medical.records.discharge-summaries.pdf', $summary));
        $response->assertStatus(200);
        $response->assertSee('DS-TEST-001');
    }

    public function test_creates_clinical_note(): void
    {
        $response = $this->post(route('medical.records.notes.store'), [
            'patient_id' => $this->patient->id,
            'note_type' => 'progress',
            'subjective' => 'Fever for 3 days',
            'objective' => 'Temp 101F',
            'assessment' => 'Viral fever',
            'plan' => 'Paracetamol, fluids',
            'noted_at' => now()->format('Y-m-d\TH:i'),
        ]);
        $response->assertRedirect();

        $note = ClinicalNote::where('patient_id', $this->patient->id)->first();
        $this->assertNotNull($note);
        $this->assertStringStartsWith('CN-', $note->note_number);
        $this->assertFalse($note->isSigned());
    }

    public function test_signs_clinical_note(): void
    {
        $note = ClinicalNote::create([
            'institute_id' => $this->institute->id,
            'note_number' => 'CN-TEST-001',
            'patient_id' => $this->patient->id,
            'author_id' => $this->owner->id,
            'note_type' => 'progress',
            'assessment' => 'Stable',
            'noted_at' => now(),
        ]);

        $response = $this->post(route('medical.records.notes.sign', $note));
        $response->assertRedirect();

        $note->refresh();
        $this->assertTrue($note->isSigned());
        $this->assertNotNull($note->signed_at);
    }

    public function test_amend_clinical_note(): void
    {
        $note = ClinicalNote::create([
            'institute_id' => $this->institute->id,
            'note_number' => 'CN-TEST-002',
            'patient_id' => $this->patient->id,
            'author_id' => $this->owner->id,
            'note_type' => 'progress',
            'assessment' => 'Stable',
            'noted_at' => now(),
            'is_signed' => true,
            'signed_at' => now(),
        ]);

        $response = $this->post(route('medical.records.notes.amend', $note), [
            'amendment_reason' => 'Typo in assessment',
            'addendum' => 'Corrected assessment.',
        ]);
        $response->assertRedirect();

        $note->refresh();
        $this->assertTrue($note->is_amended);
        $this->assertEquals('Typo in assessment', $note->amendment_reason);
    }

    public function test_document_tenant_isolation(): void
    {
        $other = Institute::create([
            'name' => 'Other Hospital',
            'slug' => 'other-' . uniqid(),
            'industry' => 'healthcare',
            'sub_industry' => 'hospital',
            'country' => 'Bangladesh',
            'status' => 'active',
        ]);

        $doc = MedicalDocument::create([
            'institute_id' => $other->id,
            'patient_id' => $this->patient->id,
            'document_number' => 'DOC-OTHER-1',
            'document_type' => 'other',
            'title' => 'Other institute doc',
            'file_path' => 'other/path.pdf',
            'original_filename' => 'path.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 100,
            'uploaded_by' => $this->owner->id,
        ]);

        $this->assertEquals(0, MedicalDocument::forInstitute($this->institute->id)->count());

        $response = $this->get(route('medical.records.documents.show', $doc));
        $response->assertStatus(403);
    }

    public function test_timeline_tenant_isolation(): void
    {
        app(PatientTimelineEventService::class)->recordEvent([
            'institute_id' => $this->institute->id,
            'patient_id' => $this->patient->id,
            'event_type' => 'note',
            'title' => 'Home event',
            'event_at' => now(),
        ]);

        $other = Institute::create([
            'name' => 'Other Hospital 2',
            'slug' => 'other2-' . uniqid(),
            'industry' => 'healthcare',
            'sub_industry' => 'hospital',
            'country' => 'Bangladesh',
            'status' => 'active',
        ]);

        $events = app(PatientTimelineEventService::class)->getTimeline($other->id, $this->patient->id);
        $this->assertCount(0, $events);
    }

    public function test_audit_log_records_actions(): void
    {
        Storage::fake('local');

        $this->post(route('medical.records.documents.store'), [
            'patient_id' => $this->patient->id,
            'document_type' => 'other',
            'title' => 'Audited doc',
            'file' => UploadedFile::fake()->create('audit.pdf', 100, 'application/pdf'),
        ]);

        $doc = MedicalDocument::where('patient_id', $this->patient->id)->first();
        $this->assertNotNull($doc);

        $logged = ClinicalAuditLog::where('auditable_type', MedicalDocument::class)
            ->where('auditable_id', $doc->id)
            ->where('action', 'created')
            ->exists();
        $this->assertTrue($logged);
    }

    public function test_dashboard_loads(): void
    {
        $response = $this->get(route('medical.records.dashboard'));
        $response->assertStatus(200);
        $response->assertSee('Medical Records');

        $response = $this->get(route('medical.records.documents.index'));
        $response->assertStatus(200);

        $response = $this->get(route('medical.records.discharge-summaries.index'));
        $response->assertStatus(200);

        $response = $this->get(route('medical.records.notes.index'));
        $response->assertStatus(200);
    }

    public function test_permission_enforcement(): void
    {
        Storage::fake('local');

        $this->post(route('medical.records.documents.store'), [
            'patient_id' => $this->patient->id,
            'document_type' => 'other',
            'title' => 'Download guard doc',
            'file' => UploadedFile::fake()->create('guard.pdf', 100, 'application/pdf'),
        ]);
        $doc = MedicalDocument::where('patient_id', $this->patient->id)->first();
        $this->assertNotNull($doc);

        $staff = User::factory()->create([
            'account_type' => 'staff',
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
        $roleId = Role::where('slug', 'doctor')->value('id');
        Membership::create([
            'user_id' => $staff->id,
            'institution_id' => $this->institute->id,
            'role_id' => $roleId,
            'status' => 'active',
        ]);

        $this->actingAs($staff, 'web');
        Workspace::set($this->institute->id);

        // Doctor role has no document.download permission → 403
        $this->get(route('medical.records.documents.download', $doc))->assertStatus(403);
    }
}

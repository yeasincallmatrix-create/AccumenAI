<?php

namespace Tests\Feature;

use App\Models\Institute;
use App\Models\Medical\NumberSequence;
use App\Models\Medical\RadiologyImage;
use App\Models\Medical\RadiologyOrder;
use App\Models\Medical\Patient;
use App\Models\Membership;
use App\Models\Role;
use App\Models\User;
use App\Support\Workspace;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RadiologyOrderTest extends TestCase
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
            'name' => 'Radiology Test Hospital',
            'slug' => 'radiology-test-' . uniqid(),
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

        // Enable radiology module
        app(\App\Services\ModuleAccessService::class)
            ->enableModule($this->institute, 'medical.radiology');

        // Create a patient
        $this->patient = Patient::create([
            'institute_id' => $this->institute->id,
            'first_name' => 'Test',
            'last_name' => 'Patient',
            'gender' => 'male',
            'date_of_birth' => '1990-01-01',
            'phone' => '01712345678',
            'is_patient' => true,
            'mr_number' => 'MR-2026-00001',
        ]);
    }

    public function test_creates_radiology_order(): void
    {
        $response = $this->post(route('medical.radiology.orders.store'), [
            'patient_id' => $this->patient->id,
            'modality' => 'X-Ray',
            'body_part' => 'Chest',
            'clinical_indication' => 'Chest pain evaluation',
            'is_urgent' => false,
            'fee' => 500,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('radiology_orders', [
            'institute_id' => $this->institute->id,
            'modality' => 'X-Ray',
            'body_part' => 'Chest',
        ]);
    }

    public function test_schedule_sets_scheduled_at(): void
    {
        $order = RadiologyOrder::create([
            'institute_id' => $this->institute->id,
            'order_number' => 'RAD-2026-00001',
            'patient_id' => $this->patient->id,
            'modality' => 'CT',
            'body_part' => 'Abdomen',
            'status' => 'ordered',
        ]);

        $scheduledAt = now()->addDay()->format('Y-m-d\TH:i');
        $response = $this->post(route('medical.radiology.orders.schedule', $order), [
            'scheduled_at' => $scheduledAt,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('radiology_orders', [
            'id' => $order->id,
            'status' => 'scheduled',
        ]);
    }

    public function test_start_marks_in_progress(): void
    {
        $order = RadiologyOrder::create([
            'institute_id' => $this->institute->id,
            'order_number' => 'RAD-2026-00002',
            'patient_id' => $this->patient->id,
            'modality' => 'MRI',
            'body_part' => 'Head',
            'status' => 'scheduled',
        ]);

        $response = $this->post(route('medical.radiology.orders.start', $order));

        $response->assertRedirect();
        $this->assertDatabaseHas('radiology_orders', [
            'id' => $order->id,
            'status' => 'in_progress',
        ]);
    }

    public function test_perform_completes_technician_step(): void
    {
        $order = RadiologyOrder::create([
            'institute_id' => $this->institute->id,
            'order_number' => 'RAD-2026-00003',
            'patient_id' => $this->patient->id,
            'modality' => 'USG',
            'body_part' => 'Abdomen',
            'status' => 'in_progress',
        ]);

        $response = $this->post(route('medical.radiology.orders.perform', $order), [
            'technique' => 'Standard protocol',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('radiology_orders', [
            'id' => $order->id,
            'status' => 'completed',
        ]);
    }

    public function test_report_records_findings_and_impression(): void
    {
        $order = RadiologyOrder::create([
            'institute_id' => $this->institute->id,
            'order_number' => 'RAD-2026-00004',
            'patient_id' => $this->patient->id,
            'modality' => 'X-Ray',
            'body_part' => 'Chest',
            'status' => 'completed',
        ]);

        $response = $this->post(route('medical.radiology.orders.report', $order), [
            'findings' => 'No acute abnormality detected.',
            'impression' => 'Normal chest X-ray.',
            'recommendations' => 'No further imaging needed.',
            'radiologist_name' => 'Dr. Radiologist',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('radiology_orders', [
            'id' => $order->id,
            'status' => 'reported',
            'findings' => 'No acute abnormality detected.',
            'impression' => 'Normal chest X-ray.',
        ]);
    }

    public function test_verify_finalizes_report(): void
    {
        $order = RadiologyOrder::create([
            'institute_id' => $this->institute->id,
            'order_number' => 'RAD-2026-00005',
            'patient_id' => $this->patient->id,
            'modality' => 'CT',
            'body_part' => 'Head',
            'status' => 'reported',
        ]);

        $response = $this->post(route('medical.radiology.orders.verify', $order));

        $response->assertRedirect();
        $this->assertDatabaseHas('radiology_orders', [
            'id' => $order->id,
            'verified_by' => $this->owner->id,
        ]);
    }

    public function test_upload_image_attaches_to_order(): void
    {
        Storage::fake('public');

        $order = RadiologyOrder::create([
            'institute_id' => $this->institute->id,
            'order_number' => 'RAD-2026-00006',
            'patient_id' => $this->patient->id,
            'modality' => 'X-Ray',
            'body_part' => 'Chest',
            'status' => 'completed',
        ]);

        $file = UploadedFile::fake()->image('xray.jpg', 600, 400);

        $response = $this->post(route('medical.radiology.orders.images.upload', $order), [
            'image' => $file,
            'caption' => 'Chest X-ray PA view',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('radiology_images', [
            'radiology_order_id' => $order->id,
            'original_filename' => 'xray.jpg',
        ]);
    }

    public function test_upload_rejects_oversized_file(): void
    {
        Storage::fake('public');

        $order = RadiologyOrder::create([
            'institute_id' => $this->institute->id,
            'order_number' => 'RAD-2026-00007',
            'patient_id' => $this->patient->id,
            'modality' => 'X-Ray',
            'body_part' => 'Chest',
            'status' => 'completed',
        ]);

        $file = UploadedFile::fake()->create('large.jpg', 15000); // 15MB > 10MB limit

        $response = $this->post(route('medical.radiology.orders.images.upload', $order), [
            'image' => $file,
        ]);

        $response->assertSessionHasErrors('image');
    }

    public function test_index_scopes_to_institute(): void
    {
        // Create order in our institute
        RadiologyOrder::create([
            'institute_id' => $this->institute->id,
            'order_number' => 'RAD-2026-00008',
            'patient_id' => $this->patient->id,
            'modality' => 'X-Ray',
            'body_part' => 'Chest',
        ]);

        // Create order in another institute
        $otherInstitute = Institute::create([
            'name' => 'Other Test Hospital',
            'slug' => 'other-radiology-test-' . uniqid(),
            'industry' => 'healthcare',
            'sub_industry' => 'hospital',
            'country' => 'Bangladesh',
            'status' => 'active',
        ]);

        RadiologyOrder::create([
            'institute_id' => $otherInstitute->id,
            'order_number' => 'RAD-2026-00009',
            'patient_id' => $this->patient->id,
            'modality' => 'CT',
            'body_part' => 'Head',
        ]);

        $response = $this->get(route('medical.radiology.orders.index'));
        $response->assertStatus(200);
        $response->assertSee('RAD-2026-00008');
        $response->assertDontSee('RAD-2026-00009');
    }

    public function test_workflow_requires_permission(): void
    {
        $staff = User::factory()->create([
            'account_type' => 'staff',
            'email_verified_at' => now(),
            'status' => 'active',
        ]);

        $staffRoleId = Role::where('slug', 'doctor')->value('id');
        Membership::create([
            'user_id' => $staff->id,
            'institution_id' => $this->institute->id,
            'role_id' => $staffRoleId,
            'status' => 'active',
        ]);

        $this->actingAs($staff, 'web');
        Workspace::set($this->institute->id);

        $order = RadiologyOrder::create([
            'institute_id' => $this->institute->id,
            'order_number' => 'RAD-2026-00010',
            'patient_id' => $this->patient->id,
            'modality' => 'X-Ray',
            'body_part' => 'Chest',
            'status' => 'ordered',
        ]);

        $response = $this->get(route('medical.radiology.orders.index'));
        $response->assertStatus(403);
    }

    public function test_audit_log_records_all_workflow_actions(): void
    {
        $order = RadiologyOrder::create([
            'institute_id' => $this->institute->id,
            'order_number' => 'RAD-2026-00011',
            'patient_id' => $this->patient->id,
            'modality' => 'X-Ray',
            'body_part' => 'Chest',
            'status' => 'ordered',
        ]);

        // Schedule
        $this->post(route('medical.radiology.orders.schedule', $order), [
            'scheduled_at' => now()->addDay()->format('Y-m-d\TH:i'),
        ]);
        $this->assertDatabaseHas('clinical_audit_logs', [
            'auditable_type' => 'App\Models\Medical\RadiologyOrder',
            'auditable_id' => $order->id,
            'action' => 'scheduled',
        ]);

        // Start
        $this->post(route('medical.radiology.orders.start', $order));
        $this->assertDatabaseHas('clinical_audit_logs', [
            'auditable_id' => $order->id,
            'action' => 'started',
        ]);

        // Perform
        $this->post(route('medical.radiology.orders.perform', $order));
        $this->assertDatabaseHas('clinical_audit_logs', [
            'auditable_id' => $order->id,
            'action' => 'performed',
        ]);

        // Report
        $this->post(route('medical.radiology.orders.report', $order), [
            'findings' => 'Test findings',
            'impression' => 'Test impression',
        ]);
        $this->assertDatabaseHas('clinical_audit_logs', [
            'auditable_id' => $order->id,
            'action' => 'reported',
        ]);

        // Verify
        $this->post(route('medical.radiology.orders.verify', $order));
        $this->assertDatabaseHas('clinical_audit_logs', [
            'auditable_id' => $order->id,
            'action' => 'verified',
        ]);
    }

    public function test_order_number_is_unique_per_institute(): void
    {
        RadiologyOrder::create([
            'institute_id' => $this->institute->id,
            'order_number' => 'RAD-2026-00099',
            'patient_id' => $this->patient->id,
            'modality' => 'X-Ray',
            'body_part' => 'Chest',
        ]);

        $this->assertDatabaseHas('radiology_orders', [
            'institute_id' => $this->institute->id,
            'order_number' => 'RAD-2026-00099',
        ]);

        // Different institute can use same order number
        $otherInstitute = Institute::create([
            'name' => 'Other Hospital',
            'slug' => 'other-unique-test-' . uniqid(),
            'industry' => 'healthcare',
            'sub_industry' => 'hospital',
            'country' => 'Bangladesh',
            'status' => 'active',
        ]);

        $otherOrder = RadiologyOrder::create([
            'institute_id' => $otherInstitute->id,
            'order_number' => 'RAD-2026-00099',
            'patient_id' => $this->patient->id,
            'modality' => 'X-Ray',
            'body_part' => 'Chest',
        ]);

        $this->assertDatabaseHas('radiology_orders', [
            'institute_id' => $otherInstitute->id,
            'order_number' => 'RAD-2026-00099',
        ]);
    }

    public function test_soft_delete_preserves_images(): void
    {
        Storage::fake('public');

        $order = RadiologyOrder::create([
            'institute_id' => $this->institute->id,
            'order_number' => 'RAD-2026-00012',
            'patient_id' => $this->patient->id,
            'modality' => 'X-Ray',
            'body_part' => 'Chest',
        ]);

        $file = UploadedFile::fake()->image('test.jpg');
        $image = RadiologyImage::create([
            'radiology_order_id' => $order->id,
            'file_path' => 'radiology/' . $order->id . '/test.jpg',
            'original_filename' => 'test.jpg',
            'mime_type' => 'image/jpeg',
            'file_size' => 1024,
            'uploaded_by' => $this->owner->id,
        ]);

        $this->delete(route('medical.radiology.orders.destroy', $order));

        $this->assertSoftDeleted('radiology_orders', ['id' => $order->id]);
        $this->assertDatabaseHas('radiology_images', [
            'radiology_order_id' => $order->id,
        ]);
    }
}

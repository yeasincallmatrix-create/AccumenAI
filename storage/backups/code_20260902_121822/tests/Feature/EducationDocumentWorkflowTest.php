<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Country;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\DocumentVersion;
use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\Role;
use App\Models\Student;
use App\Models\Workflow;
use App\Models\WorkflowHistory;
use App\Models\WorkflowStep;
use App\Support\BranchContext;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Step 51 — Education Document / Workflow Automation.
 *
 * Covers: document verification (verify/reject/replacement-request), rejection
 * reason enforcement, version history preservation on replace, expiry tracking,
 * document requirement checklist + readiness, workflow creation/transitions/
 * approval/rejection/history, permission enforcement, tenant isolation and
 * historical safety.
 */
class EducationDocumentWorkflowTest extends TestCase
{
    use DatabaseTransactions;

    protected string $password = 'secret12345';

    protected function tearDown(): void
    {
        TenantContext::clear();
        BranchContext::clear();
        parent::tearDown();
    }

    // ------------------------------------------------------------ Fixtures

    private function country(): Country
    {
        return Country::withoutGlobalScopes()->firstOrCreate(
            ['iso2' => 'BD'],
            ['name' => 'Bangladesh', 'iso3' => 'BGD', 'phone_code' => '880', 'status' => true]
        );
    }

    private function institute(string $name = 'Step51 Inst'): Institute
    {
        $country = $this->country();

        return Institute::create([
            'name' => $name,
            'slug' => str()->slug($name.'-'.uniqid()),
            'country' => $country->name,
            'country_id' => $country->id,
            'status' => 'active',
        ]);
    }

    private function branch(Institute $institute, string $name): Branch
    {
        return Branch::create(['institute_id' => $institute->id, 'name' => $name, 'status' => 'active']);
    }

    private function user(Institute $institute, string $roleSlug, string $prefix, ?Branch $branch = null): InstituteUser
    {
        return InstituteUser::create([
            'institute_id' => $institute->id,
            'branch_id' => $branch?->id,
            'role_id' => Role::where('slug', $roleSlug)->firstOrFail()->id,
            'first_name' => $prefix,
            'last_name' => 'User',
            'email' => $prefix.'-'.uniqid().'@example.test',
            'phone' => '01700'.mt_rand(100000, 999999),
            'password_hash' => bcrypt($this->password),
            'status' => 'active',
        ]);
    }

    private function student(Institute $institute, ?Branch $branch = null): Student
    {
        return Student::create([
            'institute_id' => $institute->id,
            'branch_id' => $branch?->id,
            'student_id_number' => 'S51'.strtoupper(str()->random(8)),
            'first_name' => 'Step',
            'last_name' => 'Student',
            'status' => 'active',
            'admission_date' => '2026-01-01',
        ]);
    }

    private function requiredCategory(Institute $institute, string $slug, string $stage = 'admission', bool $verificationRequired = true): DocumentCategory
    {
        return DocumentCategory::create([
            'institute_id' => $institute->id,
            'name' => ucfirst(str_replace('-', ' ', $slug)),
            'slug' => $slug.'-'.uniqid(),
            'code' => strtoupper($slug),
            'entity_types' => ['student'],
            'is_active' => true,
            'is_required' => true,
            'lifecycle_stage' => $stage,
            'verification_required' => $verificationRequired,
            'sort_order' => 1,
        ]);
    }

    private function pdf(): UploadedFile
    {
        return UploadedFile::fake()->create('doc.pdf', 200, 'application/pdf');
    }

    private function uploadDocument(InstituteUser $actor, Student $student, DocumentCategory $category): Document
    {
        $this->actingAs($actor, 'institute_user')
            ->withHeaders(['Accept' => 'application/json'])
            ->post(route('documents.store'), [
                'entity' => 'student',
                'entity_id' => $student->id,
                'category_id' => $category->id,
                'file' => $this->pdf(),
                'title' => $category->name,
            ])->assertStatus(201);

        return Document::query()->where('documentable_id', $student->id)->where('category_id', $category->id)->latest('id')->firstOrFail();
    }

    // ------------------------------------------------------- Verification

    public function test_document_can_be_verified(): void
    {
        Storage::fake('public');
        $institute = $this->institute();
        $owner = $this->user($institute, 'institute-owner', 'owner');
        $student = $this->student($institute);
        $category = $this->requiredCategory($institute, 'birth-certificate');
        $document = $this->uploadDocument($owner, $student, $category);

        $response = $this->actingAs($owner, 'institute_user')
            ->withHeaders(['Accept' => 'application/json'])
            ->post(route('documents.verify', $document), ['notes' => 'Looks good']);

        $response->assertOk()->assertJson(['success' => true]);

        $document->refresh();
        $this->assertSame(Document::VERIFICATION_VERIFIED, $document->verification_status);
        $this->assertSame((int) $owner->id, (int) $document->verified_by);
        $this->assertNotNull($document->verified_at);

        $this->assertDatabaseHas('audit_logs', [
            'institute_id' => $institute->id,
            'module' => 'documents',
            'action' => 'document_verified',
            'record_id' => $document->id,
        ]);
    }

    public function test_rejection_requires_reason(): void
    {
        Storage::fake('public');
        $institute = $this->institute();
        $owner = $this->user($institute, 'institute-owner', 'owner');
        $student = $this->student($institute);
        $category = $this->requiredCategory($institute, 'nid');
        $document = $this->uploadDocument($owner, $student, $category);

        $this->actingAs($owner, 'institute_user')
            ->withHeaders(['Accept' => 'application/json'])
            ->post(route('documents.reject', $document), ['reason' => ''])
            ->assertStatus(422)
            ->assertJsonValidationErrors('reason');

        $this->assertSame(Document::VERIFICATION_PENDING, $document->refresh()->verification_status);
    }

    public function test_document_can_be_rejected_with_reason(): void
    {
        Storage::fake('public');
        $institute = $this->institute();
        $owner = $this->user($institute, 'institute-owner', 'owner');
        $student = $this->student($institute);
        $category = $this->requiredCategory($institute, 'passport');
        $document = $this->uploadDocument($owner, $student, $category);

        $this->actingAs($owner, 'institute_user')
            ->withHeaders(['Accept' => 'application/json'])
            ->post(route('documents.reject', $document), ['reason' => 'Blurry scan'])
            ->assertOk();

        $document->refresh();
        $this->assertSame(Document::VERIFICATION_REJECTED, $document->verification_status);
        $this->assertSame('Blurry scan', $document->rejection_reason);
    }

    public function test_verifying_already_verified_document_fails(): void
    {
        Storage::fake('public');
        $institute = $this->institute();
        $owner = $this->user($institute, 'institute-owner', 'owner');
        $student = $this->student($institute);
        $category = $this->requiredCategory($institute, 'photo');
        $document = $this->uploadDocument($owner, $student, $category);

        $this->actingAs($owner, 'institute_user')->withHeaders(['Accept' => 'application/json'])
            ->post(route('documents.verify', $document))->assertOk();

        $this->actingAs($owner, 'institute_user')->withHeaders(['Accept' => 'application/json'])
            ->post(route('documents.verify', $document))->assertStatus(422);
    }

    // ---------------------------------------------------------- Versioning

    public function test_replacement_preserves_version_history(): void
    {
        Storage::fake('public');
        $institute = $this->institute();
        $owner = $this->user($institute, 'institute-owner', 'owner');
        $student = $this->student($institute);
        $category = $this->requiredCategory($institute, 'academic-certificate');
        $document = $this->uploadDocument($owner, $student, $category);

        $originalPath = $document->file_path;
        $this->assertSame(1, (int) $document->version);

        $this->actingAs($owner, 'institute_user')
            ->withHeaders(['Accept' => 'application/json'])
            ->post(route('documents.replace', $document), ['file' => $this->pdf()])
            ->assertOk();

        $document->refresh();
        $this->assertSame(2, (int) $document->version);

        // The outgoing version 1 is preserved in history, file not deleted.
        $this->assertDatabaseHas('document_versions', [
            'document_id' => $document->id,
            'version' => 1,
            'file_path' => $originalPath,
        ]);
        Storage::disk('public')->assertExists($originalPath);

        // Replacement resets verification to pending.
        $this->assertSame(Document::VERIFICATION_PENDING, $document->verification_status);
        $this->assertNull($document->verified_by);
    }

    public function test_versions_endpoint_returns_history(): void
    {
        Storage::fake('public');
        $institute = $this->institute();
        $owner = $this->user($institute, 'institute-owner', 'owner');
        $student = $this->student($institute);
        $category = $this->requiredCategory($institute, 'transcript');
        $document = $this->uploadDocument($owner, $student, $category);

        $this->actingAs($owner, 'institute_user')->withHeaders(['Accept' => 'application/json'])
            ->post(route('documents.replace', $document), ['file' => $this->pdf()])->assertOk();

        $response = $this->actingAs($owner, 'institute_user')
            ->withHeaders(['Accept' => 'application/json'])
            ->get(route('documents.versions', $document));

        $response->assertOk();
        $this->assertSame(2, $response->json('data.current_version'));
        $this->assertCount(1, $response->json('data.versions'));
    }

    // ------------------------------------------------------------- Expiry

    public function test_expired_document_reports_expired_status(): void
    {
        Storage::fake('public');
        $institute = $this->institute();
        $owner = $this->user($institute, 'institute-owner', 'owner');
        $student = $this->student($institute);
        $category = $this->requiredCategory($institute, 'medical');
        $document = $this->uploadDocument($owner, $student, $category);

        $document->update([
            'verification_status' => Document::VERIFICATION_VERIFIED,
            'expiry_date' => now()->subDay()->toDateString(),
        ]);

        $this->assertTrue($document->isExpired());
        $this->assertSame(Document::VERIFICATION_EXPIRED, $document->effectiveVerificationStatus());
    }

    public function test_expiring_soon_detection(): void
    {
        Storage::fake('public');
        $institute = $this->institute();
        $owner = $this->user($institute, 'institute-owner', 'owner');
        $student = $this->student($institute);
        $category = $this->requiredCategory($institute, 'insurance');
        $document = $this->uploadDocument($owner, $student, $category);

        $document->update(['expiry_date' => now()->addDays(10)->toDateString()]);

        $this->assertFalse($document->isExpired());
        $this->assertTrue($document->isExpiringSoon(30));
    }

    // ---------------------------------------------------------- Checklist

    public function test_checklist_reports_missing_required_document(): void
    {
        Storage::fake('public');
        $institute = $this->institute();
        $owner = $this->user($institute, 'institute-owner', 'owner');
        $student = $this->student($institute);
        $this->requiredCategory($institute, 'birth-cert', 'admission');

        $response = $this->actingAs($owner, 'institute_user')
            ->withHeaders(['Accept' => 'application/json'])
            ->get(route('documents.checklist', ['student' => $student->id, 'stage' => 'admission']));

        $response->assertOk();
        $this->assertSame('not_ready', $response->json('data.readiness'));
        $this->assertSame(1, $response->json('data.summary.missing'));
    }

    public function test_checklist_uploaded_but_unverified_is_not_ready(): void
    {
        Storage::fake('public');
        $institute = $this->institute();
        $owner = $this->user($institute, 'institute-owner', 'owner');
        $student = $this->student($institute);
        $category = $this->requiredCategory($institute, 'guardian-id', 'admission', true);
        $this->uploadDocument($owner, $student, $category);

        $response = $this->actingAs($owner, 'institute_user')
            ->withHeaders(['Accept' => 'application/json'])
            ->get(route('documents.checklist', ['student' => $student->id, 'stage' => 'admission']));

        $response->assertOk();
        // verification_required + only submitted => NOT READY (uploaded != verified).
        $this->assertSame('not_ready', $response->json('data.readiness'));
        $this->assertSame(1, $response->json('data.summary.submitted'));
        $this->assertSame(0, $response->json('data.summary.verified'));
    }

    public function test_checklist_verified_is_ready(): void
    {
        Storage::fake('public');
        $institute = $this->institute();
        $owner = $this->user($institute, 'institute-owner', 'owner');
        $student = $this->student($institute);
        $category = $this->requiredCategory($institute, 'prev-cert', 'admission', true);
        $document = $this->uploadDocument($owner, $student, $category);

        $this->actingAs($owner, 'institute_user')->withHeaders(['Accept' => 'application/json'])
            ->post(route('documents.verify', $document))->assertOk();

        $response = $this->actingAs($owner, 'institute_user')
            ->withHeaders(['Accept' => 'application/json'])
            ->get(route('documents.checklist', ['student' => $student->id, 'stage' => 'admission']));

        $response->assertOk();
        $this->assertSame('ready', $response->json('data.readiness'));
        $this->assertSame(1, $response->json('data.summary.verified'));
    }

    public function test_checklist_rejected_document_is_not_ready(): void
    {
        Storage::fake('public');
        $institute = $this->institute();
        $owner = $this->user($institute, 'institute-owner', 'owner');
        $student = $this->student($institute);
        $category = $this->requiredCategory($institute, 'photo-id', 'admission', true);
        $document = $this->uploadDocument($owner, $student, $category);

        $this->actingAs($owner, 'institute_user')->withHeaders(['Accept' => 'application/json'])
            ->post(route('documents.reject', $document), ['reason' => 'Unclear'])->assertOk();

        $response = $this->actingAs($owner, 'institute_user')
            ->withHeaders(['Accept' => 'application/json'])
            ->get(route('documents.checklist', ['student' => $student->id, 'stage' => 'admission']));

        $response->assertOk();
        $this->assertSame('not_ready', $response->json('data.readiness'));
        $this->assertSame(1, $response->json('data.summary.rejected'));
    }

    public function test_checklist_pending_non_required_is_ready_with_exceptions(): void
    {
        Storage::fake('public');
        $institute = $this->institute();
        $owner = $this->user($institute, 'institute-owner', 'owner');
        $student = $this->student($institute);
        $category = $this->requiredCategory($institute, 'optional-doc', 'admission', false);
        $this->uploadDocument($owner, $student, $category);

        $response = $this->actingAs($owner, 'institute_user')
            ->withHeaders(['Accept' => 'application/json'])
            ->get(route('documents.checklist', ['student' => $student->id, 'stage' => 'admission']));

        $response->assertOk();
        $this->assertSame('ready_with_exceptions', $response->json('data.readiness'));
    }

    // ----------------------------------------------------------- Workflow

    public function test_workflow_can_be_created_with_steps(): void
    {
        $institute = $this->institute();
        $owner = $this->user($institute, 'institute-owner', 'owner');
        $student = $this->student($institute);

        $response = $this->actingAs($owner, 'institute_user')
            ->post(route('workflows.store'), [
                'workflow_type' => 'certificate_request',
                'title' => 'Certificate for '.$student->student_id_number,
                'student_id' => $student->id,
            ]);

        $response->assertRedirect();

        $workflow = Workflow::query()->where('institute_id', $institute->id)->firstOrFail();
        $this->assertSame('draft', $workflow->status);
        $this->assertSame(3, $workflow->steps()->count());
        $this->assertSame((int) $student->id, (int) $workflow->student_id);

        $this->assertDatabaseHas('workflow_histories', [
            'workflow_id' => $workflow->id,
            'action' => 'created',
        ]);
    }

    public function test_workflow_invalid_transition_is_rejected(): void
    {
        $institute = $this->institute();
        $owner = $this->user($institute, 'institute-owner', 'owner');

        $this->actingAs($owner, 'institute_user')->post(route('workflows.store'), [
            'workflow_type' => 'student_transfer',
            'title' => 'Transfer Test',
        ])->assertRedirect();

        $workflow = Workflow::query()->where('institute_id', $institute->id)->firstOrFail();

        // draft -> approved is not a valid transition.
        $this->actingAs($owner, 'institute_user')
            ->post(route('workflows.transition', $workflow), ['status' => 'approved'])
            ->assertSessionHasErrors('status');

        $this->assertSame('draft', $workflow->refresh()->status);
    }

    public function test_workflow_submit_then_review_then_approve_steps_to_completion(): void
    {
        $institute = $this->institute();
        $owner = $this->user($institute, 'institute-owner', 'owner');

        $this->actingAs($owner, 'institute_user')->post(route('workflows.store'), [
            'workflow_type' => 'certificate_request',
            'title' => 'Full Flow',
        ])->assertRedirect();

        $workflow = Workflow::query()->where('institute_id', $institute->id)->firstOrFail();

        $this->actingAs($owner, 'institute_user')
            ->post(route('workflows.transition', $workflow), ['status' => 'submitted'])
            ->assertRedirect();
        $this->assertSame('submitted', $workflow->refresh()->status);

        $this->actingAs($owner, 'institute_user')
            ->post(route('workflows.transition', $workflow), ['status' => 'under_review'])
            ->assertRedirect();
        $this->assertSame('under_review', $workflow->refresh()->status);

        // Approve all 3 steps.
        foreach ([1, 2, 3] as $i) {
            $this->actingAs($owner, 'institute_user')
                ->post(route('workflows.approve-step', $workflow), ['comment' => "ok $i"])
                ->assertRedirect();
            $workflow->refresh();
        }

        $this->assertSame('completed', $workflow->status);
        $this->assertNotNull($workflow->completed_at);
        $this->assertSame(3, $workflow->steps()->where('status', WorkflowStep::STATUS_APPROVED)->count());
    }

    public function test_workflow_reject_step_requires_comment(): void
    {
        $institute = $this->institute();
        $owner = $this->user($institute, 'institute-owner', 'owner');

        $this->actingAs($owner, 'institute_user')->post(route('workflows.store'), [
            'workflow_type' => 'admission_review',
            'title' => 'Reject Test',
        ])->assertRedirect();

        $workflow = Workflow::query()->where('institute_id', $institute->id)->firstOrFail();
        $this->actingAs($owner, 'institute_user')->post(route('workflows.transition', $workflow), ['status' => 'submitted']);
        $this->actingAs($owner, 'institute_user')->post(route('workflows.transition', $workflow), ['status' => 'under_review']);

        $this->actingAs($owner, 'institute_user')
            ->post(route('workflows.reject-step', $workflow), ['comment' => ''])
            ->assertSessionHasErrors('comment');

        $this->assertSame('under_review', $workflow->refresh()->status);
    }

    public function test_workflow_reject_step_rejects_workflow(): void
    {
        $institute = $this->institute();
        $owner = $this->user($institute, 'institute-owner', 'owner');

        $this->actingAs($owner, 'institute_user')->post(route('workflows.store'), [
            'workflow_type' => 'admission_review',
            'title' => 'Reject Flow',
        ])->assertRedirect();

        $workflow = Workflow::query()->where('institute_id', $institute->id)->firstOrFail();
        $this->actingAs($owner, 'institute_user')->post(route('workflows.transition', $workflow), ['status' => 'submitted']);
        $this->actingAs($owner, 'institute_user')->post(route('workflows.transition', $workflow), ['status' => 'under_review']);

        $this->actingAs($owner, 'institute_user')
            ->post(route('workflows.reject-step', $workflow), ['comment' => 'Missing docs'])
            ->assertRedirect();

        $workflow->refresh();
        $this->assertSame('rejected', $workflow->status);
        $this->assertTrue($workflow->isTerminal());
    }

    public function test_workflow_history_is_immutable_and_complete(): void
    {
        $institute = $this->institute();
        $owner = $this->user($institute, 'institute-owner', 'owner');

        $this->actingAs($owner, 'institute_user')->post(route('workflows.store'), [
            'workflow_type' => 'certificate_request',
            'title' => 'History Test',
        ])->assertRedirect();

        $workflow = Workflow::query()->where('institute_id', $institute->id)->firstOrFail();
        $this->actingAs($owner, 'institute_user')->post(route('workflows.transition', $workflow), ['status' => 'submitted']);

        $count = WorkflowHistory::where('workflow_id', $workflow->id)->count();
        $this->assertGreaterThanOrEqual(2, $count); // created + submitted

        $this->assertDatabaseHas('workflow_histories', [
            'workflow_id' => $workflow->id,
            'action' => 'submitted',
            'from_status' => 'draft',
            'to_status' => 'submitted',
        ]);
    }

    public function test_workflow_return_transition_allows_resubmission(): void
    {
        $institute = $this->institute();
        $owner = $this->user($institute, 'institute-owner', 'owner');

        $this->actingAs($owner, 'institute_user')->post(route('workflows.store'), [
            'workflow_type' => 'student_withdrawal',
            'title' => 'Return Test',
        ])->assertRedirect();

        $workflow = Workflow::query()->where('institute_id', $institute->id)->firstOrFail();
        $this->actingAs($owner, 'institute_user')->post(route('workflows.transition', $workflow), ['status' => 'submitted']);
        $this->actingAs($owner, 'institute_user')->post(route('workflows.transition', $workflow), ['status' => 'under_review']);

        $this->actingAs($owner, 'institute_user')
            ->post(route('workflows.transition', $workflow), ['status' => 'returned', 'comment' => 'Fix docs'])
            ->assertRedirect();
        $this->assertSame('returned', $workflow->refresh()->status);

        // returned -> submitted is valid.
        $this->actingAs($owner, 'institute_user')
            ->post(route('workflows.transition', $workflow), ['status' => 'submitted'])
            ->assertRedirect();
        $this->assertSame('submitted', $workflow->refresh()->status);
    }

    // ------------------------------------------------------- Authorization

    public function test_teacher_cannot_manage_workflows(): void
    {
        $institute = $this->institute();
        $teacher = $this->user($institute, 'teacher', 'teacher');

        $this->actingAs($teacher, 'institute_user')
            ->post(route('workflows.store'), ['workflow_type' => 'certificate_request', 'title' => 'Nope'])
            ->assertForbidden();
    }

    public function test_teacher_can_view_workflows(): void
    {
        $institute = $this->institute();
        $teacher = $this->user($institute, 'teacher', 'teacher');

        $this->actingAs($teacher, 'institute_user')
            ->get(route('workflows.index'))
            ->assertOk();
    }

    public function test_user_without_workflow_permission_is_forbidden(): void
    {
        $institute = $this->institute();
        $accountant = $this->user($institute, 'accountant', 'accountant');

        $this->actingAs($accountant, 'institute_user')
            ->get(route('workflows.index'))
            ->assertForbidden();
    }

    public function test_cross_tenant_workflow_is_not_accessible(): void
    {
        $institute = $this->institute('Inst A');
        $owner = $this->user($institute, 'institute-owner', 'ownerA');

        $this->actingAs($owner, 'institute_user')->post(route('workflows.store'), [
            'workflow_type' => 'certificate_request',
            'title' => 'Private Workflow',
        ])->assertRedirect();

        $workflow = Workflow::query()->where('institute_id', $institute->id)->firstOrFail();

        $foreign = $this->institute('Inst B');
        $foreignOwner = $this->user($foreign, 'institute-owner', 'ownerB');

        $this->actingAs($foreignOwner, 'institute_user')
            ->get(route('workflows.show', $workflow))
            ->assertNotFound();
    }

    public function test_cross_tenant_document_verification_is_blocked(): void
    {
        Storage::fake('public');
        $institute = $this->institute('Inst A');
        $owner = $this->user($institute, 'institute-owner', 'ownerA');
        $student = $this->student($institute);
        $category = $this->requiredCategory($institute, 'birth-cert-x');
        $document = $this->uploadDocument($owner, $student, $category);

        $foreign = $this->institute('Inst B');
        $foreignOwner = $this->user($foreign, 'institute-owner', 'ownerB');

        // Document is TenantScoped; a foreign user's tenant context hides it → 404.
        $this->actingAs($foreignOwner, 'institute_user')
            ->withHeaders(['Accept' => 'application/json'])
            ->post(route('documents.verify', $document))
            ->assertNotFound();
    }

    // -------------------------------------------------- Historical safety

    public function test_replacing_document_does_not_destroy_academic_or_version_history(): void
    {
        Storage::fake('public');
        $institute = $this->institute();
        $owner = $this->user($institute, 'institute-owner', 'owner');
        $student = $this->student($institute);
        $category = $this->requiredCategory($institute, 'safe-doc');
        $document = $this->uploadDocument($owner, $student, $category);

        $this->actingAs($owner, 'institute_user')->withHeaders(['Accept' => 'application/json'])
            ->post(route('documents.verify', $document))->assertOk();

        $this->actingAs($owner, 'institute_user')->withHeaders(['Accept' => 'application/json'])
            ->post(route('documents.replace', $document), ['file' => $this->pdf()])->assertOk();

        // Version 1 preserved.
        $this->assertSame(1, DocumentVersion::where('document_id', $document->id)->where('version', 1)->count());
        // Student row untouched.
        $this->assertDatabaseHas('students', ['id' => $student->id, 'status' => 'active']);
    }
}

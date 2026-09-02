<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Country;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\HrDepartment;
use App\Models\HrDesignation;
use App\Models\HrEmployee;
use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\Role;
use App\Services\HrEmployeeService;
use App\Support\BranchContext;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * HR-3 — Employee Document Management.
 *
 * Covers: upload, metadata, verification, rejection, expiry, required, replacement,
 * tenant/branch isolation, permissions, secure download, audit.
 */
class HrDocumentManagementTest extends TestCase
{
    use DatabaseTransactions;

    protected string $password = 'secret12345';

    protected function tearDown(): void
    {
        TenantContext::clear();
        BranchContext::clear();
        parent::tearDown();
    }

    private function country(): Country
    {
        return Country::withoutGlobalScopes()->firstOrCreate(
            ['iso2' => 'BD'],
            ['name' => 'Bangladesh', 'iso3' => 'BGD', 'phone_code' => '880', 'status' => true]
        );
    }

    private function institute(?Country $country = null): Institute
    {
        $country ??= $this->country();
        return Institute::create([
            'name' => 'HRDoc Inst '.uniqid(),
            'slug' => 'hrdoc-inst-'.uniqid(),
            'country' => $country->name,
            'country_id' => $country->id,
            'status' => 'active',
        ]);
    }

    private function branch(Institute $institute, string $name = 'Branch'): Branch
    {
        return Branch::create(['institute_id' => $institute->id, 'name' => $name.' '.uniqid(), 'status' => 'active']);
    }

    private function role(string $slug): Role
    {
        return Role::where('slug', $slug)->firstOrFail();
    }

    private function user(Institute $institute, string $roleSlug, ?int $branchId = null, ?string $email = null): InstituteUser
    {
        return InstituteUser::create([
            'institute_id' => $institute->id,
            'role_id' => $this->role($roleSlug)->id,
            'branch_id' => $branchId,
            'first_name' => ucfirst($roleSlug),
            'last_name' => 'User',
            'email' => $email ?? $roleSlug.'-'.uniqid().'@example.test',
            'phone' => '01700'.rand(100000, 999999),
            'password_hash' => bcrypt($this->password),
            'status' => 'active',
        ]);
    }

    private function employee(Institute $institute, ?int $branchId = null, ?int $actorId = null): HrEmployee
    {
        $svc = app(HrEmployeeService::class);
        return $svc->create([
            'first_name' => 'Emp',
            'last_name' => 'Doc '.uniqid(),
            'employment_status' => 'active',
            'employment_type' => 'full_time',
        ], $institute->id, $branchId, $actorId ?? $this->user($institute, 'institute-owner')->id);
    }

    private function hrCategory(string $slug): DocumentCategory
    {
        return DocumentCategory::where('slug', $slug)->firstOrFail();
    }

    private function pdf(): UploadedFile
    {
        return UploadedFile::fake()->create('resume.pdf', 200, 'application/pdf');
    }

    // ---------------------------------------------------------------- Lifecycle

    public function test_owner_uploads_hr_document_with_metadata(): void
    {
        Storage::fake('public');
        $institute = $this->institute();
        $branch = $this->branch($institute, 'Campus');
        $owner = $this->user($institute, 'institute-owner');
        $emp = $this->employee($institute, $branch->id, $owner->id);
        $cat = $this->hrCategory('hr-nid-passport');

        $response = $this->actingAs($owner, 'institute_user')
            ->withHeaders(['Accept' => 'application/json'])
            ->post(route('hr.employees.documents.store', $emp), [
                'category_id' => $cat->id,
                'file' => $this->pdf(),
                'title' => 'NID Copy',
                'document_number' => 'NID-12345',
                'issue_date' => '2024-01-01',
                'expiry_date' => '2030-01-01',
                'description' => 'Verified copy',
            ]);

        $response->assertStatus(201)->assertJson(['success' => true]);
        $doc = Document::query()->firstOrFail();
        $this->assertSame((int) $institute->id, (int) $doc->institute_id);
        $this->assertSame((int) $branch->id, (int) $doc->branch_id);
        $this->assertSame(HrEmployee::class, $doc->documentable_type);
        $this->assertSame((int) $emp->id, (int) $doc->documentable_id);
        $this->assertSame('NID Copy', $doc->title);
        $this->assertSame('NID-12345', $doc->document_number);
        $this->assertSame('2024-01-01', $doc->issue_date->format('Y-m-d'));
        $this->assertSame('2030-01-01', $doc->expiry_date->format('Y-m-d'));
        $this->assertSame('Verified copy', $doc->description);
        $this->assertSame(Document::VERIFICATION_PENDING, $doc->verification_status);
        $this->assertSame(1, (int) $doc->version);
        Storage::disk('public')->assertExists($doc->file_path);
        $this->assertDatabaseHas('audit_logs', ['module' => 'documents', 'action' => 'document_uploaded', 'record_id' => $doc->id]);
    }

    public function test_upload_rejects_invalid_category_for_hr_employee(): void
    {
        Storage::fake('public');
        $institute = $this->institute();
        $owner = $this->user($institute, 'institute-owner');
        $emp = $this->employee($institute, null, $owner->id);
        // birth-certificate is student only, not hr-employee
        $cat = DocumentCategory::where('slug', 'birth-certificate')->firstOrFail();

        $this->actingAs($owner, 'institute_user')
            ->withHeaders(['Accept' => 'application/json'])
            ->post(route('hr.employees.documents.store', $emp), [
                'category_id' => $cat->id,
                'file' => $this->pdf(),
            ])
            ->assertStatus(422);

        $this->assertSame(0, Document::query()->count());
    }

    public function test_categories_are_scoped_to_hr_employee(): void
    {
        $institute = $this->institute();
        $owner = $this->user($institute, 'institute-owner');
        $data = $this->actingAs($owner, 'institute_user')
            ->getJson(route('hr.documents.categories'))
            ->assertOk()
            ->json('data');
        $slugs = collect($data)->pluck('slug');
        $this->assertTrue($slugs->contains('hr-nid-passport'));
        $this->assertTrue($slugs->contains('hr-appointment-letter'));
        $this->assertFalse($slugs->contains('birth-certificate'));
        $this->assertFalse($slugs->contains('invoice'));
    }

    public function test_index_lists_and_filters_archived(): void
    {
        Storage::fake('public');
        $institute = $this->institute();
        $owner = $this->user($institute, 'institute-owner');
        $emp = $this->employee($institute, null, $owner->id);
        $cat = $this->hrCategory('hr-other');

        foreach (['A', 'B'] as $title) {
            $this->actingAs($owner, 'institute_user')
                ->withHeaders(['Accept' => 'application/json'])
                ->post(route('hr.employees.documents.store', $emp), [
                    'category_id' => $cat->id,
                    'file' => $this->pdf(),
                    'title' => $title,
                ])->assertStatus(201);
        }

        $docs = Document::query()->orderBy('id')->get();
        $this->assertCount(2, $docs);

        $this->actingAs($owner, 'institute_user')->postJson(route('hr.documents.archive', $docs->first()))->assertOk();

        $active = $this->actingAs($owner, 'institute_user')
            ->getJson(route('hr.employees.documents.index', $emp))->assertOk()->json('data');
        $this->assertCount(1, $active);

        $all = $this->actingAs($owner, 'institute_user')
            ->getJson(route('hr.employees.documents.index', $emp).'?include_archived=1')->assertOk()->json('data');
        $this->assertCount(2, $all);
    }

    public function test_metadata_update(): void
    {
        Storage::fake('public');
        $institute = $this->institute();
        $owner = $this->user($institute, 'institute-owner');
        $emp = $this->employee($institute, null, $owner->id);
        $cat = $this->hrCategory('hr-other');

        $this->actingAs($owner, 'institute_user')
            ->withHeaders(['Accept' => 'application/json'])
            ->post(route('hr.employees.documents.store', $emp), [
                'category_id' => $cat->id,
                'file' => $this->pdf(),
                'title' => 'Before',
            ])->assertStatus(201);

        $doc = Document::query()->firstOrFail();
        $newCat = $this->hrCategory('hr-cv-resume');

        $this->actingAs($owner, 'institute_user')
            ->patchJson(route('hr.documents.update', $doc), [
                'title' => 'After',
                'document_number' => 'REF-999',
                'issue_date' => '2025-01-01',
                'expiry_date' => '2026-01-01',
                'category_id' => $newCat->id,
                'description' => 'Updated',
            ])->assertOk()->assertJsonPath('data.title', 'After');

        $doc->refresh();
        $this->assertSame('After', $doc->title);
        $this->assertSame('REF-999', $doc->document_number);
        $this->assertSame('Updated', $doc->description);
        $this->assertSame($newCat->id, (int) $doc->category_id);
        $this->assertDatabaseHas('audit_logs', ['module' => 'documents', 'action' => 'document_updated', 'record_id' => $doc->id]);
    }

    public function test_verification_and_rejection(): void
    {
        Storage::fake('public');
        $institute = $this->institute();
        $owner = $this->user($institute, 'institute-owner');
        $emp = $this->employee($institute, null, $owner->id);
        $cat = $this->hrCategory('hr-appointment-letter');

        $this->actingAs($owner, 'institute_user')
            ->withHeaders(['Accept' => 'application/json'])
            ->post(route('hr.employees.documents.store', $emp), [
                'category_id' => $cat->id,
                'file' => $this->pdf(),
            ])->assertStatus(201);

        $doc = Document::query()->firstOrFail();

        $this->actingAs($owner, 'institute_user')
            ->postJson(route('hr.documents.verify', $doc), ['notes' => 'Checked'])
            ->assertOk()->assertJsonPath('data.verification_status', Document::VERIFICATION_VERIFIED);

        $doc->refresh();
        $this->assertTrue($doc->isVerified());
        $this->assertNotNull($doc->verified_at);
        $this->assertSame((int) $owner->id, (int) $doc->verified_by);
        $this->assertDatabaseHas('audit_logs', ['module' => 'documents', 'action' => 'document_verified', 'record_id' => $doc->id]);

        // Create second doc for rejection
        $this->actingAs($owner, 'institute_user')
            ->withHeaders(['Accept' => 'application/json'])
            ->post(route('hr.employees.documents.store', $emp), [
                'category_id' => $cat->id,
                'file' => $this->pdf(),
            ])->assertStatus(201);

        $doc2 = Document::query()->orderByDesc('id')->firstOrFail();

        $this->actingAs($owner, 'institute_user')
            ->postJson(route('hr.documents.reject', $doc2), ['reason' => 'Blurry scan'])
            ->assertOk()->assertJsonPath('data.verification_status', Document::VERIFICATION_REJECTED);

        $doc2->refresh();
        $this->assertTrue($doc2->isRejected());
        $this->assertSame('Blurry scan', $doc2->rejection_reason);
        $this->assertDatabaseHas('audit_logs', ['module' => 'documents', 'action' => 'document_rejected', 'record_id' => $doc2->id]);

        // Rejection without reason should 422
        $this->actingAs($owner, 'institute_user')
            ->postJson(route('hr.documents.reject', $doc2), ['reason' => ''])
            ->assertStatus(422);
    }

    public function test_expiry_detection(): void
    {
        Storage::fake('public');
        $institute = $this->institute();
        $owner = $this->user($institute, 'institute-owner');
        $emp = $this->employee($institute, null, $owner->id);
        $cat = $this->hrCategory('hr-professional-certificate');

        // Expired
        $this->actingAs($owner, 'institute_user')
            ->withHeaders(['Accept' => 'application/json'])
            ->post(route('hr.employees.documents.store', $emp), [
                'category_id' => $cat->id,
                'file' => $this->pdf(),
                'expiry_date' => now()->subDays(5)->toDateString(),
            ])->assertStatus(201);

        // Expiring soon (10 days)
        $this->actingAs($owner, 'institute_user')
            ->withHeaders(['Accept' => 'application/json'])
            ->post(route('hr.employees.documents.store', $emp), [
                'category_id' => $cat->id,
                'file' => $this->pdf(),
                'expiry_date' => now()->addDays(10)->toDateString(),
            ])->assertStatus(201);

        // Far future (not expiring soon)
        $this->actingAs($owner, 'institute_user')
            ->withHeaders(['Accept' => 'application/json'])
            ->post(route('hr.employees.documents.store', $emp), [
                'category_id' => $cat->id,
                'file' => $this->pdf(),
                'expiry_date' => now()->addDays(60)->toDateString(),
            ])->assertStatus(201);

        $expired = Document::query()->first();
        $this->assertTrue($expired->isExpired());
        $this->assertSame(Document::VERIFICATION_PENDING, $expired->verification_status);

        $expiring = $this->actingAs($owner, 'institute_user')
            ->getJson(route('hr.documents.expiring').'?days=30')->assertOk()->json('data');

        $this->assertCount(1, $expiring['expired']);
        $this->assertCount(1, $expiring['expiring_soon']);

        // Verify effective status: expired doc verified should show expired
        $expiredDoc = Document::query()->where('expiry_date', '<', now()->toDateString())->firstOrFail();
        $this->actingAs($owner, 'institute_user')->postJson(route('hr.documents.verify', $expiredDoc), [])->assertOk();
        $expiredDoc->refresh();
        $this->assertTrue($expiredDoc->isExpired());
        $this->assertSame(Document::VERIFICATION_VERIFIED, $expiredDoc->verification_status);
        $this->assertSame(Document::VERIFICATION_EXPIRED, $expiredDoc->effectiveVerificationStatus());
    }

    public function test_missing_required_documents_detection(): void
    {
        Storage::fake('public');
        $institute = $this->institute();
        $owner = $this->user($institute, 'institute-owner');
        $emp = $this->employee($institute, null, $owner->id);

        // No docs yet → missing required should list employee
        $missing = $this->actingAs($owner, 'institute_user')->getJson(route('hr.documents.missing'))->assertOk()->json('data');
        $this->assertCount(1, $missing);
        $this->assertSame((int) $emp->id, $missing[0]['employee']['id']);
        $this->assertGreaterThan(0, count($missing[0]['missing']));

        // Upload one required doc
        $cat = $this->hrCategory('hr-nid-passport');
        $this->actingAs($owner, 'institute_user')
            ->withHeaders(['Accept' => 'application/json'])
            ->post(route('hr.employees.documents.store', $emp), [
                'category_id' => $cat->id,
                'file' => $this->pdf(),
            ])->assertStatus(201);

        $missing2 = $this->actingAs($owner, 'institute_user')->getJson(route('hr.documents.missing'))->assertOk()->json('data');
        // Still missing others, but not the uploaded one
        if (count($missing2) > 0) {
            $slugs = collect($missing2[0]['missing'])->pluck('slug');
            $this->assertFalse($slugs->contains('hr-nid-passport'));
        }
    }

    public function test_replacement_preserves_version(): void
    {
        Storage::fake('public');
        $institute = $this->institute();
        $owner = $this->user($institute, 'institute-owner');
        $emp = $this->employee($institute, null, $owner->id);
        $cat = $this->hrCategory('hr-other');

        $this->actingAs($owner, 'institute_user')
            ->withHeaders(['Accept' => 'application/json'])
            ->post(route('hr.employees.documents.store', $emp), [
                'category_id' => $cat->id,
                'file' => UploadedFile::fake()->create('v1.pdf', 100, 'application/pdf'),
            ])->assertStatus(201);

        $doc = Document::query()->firstOrFail();
        $oldPath = $doc->file_path;
        $this->assertSame(1, (int) $doc->version);

        $this->actingAs($owner, 'institute_user')
            ->withHeaders(['Accept' => 'application/json'])
            ->post(route('hr.documents.replace', $doc), [
                'file' => UploadedFile::fake()->create('v2.pdf', 120, 'application/pdf'),
            ])->assertOk()->assertJsonPath('data.version', 2);

        $doc->refresh();
        $this->assertSame(2, (int) $doc->version);
        $this->assertSame(Document::VERIFICATION_PENDING, $doc->verification_status);
        $this->assertDatabaseHas('document_versions', ['document_id' => $doc->id, 'version' => 1, 'file_path' => $oldPath]);
        Storage::disk('public')->assertExists($oldPath);
        Storage::disk('public')->assertExists($doc->file_path);
    }

    public function test_secure_download(): void
    {
        Storage::fake('public');
        $institute = $this->institute();
        $owner = $this->user($institute, 'institute-owner');
        $emp = $this->employee($institute, null, $owner->id);
        $cat = $this->hrCategory('hr-other');

        $this->actingAs($owner, 'institute_user')
            ->withHeaders(['Accept' => 'application/json'])
            ->post(route('hr.employees.documents.store', $emp), [
                'category_id' => $cat->id,
                'file' => $this->pdf(),
            ])->assertStatus(201);

        $doc = Document::query()->firstOrFail();

        $this->actingAs($owner, 'institute_user')->get(route('hr.documents.download', $doc))->assertOk()->assertDownload('resume.pdf');

        // Cross-tenant cannot download
        $otherInst = $this->institute();
        $otherOwner = $this->user($otherInst, 'institute-owner');
        $this->actingAs($otherOwner, 'institute_user')->get(route('hr.documents.download', $doc))->assertNotFound();
    }

    public function test_tenant_isolation(): void
    {
        Storage::fake('public');
        $a = $this->institute();
        $b = $this->institute();
        $ownerA = $this->user($a, 'institute-owner');
        $ownerB = $this->user($b, 'institute-owner');
        $empA = $this->employee($a, null, $ownerA->id);
        $cat = $this->hrCategory('hr-other');

        $this->actingAs($ownerA, 'institute_user')
            ->withHeaders(['Accept' => 'application/json'])
            ->post(route('hr.employees.documents.store', $empA), [
                'category_id' => $cat->id,
                'file' => $this->pdf(),
            ])->assertStatus(201);

        $doc = Document::query()->firstOrFail();

        $this->actingAs($ownerB, 'institute_user')
            ->getJson(route('hr.employees.documents.index', $empA))->assertNotFound();

        $this->actingAs($ownerB, 'institute_user')
            ->get(route('hr.documents.download', $doc))->assertNotFound();

        $this->actingAs($ownerB, 'institute_user')
            ->withHeaders(['Accept' => 'application/json'])
            ->post(route('hr.employees.documents.store', $empA), [
                'category_id' => $cat->id,
                'file' => $this->pdf(),
            ])->assertNotFound();

        $this->actingAs($ownerB, 'institute_user')
            ->postJson(route('hr.documents.verify', $doc))->assertNotFound();
    }

    public function test_branch_isolation(): void
    {
        Storage::fake('public');
        $institute = $this->institute();
        $branchA = $this->branch($institute, 'Branch A');
        $branchB = $this->branch($institute, 'Branch B');
        $owner = $this->user($institute, 'institute-owner');
        $mgrA = $this->user($institute, 'branch-manager', $branchA->id);
        $mgrB = $this->user($institute, 'branch-manager', $branchB->id);
        $empA = $this->employee($institute, $branchA->id, $owner->id);
        $empB = $this->employee($institute, $branchB->id, $owner->id);
        $cat = $this->hrCategory('hr-other');

        $this->actingAs($mgrA, 'institute_user')
            ->withHeaders(['Accept' => 'application/json'])
            ->post(route('hr.employees.documents.store', $empA), [
                'category_id' => $cat->id,
                'file' => $this->pdf(),
            ])->assertStatus(201);

        $docA = Document::query()->where('documentable_id', $empA->id)->firstOrFail();
        $this->assertSame((int) $branchA->id, (int) $docA->branch_id);

        // Manager B cannot see A's docs via index or download
        $this->actingAs($mgrB, 'institute_user')
            ->getJson(route('hr.employees.documents.index', $empA))->assertNotFound();

        $this->actingAs($mgrB, 'institute_user')
            ->get(route('hr.documents.download', $docA))->assertNotFound();

        // Manager B also cannot view expiring/missing for Branch A
        $expiringB = $this->actingAs($mgrB, 'institute_user')->getJson(route('hr.documents.expiring'))->assertOk()->json('data');
        // Should not contain A's expired docs
        $this->assertCount(0, $expiringB['expired']);

        // Manager A can
        $this->actingAs($mgrA, 'institute_user')->getJson(route('hr.employees.documents.index', $empA))->assertOk()->assertJsonCount(1, 'data');
        $this->actingAs($mgrA, 'institute_user')->get(route('hr.documents.download', $docA))->assertOk();

        // Owner sees everything
        $this->actingAs($owner, 'institute_user')->getJson(route('hr.employees.documents.index', $empA))->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_permission_enforcement(): void
    {
        Storage::fake('public');
        $institute = $this->institute();
        $owner = $this->user($institute, 'institute-owner');
        $receptionist = $this->user($institute, 'receptionist');
        $emp = $this->employee($institute, null, $owner->id);
        $cat = $this->hrCategory('hr-other');

        // Receptionist has no hr.document.* (only documents.* but not hr)
        $this->actingAs($receptionist, 'institute_user')->getJson(route('hr.documents.categories'))->assertForbidden();
        $this->actingAs($receptionist, 'institute_user')->getJson(route('hr.employees.documents.index', $emp))->assertForbidden();
        $this->actingAs($receptionist, 'institute_user')
            ->withHeaders(['Accept' => 'application/json'])
            ->post(route('hr.employees.documents.store', $emp), [
                'category_id' => $cat->id,
                'file' => $this->pdf(),
            ])->assertForbidden();

        // Create doc as owner then try verify as receptionist
        $this->actingAs($owner, 'institute_user')
            ->withHeaders(['Accept' => 'application/json'])
            ->post(route('hr.employees.documents.store', $emp), [
                'category_id' => $cat->id,
                'file' => $this->pdf(),
            ])->assertStatus(201);
        $doc = Document::query()->firstOrFail();

        $this->actingAs($receptionist, 'institute_user')->postJson(route('hr.documents.verify', $doc))->assertForbidden();
        $this->actingAs($receptionist, 'institute_user')->postJson(route('hr.documents.reject', $doc), ['reason' => 'bad'])->assertForbidden();
        $this->actingAs($receptionist, 'institute_user')->get(route('hr.documents.download', $doc))->assertForbidden();
    }

    public function test_file_safety_rejects_executable_and_oversized(): void
    {
        Storage::fake('public');
        $institute = $this->institute();
        $owner = $this->user($institute, 'institute-owner');
        $emp = $this->employee($institute, null, $owner->id);
        $cat = $this->hrCategory('hr-other');

        $this->actingAs($owner, 'institute_user')
            ->withHeaders(['Accept' => 'application/json'])
            ->post(route('hr.employees.documents.store', $emp), [
                'category_id' => $cat->id,
                'file' => UploadedFile::fake()->create('payload.exe', 200, 'application/x-msdownload'),
            ])->assertStatus(422)->assertJsonValidationErrors('file');

        $this->actingAs($owner, 'institute_user')
            ->withHeaders(['Accept' => 'application/json'])
            ->post(route('hr.employees.documents.store', $emp), [
                'category_id' => $cat->id,
                'file' => UploadedFile::fake()->create('huge.pdf', 11000, 'application/pdf'),
            ])->assertStatus(422)->assertJsonValidationErrors('file');

        $this->assertSame(0, Document::query()->count());
    }

    public function test_audit_logging_covers_all_actions(): void
    {
        Storage::fake('public');
        $institute = $this->institute();
        $owner = $this->user($institute, 'institute-owner');
        $emp = $this->employee($institute, null, $owner->id);
        $cat = $this->hrCategory('hr-other');

        $this->actingAs($owner, 'institute_user')
            ->withHeaders(['Accept' => 'application/json'])
            ->post(route('hr.employees.documents.store', $emp), [
                'category_id' => $cat->id,
                'file' => $this->pdf(),
            ])->assertStatus(201);
        $doc = Document::query()->firstOrFail();
        $this->assertDatabaseHas('audit_logs', ['module' => 'documents', 'action' => 'document_uploaded', 'record_id' => $doc->id]);

        $this->actingAs($owner, 'institute_user')
            ->withHeaders(['Accept' => 'application/json'])
            ->post(route('hr.documents.replace', $doc), [
                'file' => UploadedFile::fake()->create('v2.pdf', 120, 'application/pdf'),
            ])->assertOk();
        $this->assertDatabaseHas('audit_logs', ['module' => 'documents', 'action' => 'document_replaced', 'record_id' => $doc->id]);

        $this->actingAs($owner, 'institute_user')->postJson(route('hr.documents.verify', $doc))->assertOk();
        $this->assertDatabaseHas('audit_logs', ['module' => 'documents', 'action' => 'document_verified', 'record_id' => $doc->id]);

        $this->actingAs($owner, 'institute_user')->postJson(route('hr.documents.archive', $doc))->assertOk();
        $this->assertDatabaseHas('audit_logs', ['module' => 'documents', 'action' => 'document_archived', 'record_id' => $doc->id]);

        $this->actingAs($owner, 'institute_user')->deleteJson(route('hr.documents.destroy', $doc))->assertOk();
        $this->assertDatabaseHas('audit_logs', ['module' => 'documents', 'action' => 'document_deleted', 'record_id' => $doc->id]);
    }

    public function test_archive_and_delete(): void
    {
        Storage::fake('public');
        $institute = $this->institute();
        $owner = $this->user($institute, 'institute-owner');
        $emp = $this->employee($institute, null, $owner->id);
        $cat = $this->hrCategory('hr-other');

        $this->actingAs($owner, 'institute_user')
            ->withHeaders(['Accept' => 'application/json'])
            ->post(route('hr.employees.documents.store', $emp), [
                'category_id' => $cat->id,
                'file' => $this->pdf(),
            ])->assertStatus(201);

        $doc = Document::query()->firstOrFail();
        $this->actingAs($owner, 'institute_user')->postJson(route('hr.documents.archive', $doc))->assertOk()->assertJsonPath('data.status', Document::STATUS_ARCHIVED);
        $this->assertTrue($doc->refresh()->isArchived());

        $this->actingAs($owner, 'institute_user')->deleteJson(route('hr.documents.destroy', $doc))->assertOk();
        $this->assertNull(Document::query()->find($doc->id));
        $this->assertNotNull(Document::withTrashed()->find($doc->id));
        Storage::disk('public')->assertExists($doc->file_path);
    }

    public function test_versions_endpoint(): void
    {
        Storage::fake('public');
        $institute = $this->institute();
        $owner = $this->user($institute, 'institute-owner');
        $emp = $this->employee($institute, null, $owner->id);
        $cat = $this->hrCategory('hr-other');

        $this->actingAs($owner, 'institute_user')
            ->withHeaders(['Accept' => 'application/json'])
            ->post(route('hr.employees.documents.store', $emp), [
                'category_id' => $cat->id,
                'file' => $this->pdf(),
            ])->assertStatus(201);
        $doc = Document::query()->firstOrFail();
        $this->actingAs($owner, 'institute_user')
            ->withHeaders(['Accept' => 'application/json'])
            ->post(route('hr.documents.replace', $doc), [
                'file' => UploadedFile::fake()->create('v2.pdf', 120, 'application/pdf'),
            ])->assertOk();

        $this->actingAs($owner, 'institute_user')
            ->getJson(route('hr.documents.versions', $doc))
            ->assertOk()->assertJsonPath('data.current_version', 2)
            ->assertJsonCount(1, 'data.versions');
    }

    public function test_dashboard_shows_expired_and_missing(): void
    {
        Storage::fake('public');
        $institute = $this->institute();
        $owner = $this->user($institute, 'institute-owner');
        $emp = $this->employee($institute, null, $owner->id);
        $cat = $this->hrCategory('hr-professional-certificate');

        $this->actingAs($owner, 'institute_user')
            ->withHeaders(['Accept' => 'application/json'])
            ->post(route('hr.employees.documents.store', $emp), [
                'category_id' => $cat->id,
                'file' => $this->pdf(),
                'expiry_date' => now()->subDay()->toDateString(),
            ])->assertStatus(201);

        $this->actingAs($owner, 'institute_user')->get(route('hr.dashboard'))->assertOk()->assertSee('Expired Documents');
    }
}

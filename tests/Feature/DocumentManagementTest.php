<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Country;
use App\Models\Course;
use App\Models\CourseCategory;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\Role;
use App\Models\Student;
use App\Support\BranchContext;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Step 46 — Document Management: upload → validate → store → link → categorize
 * → download → replace (version++) → archive/delete → audit, plus the
 * authorization, tenant and branch isolation of the reusable documents layer.
 */
class DocumentManagementTest extends TestCase
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

    private function institute(string $name = 'Docs Inst'): Institute
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
        return Branch::create([
            'institute_id' => $institute->id,
            'name' => $name,
            'status' => 'active',
        ]);
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
            'student_id_number' => 'RP'.strtoupper(str()->random(8)),
            'first_name' => 'Doc',
            'last_name' => 'Student',
            'status' => 'active',
            'admission_date' => '2026-01-01',
        ]);
    }

    private function course(Institute $institute): Course
    {
        $category = CourseCategory::create([
            'name' => 'Doc Course Cat',
            'slug' => 'doc-course-cat-'.uniqid(),
            'subject_type' => 'professional',
            'institute_id' => $institute->id,
            'status' => 'active',
        ]);

        return Course::create([
            'institute_id' => $institute->id,
            'course_code' => 'DOC'.mt_rand(1000, 9999),
            'name' => 'Document Management',
            'slug' => 'document-management-'.uniqid(),
            'category_id' => $category->id,
            'fee' => 10000,
            'status' => 'active',
        ]);
    }

    private function category(string $slug): DocumentCategory
    {
        return DocumentCategory::where('slug', $slug)->firstOrFail();
    }

    private function pdf(): UploadedFile
    {
        return UploadedFile::fake()->create('resume.pdf', 200, 'application/pdf');
    }

    private function uploadStore(InstituteUser $actor, array $overrides = []): TestResponse
    {
        return $this->actingAs($actor, 'institute_user')
            ->withHeaders(['Accept' => 'application/json'])
            ->post(route('documents.store'), array_merge([
                'entity' => 'student',
                'entity_id' => $this->student($actor->institute)->id,
                'category_id' => $this->category('photo')->id,
                'file' => $this->pdf(),
                'title' => 'Profile photo',
            ], $overrides));
    }

    // ----------------------------------------------------------- Lifecycle

    public function test_owner_uploads_document_linked_to_student(): void
    {
        Storage::fake('public');
        $institute = $this->institute();
        $branch = $this->branch($institute, 'Campus A');
        $owner = $this->user($institute, 'institute-owner', 'owner');
        $student = $this->student($institute, $branch);

        $response = $this->actingAs($owner, 'institute_user')
            ->withHeaders(['Accept' => 'application/json'])
            ->post(route('documents.store'), [
                'entity' => 'student',
                'entity_id' => $student->id,
                'category_id' => $this->category('photo')->id,
                'file' => $this->pdf(),
                'title' => 'Profile photo',
                'description' => 'Latest headshot',
            ]);

        $response->assertStatus(201)->assertJson(['success' => true]);

        $document = Document::query()->firstOrFail();
        $this->assertSame((int) $institute->id, (int) $document->institute_id);
        $this->assertSame((int) $branch->id, (int) $document->branch_id);
        $this->assertSame(Student::class, $document->documentable_type);
        $this->assertSame((int) $student->id, (int) $document->documentable_id);
        $this->assertSame($this->category('photo')->id, (int) $document->category_id);
        $this->assertSame(1, (int) $document->version);
        $this->assertSame(Document::STATUS_ACTIVE, $document->status);
        $this->assertSame('resume.pdf', $document->original_filename);
        $this->assertSame((int) $owner->id, (int) $document->uploaded_by);

        Storage::disk('public')->assertExists($document->file_path);

        $this->assertDatabaseHas('audit_logs', [
            'institute_id' => $institute->id,
            'module' => 'documents',
            'action' => 'document_uploaded',
            'record_id' => $document->id,
        ]);
    }

    public function test_upload_rejects_disallowed_mime_type(): void
    {
        Storage::fake('public');
        $institute = $this->institute();
        $owner = $this->user($institute, 'institute-owner', 'owner');
        $student = $this->student($institute);

        $this->actingAs($owner, 'institute_user')
            ->withHeaders(['Accept' => 'application/json'])
            ->post(route('documents.store'), [
                'entity' => 'student',
                'entity_id' => $student->id,
                'category_id' => $this->category('photo')->id,
                'file' => UploadedFile::fake()->create('payload.exe', 200, 'application/x-msdownload'),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('file');

        $this->assertSame(0, Document::query()->count());
    }

    public function test_upload_rejects_oversized_file(): void
    {
        Storage::fake('public');
        $institute = $this->institute();
        $owner = $this->user($institute, 'institute-owner', 'owner');
        $student = $this->student($institute);

        $this->actingAs($owner, 'institute_user')
            ->withHeaders(['Accept' => 'application/json'])
            ->post(route('documents.store'), [
                'entity' => 'student',
                'entity_id' => $student->id,
                'category_id' => $this->category('photo')->id,
                'file' => UploadedFile::fake()->create('huge.pdf', 11000, 'application/pdf'),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('file');

        $this->assertSame(0, Document::query()->count());
    }

    public function test_upload_rejects_unknown_entity_and_category(): void
    {
        Storage::fake('public');
        $institute = $this->institute();
        $owner = $this->user($institute, 'institute-owner', 'owner');
        $student = $this->student($institute);

        $this->actingAs($owner, 'institute_user')
            ->withHeaders(['Accept' => 'application/json'])
            ->post(route('documents.store'), [
                'entity' => 'bogus',
                'entity_id' => $student->id,
                'category_id' => $this->category('photo')->id,
                'file' => $this->pdf(),
            ])
            ->assertStatus(422);

        // A category that does not apply to a student (Resume is staff/teacher only).
        $this->actingAs($owner, 'institute_user')
            ->withHeaders(['Accept' => 'application/json'])
            ->post(route('documents.store'), [
                'entity' => 'student',
                'entity_id' => $student->id,
                'category_id' => $this->category('resume')->id,
                'file' => $this->pdf(),
            ])
            ->assertStatus(422);

        $this->assertSame(0, Document::query()->count());
    }

    public function test_categories_are_scoped_to_entity(): void
    {
        $institute = $this->institute();
        $owner = $this->user($institute, 'institute-owner', 'owner');

        $studentSlugs = $this->actingAs($owner, 'institute_user')
            ->getJson(route('documents.categories').'?entity=student')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->json('data');

        $studentNames = collect($studentSlugs)->pluck('slug');
        $this->assertTrue($studentNames->contains('photo'));
        $this->assertTrue($studentNames->contains('other'));
        $this->assertFalse($studentNames->contains('resume'));
        $this->assertFalse($studentNames->contains('invoice'));
        $this->assertFalse($studentNames->contains('trade-license'));

        $teacherSlugs = $this->actingAs($owner, 'institute_user')
            ->getJson(route('documents.categories').'?entity=teacher')
            ->assertOk()
            ->json('data');

        $teacherNames = collect($teacherSlugs)->pluck('slug');
        $this->assertTrue($teacherNames->contains('resume'));
        $this->assertFalse($teacherNames->contains('birth-certificate'));

        $courseSlugs = $this->actingAs($owner, 'institute_user')
            ->getJson(route('documents.categories').'?entity=course')
            ->assertOk()
            ->json('data');

        $courseNames = collect($courseSlugs)->pluck('slug');
        $this->assertTrue($courseNames->contains('contract'));
        $this->assertFalse($courseNames->contains('resume'));
    }

    public function test_index_lists_documents_and_filters_archived(): void
    {
        Storage::fake('public');
        $institute = $this->institute();
        $owner = $this->user($institute, 'institute-owner', 'owner');
        $student = $this->student($institute);

        $this->actingAs($owner, 'institute_user')
            ->withHeaders(['Accept' => 'application/json'])
            ->post(route('documents.store'), [
                'entity' => 'student',
                'entity_id' => $student->id,
                'category_id' => $this->category('photo')->id,
                'file' => $this->pdf(),
            ])
            ->assertStatus(201);

        $this->actingAs($owner, 'institute_user')
            ->withHeaders(['Accept' => 'application/json'])
            ->post(route('documents.store'), [
                'entity' => 'student',
                'entity_id' => $student->id,
                'category_id' => $this->category('other')->id,
                'file' => $this->pdf(),
            ])
            ->assertStatus(201);

        $documents = Document::query()->orderBy('id')->get();
        $this->assertCount(2, $documents);

        $this->actingAs($owner, 'institute_user')
            ->postJson(route('documents.archive', $documents->first()))
            ->assertOk();

        $active = $this->actingAs($owner, 'institute_user')
            ->getJson(route('documents.index').'?entity=student&id='.$student->id)
            ->assertOk()
            ->json('data');

        $this->assertCount(1, $active);

        $all = $this->actingAs($owner, 'institute_user')
            ->getJson(route('documents.index').'?entity=student&id='.$student->id.'&include_archived=1')
            ->assertOk()
            ->json('data');

        $this->assertCount(2, $all);
    }

    public function test_download_serves_the_original_file(): void
    {
        Storage::fake('public');
        $institute = $this->institute();
        $owner = $this->user($institute, 'institute-owner', 'owner');
        $student = $this->student($institute);

        $this->actingAs($owner, 'institute_user')
            ->withHeaders(['Accept' => 'application/json'])
            ->post(route('documents.store'), [
                'entity' => 'student',
                'entity_id' => $student->id,
                'category_id' => $this->category('photo')->id,
                'file' => $this->pdf(),
            ])
            ->assertStatus(201);

        $document = Document::query()->firstOrFail();

        $this->actingAs($owner, 'institute_user')
            ->get(route('documents.download', $document))
            ->assertOk()
            ->assertDownload('resume.pdf');
    }

    public function test_replace_increments_version_and_removes_old_file(): void
    {
        Storage::fake('public');
        $institute = $this->institute();
        $owner = $this->user($institute, 'institute-owner', 'owner');
        $student = $this->student($institute);

        $this->actingAs($owner, 'institute_user')
            ->withHeaders(['Accept' => 'application/json'])
            ->post(route('documents.store'), [
                'entity' => 'student',
                'entity_id' => $student->id,
                'category_id' => $this->category('photo')->id,
                'file' => UploadedFile::fake()->create('v1.png', 100, 'image/png'),
            ])
            ->assertStatus(201);

        $document = Document::query()->firstOrFail();
        $oldPath = $document->file_path;
        Storage::disk('public')->assertExists($oldPath);

        $response = $this->actingAs($owner, 'institute_user')
            ->withHeaders(['Accept' => 'application/json'])
            ->post(route('documents.replace', $document), [
                'file' => UploadedFile::fake()->create('v2.png', 120, 'image/png'),
            ]);

        $response->assertOk()->assertJsonPath('data.version', 2);

        $document->refresh();
        $this->assertSame(2, (int) $document->version);
        $this->assertSame('v2.png', $document->original_filename);
        $this->assertNotSame($oldPath, $document->file_path);

        // Step 51: replaced versions are preserved (history is never destroyed).
        Storage::disk('public')->assertExists($oldPath);
        Storage::disk('public')->assertExists($document->file_path);
        $this->assertDatabaseHas('document_versions', [
            'document_id' => $document->id,
            'version' => 1,
            'file_path' => $oldPath,
        ]);

        $audit = DB::table('audit_logs')
            ->where('module', 'documents')
            ->where('action', 'document_replaced')
            ->where('record_id', $document->id)
            ->first();

        $this->assertNotNull($audit);
        $this->assertSame(1, json_decode((string) $audit->old_values, true)['version']);
        $this->assertSame(2, json_decode((string) $audit->new_values, true)['version']);
    }

    public function test_update_changes_metadata_and_category(): void
    {
        Storage::fake('public');
        $institute = $this->institute();
        $owner = $this->user($institute, 'institute-owner', 'owner');
        $student = $this->student($institute);

        $this->actingAs($owner, 'institute_user')
            ->withHeaders(['Accept' => 'application/json'])
            ->post(route('documents.store'), [
                'entity' => 'student',
                'entity_id' => $student->id,
                'category_id' => $this->category('photo')->id,
                'file' => $this->pdf(),
                'title' => 'Before',
            ])
            ->assertStatus(201);

        $document = Document::query()->firstOrFail();

        $this->actingAs($owner, 'institute_user')
            ->patchJson(route('documents.update', $document), [
                'title' => 'After',
                'description' => 'Renamed',
                'category_id' => $this->category('birth-certificate')->id,
            ])
            ->assertOk()
            ->assertJsonPath('data.title', 'After');

        $document->refresh();
        $this->assertSame('After', $document->title);
        $this->assertSame('Renamed', $document->description);
        $this->assertSame($this->category('birth-certificate')->id, (int) $document->category_id);

        $this->assertDatabaseHas('audit_logs', [
            'module' => 'documents',
            'action' => 'document_updated',
            'record_id' => $document->id,
        ]);
    }

    public function test_archive_and_restore_lifecycle(): void
    {
        Storage::fake('public');
        $institute = $this->institute();
        $owner = $this->user($institute, 'institute-owner', 'owner');
        $student = $this->student($institute);

        $this->actingAs($owner, 'institute_user')
            ->withHeaders(['Accept' => 'application/json'])
            ->post(route('documents.store'), [
                'entity' => 'student',
                'entity_id' => $student->id,
                'category_id' => $this->category('photo')->id,
                'file' => $this->pdf(),
            ])
            ->assertStatus(201);

        $document = Document::query()->firstOrFail();

        $this->actingAs($owner, 'institute_user')
            ->postJson(route('documents.archive', $document))
            ->assertOk()
            ->assertJsonPath('data.status', Document::STATUS_ARCHIVED);

        $this->assertTrue($document->refresh()->isArchived());

        $this->actingAs($owner, 'institute_user')
            ->postJson(route('documents.restore', $document))
            ->assertOk()
            ->assertJsonPath('data.status', Document::STATUS_ACTIVE);

        $this->assertFalse($document->refresh()->isArchived());

        $this->assertDatabaseHas('audit_logs', ['module' => 'documents', 'action' => 'document_archived', 'record_id' => $document->id]);
        $this->assertDatabaseHas('audit_logs', ['module' => 'documents', 'action' => 'document_restored', 'record_id' => $document->id]);
    }

    public function test_soft_delete_keeps_file_and_force_delete_removes_it(): void
    {
        Storage::fake('public');
        $institute = $this->institute();
        $owner = $this->user($institute, 'institute-owner', 'owner');
        $student = $this->student($institute);

        $this->actingAs($owner, 'institute_user')
            ->withHeaders(['Accept' => 'application/json'])
            ->post(route('documents.store'), [
                'entity' => 'student',
                'entity_id' => $student->id,
                'category_id' => $this->category('photo')->id,
                'file' => $this->pdf(),
            ])
            ->assertStatus(201);

        $document = Document::query()->firstOrFail();
        Storage::disk('public')->assertExists($document->file_path);

        $this->actingAs($owner, 'institute_user')
            ->deleteJson(route('documents.destroy', $document))
            ->assertOk();

        $this->assertNotNull(Document::withTrashed()->find($document->id));
        $this->assertNull(Document::query()->find($document->id));
        Storage::disk('public')->assertExists($document->file_path);

        $this->actingAs($owner, 'institute_user')
            ->deleteJson(route('documents.force-destroy', $document))
            ->assertOk();

        $this->assertNull(Document::withTrashed()->find($document->id));
        Storage::disk('public')->assertMissing($document->file_path);

        $this->assertDatabaseHas('audit_logs', ['module' => 'documents', 'action' => 'document_deleted', 'record_id' => $document->id]);
        $this->assertDatabaseHas('audit_logs', ['module' => 'documents', 'action' => 'document_force_deleted', 'record_id' => $document->id]);
    }

    public function test_present_includes_uploader_name(): void
    {
        Storage::fake('public');
        $institute = $this->institute();
        $owner = $this->user($institute, 'institute-owner', 'owner');
        $student = $this->student($institute);

        $response = $this->actingAs($owner, 'institute_user')
            ->withHeaders(['Accept' => 'application/json'])
            ->post(route('documents.store'), [
                'entity' => 'student',
                'entity_id' => $student->id,
                'category_id' => $this->category('photo')->id,
                'file' => $this->pdf(),
            ])
            ->assertStatus(201);

        $response->assertJsonPath('data.uploaded_by', $owner->first_name.' '.$owner->last_name);
        $response->assertJsonPath('data.category', 'Photo');
    }

    // ----------------------------------------------------- Authorization

    public function test_teacher_can_view_but_not_manage(): void
    {
        Storage::fake('public');
        $institute = $this->institute();
        $owner = $this->user($institute, 'institute-owner', 'owner');
        $teacher = $this->user($institute, 'teacher', 'teacher');
        $student = $this->student($institute);

        $this->actingAs($owner, 'institute_user')
            ->withHeaders(['Accept' => 'application/json'])
            ->post(route('documents.store'), [
                'entity' => 'student',
                'entity_id' => $student->id,
                'category_id' => $this->category('photo')->id,
                'file' => $this->pdf(),
            ])
            ->assertStatus(201);

        $document = Document::query()->firstOrFail();

        $this->actingAs($teacher, 'institute_user')
            ->getJson(route('documents.index').'?entity=student&id='.$student->id)
            ->assertOk();

        $this->actingAs($teacher, 'institute_user')
            ->get(route('documents.download', $document))
            ->assertOk();

        $this->actingAs($teacher, 'institute_user')
            ->withHeaders(['Accept' => 'application/json'])
            ->post(route('documents.store'), [
                'entity' => 'student',
                'entity_id' => $student->id,
                'category_id' => $this->category('photo')->id,
                'file' => $this->pdf(),
            ])
            ->assertForbidden();

        $this->actingAs($teacher, 'institute_user')
            ->postJson(route('documents.archive', $document))
            ->assertForbidden();
    }

    public function test_user_without_documents_permission_is_forbidden(): void
    {
        Storage::fake('public');
        $institute = $this->institute();
        $accountant = $this->user($institute, 'accountant', 'accountant');
        $student = $this->student($institute);

        $this->actingAs($accountant, 'institute_user')
            ->getJson(route('documents.categories').'?entity=student')
            ->assertForbidden();

        $this->actingAs($accountant, 'institute_user')
            ->getJson(route('documents.index').'?entity=student&id='.$student->id)
            ->assertForbidden();
    }

    // ------------------------------------------------------ Isolation

    public function test_cross_tenant_documents_are_not_accessible(): void
    {
        Storage::fake('public');
        $a = $this->institute('Inst A');
        $b = $this->institute('Inst B');
        $ownerA = $this->user($a, 'institute-owner', 'owner-a');
        $ownerB = $this->user($b, 'institute-owner', 'owner-b');
        $studentA = $this->student($a);

        $this->actingAs($ownerA, 'institute_user')
            ->withHeaders(['Accept' => 'application/json'])
            ->post(route('documents.store'), [
                'entity' => 'student',
                'entity_id' => $studentA->id,
                'category_id' => $this->category('photo')->id,
                'file' => $this->pdf(),
            ])
            ->assertStatus(201);

        $document = Document::query()->firstOrFail();

        $this->actingAs($ownerB, 'institute_user')
            ->getJson(route('documents.index').'?entity=student&id='.$studentA->id)
            ->assertNotFound();

        $this->actingAs($ownerB, 'institute_user')
            ->get(route('documents.download', $document))
            ->assertNotFound();

        $this->actingAs($ownerB, 'institute_user')
            ->withHeaders(['Accept' => 'application/json'])
            ->post(route('documents.store'), [
                'entity' => 'student',
                'entity_id' => $studentA->id,
                'category_id' => $this->category('photo')->id,
                'file' => $this->pdf(),
            ])
            ->assertNotFound();

        $this->actingAs($ownerB, 'institute_user')
            ->postJson(route('documents.archive', $document))
            ->assertNotFound();
    }

    public function test_branch_isolation_for_documents(): void
    {
        Storage::fake('public');
        $institute = $this->institute();
        $branchA = $this->branch($institute, 'Branch A');
        $branchB = $this->branch($institute, 'Branch B');
        $owner = $this->user($institute, 'institute-owner', 'owner');
        $managerA = $this->user($institute, 'branch-manager', 'manager-a', $branchA);
        $managerB = $this->user($institute, 'branch-manager', 'manager-b', $branchB);
        $course = $this->course($institute);

        // The course is institute-level; a branch manager's upload inherits their branch.
        $this->actingAs($managerA, 'institute_user')
            ->withHeaders(['Accept' => 'application/json'])
            ->post(route('documents.store'), [
                'entity' => 'course',
                'entity_id' => $course->id,
                'category_id' => $this->category('contract')->id,
                'file' => $this->pdf(),
            ])
            ->assertStatus(201);

        $document = Document::query()->firstOrFail();
        $this->assertSame((int) $branchA->id, (int) $document->branch_id);

        // Manager B cannot see or download the branch-A document.
        $this->actingAs($managerB, 'institute_user')
            ->getJson(route('documents.index').'?entity=course&id='.$course->id)
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->actingAs($managerB, 'institute_user')
            ->get(route('documents.download', $document))
            ->assertNotFound();

        // Manager A (owning branch) can see and download it.
        $this->actingAs($managerA, 'institute_user')
            ->getJson(route('documents.index').'?entity=course&id='.$course->id)
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->actingAs($managerA, 'institute_user')
            ->get(route('documents.download', $document))
            ->assertOk();

        // The owner (no branch) sees everything.
        $this->actingAs($owner, 'institute_user')
            ->getJson(route('documents.index').'?entity=course&id='.$course->id)
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }
}

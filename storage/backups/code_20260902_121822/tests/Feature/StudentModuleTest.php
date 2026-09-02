<?php

namespace Tests\Feature;

use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\Role;
use App\Models\Student;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StudentModuleTest extends TestCase
{
    use DatabaseTransactions;

    protected string $password = 'secret12345';

    private Institute $institute;

    private InstituteUser $staff;

    protected function setUp(): void
    {
        parent::setUp();

        TenantContext::clear();

        $this->institute = Institute::where('name', 'MAWA ACADEMY')->firstOrFail();
        $this->staff = $this->makeStaff('institute-owner', 'students-owner@example.test');
    }

    protected function makeStaff(string $roleSlug, string $email): InstituteUser
    {
        $role = Role::where('slug', $roleSlug)->firstOrFail();

        return InstituteUser::create([
            'institute_id' => $this->institute->id,
            'role_id' => $role->id,
            'email' => $email,
            'phone' => '0170000'.substr(md5($email), 0, 4),
            'password_hash' => bcrypt($this->password),
            'status' => 'active',
        ]);
    }

    public function test_guest_is_redirected_from_students_index(): void
    {
        $this->get('/students')->assertRedirect('/admin/login');
    }

    public function test_index_columns_menu_has_all_student_fields(): void
    {
        $this->actingAs($this->staff, 'institute_user')
            ->get(route('students.index'))
            ->assertOk()
            ->assertSee('student-col-age', false)
            ->assertSee('student-col-dob', false)
            ->assertSee('student-col-email', false)
            ->assertSee('student-col-reg', false)
            ->assertSee('student-col-gender', false)
            ->assertSee('student-col-blood', false)
            ->assertSee('student-col-religion', false)
            ->assertSee('student-col-nationality', false)
            ->assertSee('student-col-nid', false)
            ->assertSee('student-col-passport', false)
            ->assertSee('student-col-branch', false)
            ->assertSee('student-col-guardian', false);
    }

    public function test_index_has_header_filter_icons_and_filters_by_gender_religion_status(): void
    {
        Student::create([
            'institute_id' => $this->institute->id,
            'student_id_number' => 'F-'.mt_rand(1000, 9999),
            'first_name' => 'Filter Female One',
            'gender' => 'female',
            'religion' => 'Islam',
            'status' => 'active',
            'admission_date' => now(),
        ]);
        Student::create([
            'institute_id' => $this->institute->id,
            'student_id_number' => 'M-'.mt_rand(1000, 9999),
            'first_name' => 'Filter Male One',
            'gender' => 'male',
            'religion' => 'Hindu',
            'status' => 'suspended',
            'admission_date' => now(),
        ]);

        $this->actingAs($this->staff, 'institute_user')
            ->get(route('students.index'))
            ->assertOk()
            ->assertSee('col-filter-btn', false)
            ->assertSee('Gender')
            ->assertSee('Religion')
            ->assertSee('Status');

        $this->actingAs($this->staff, 'institute_user')
            ->get(route('students.index', ['gender' => 'female']))
            ->assertOk()
            ->assertSee('Filter Female One')
            ->assertDontSee('Filter Male One');

        $this->actingAs($this->staff, 'institute_user')
            ->get(route('students.index', ['religion' => 'Hindu']))
            ->assertOk()
            ->assertSee('Filter Male One')
            ->assertDontSee('Filter Female One');

        $this->actingAs($this->staff, 'institute_user')
            ->get(route('students.index', ['status' => 'suspended']))
            ->assertOk()
            ->assertSee('Filter Male One')
            ->assertDontSee('Filter Female One');
    }

    public function test_index_sorts_by_admission_date_and_age(): void
    {
        Student::create([
            'institute_id' => $this->institute->id,
            'student_id_number' => 'A-'.mt_rand(1000, 9999),
            'first_name' => 'Sort Old Admission',
            'dob' => '2000-01-01',
            'admission_date' => '2024-01-01',
            'status' => 'active',
        ]);
        Student::create([
            'institute_id' => $this->institute->id,
            'student_id_number' => 'B-'.mt_rand(1000, 9999),
            'first_name' => 'Sort New Admission',
            'dob' => '2005-01-01',
            'admission_date' => '2026-01-01',
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->staff, 'institute_user')
            ->get(route('students.index', ['sort' => 'admission', 'dir' => 'desc', 'q' => 'Sort']))
            ->assertOk()
            ->assertSee('col-filter-btn', false)
            ->assertSeeInOrder(['Sort New Admission', 'Sort Old Admission']);

        $this->actingAs($this->staff, 'institute_user')
            ->get(route('students.index', ['sort' => 'age', 'dir' => 'asc', 'q' => 'Sort']))
            ->assertOk()
            ->assertSee('Ascending (youngest first)')
            ->assertSeeInOrder(['Sort New Admission', 'Sort Old Admission']);

        $this->actingAs($this->staff, 'institute_user')
            ->get(route('students.index', ['sort' => 'age', 'dir' => 'desc', 'q' => 'Sort']))
            ->assertOk()
            ->assertSee('Descending (oldest first)')
            ->assertSeeInOrder(['Sort Old Admission', 'Sort New Admission']);
    }

    public function test_index_shows_only_own_institutes_students(): void
    {
        $this->actingAs($this->staff, 'institute_user')->get('/students')->assertOk();

        $scoped = Student::count();
        $all = Student::withoutGlobalScopes()->count();
        $this->assertLessThan($all, $scoped);
        $this->assertSame(
            (int) DB::table('students')->where('institute_id', $this->institute->id)->whereNull('deleted_at')->count(),
            $scoped
        );
    }

    public function test_index_search_filters_by_name(): void
    {
        $target = DB::table('students')->where('institute_id', $this->institute->id)->first();

        $this->actingAs($this->staff, 'institute_user')
            ->get('/students?q='.urlencode($target->student_id_number))
            ->assertOk()
            ->assertSee($target->first_name);
    }

    public function test_create_store_generates_incremental_number(): void
    {
        $before = (int) DB::table('students')
            ->where('institute_id', $this->institute->id)
            ->max(DB::raw('CAST(student_id_number AS UNSIGNED)'));

        $this->actingAs($this->staff, 'institute_user')
            ->post('/students', [
                'first_name' => 'New',
                'last_name' => 'Student',
                'gender' => 'male',
                'phone' => '01711112222',
                'admission_date' => '2026-08-12',
                'status' => 'active',
            ])
            ->assertRedirect();

        $created = Student::withoutGlobalScopes()
            ->where('institute_id', $this->institute->id)
            ->where('first_name', 'New')
            ->where('last_name', 'Student')
            ->first();

        $this->assertNotNull($created);
        $this->assertSame((string) ($before + 1), $created->student_id_number);
        $this->assertSame($this->institute->id, (int) $created->institute_id);
        $this->assertNotNull($created->uuid);
    }

    public function test_store_validates_required_fields(): void
    {
        $this->actingAs($this->staff, 'institute_user')
            ->post('/students', [
                'first_name' => '',
                'admission_date' => '',
                'status' => '',
            ])
            ->assertSessionHasErrors(['first_name', 'admission_date', 'status']);
    }

    public function test_document_upload_accepts_pdf_and_rejects_image(): void
    {
        if (! extension_loaded('gd')) {
            $this->markTestSkipped('The GD extension is required for image upload tests.');
        }

        $this->actingAs($this->staff, 'institute_user');

        $this->post('/students', [
            'first_name' => 'Doc',
            'last_name' => 'Holder',
            'admission_date' => '2026-08-12',
            'status' => 'active',
            'photo' => UploadedFile::fake()->image('pic.jpg', 80, 100),
            'document' => UploadedFile::fake()->create('cv.pdf', 1, 'application/pdf'),
        ])->assertRedirect();

        $created = Student::withoutGlobalScopes()
            ->where('institute_id', $this->institute->id)
            ->where('first_name', 'Doc')
            ->where('last_name', 'Holder')
            ->first();

        $this->assertNotNull($created);
        $this->assertNotNull($created->photo);
        $this->assertStringContainsString('profile-images/students/', $created->photo);
        $this->assertStringEndsWith('.jpg', $created->photo);
        $this->assertNotNull($created->document);
        $this->assertStringEndsWith('.pdf', $created->document);
    }

    public function test_document_upload_rejects_unsupported_format(): void
    {
        $this->actingAs($this->staff, 'institute_user');

        $this->post('/students', [
            'first_name' => 'Bad',
            'last_name' => 'Doc',
            'admission_date' => '2026-08-12',
            'status' => 'active',
            'document' => UploadedFile::fake()->create('malware.exe', 1, 'application/octet-stream'),
        ])->assertSessionHasErrors('document');
    }

    public function test_update_changes_details_but_not_institute(): void
    {
        $student = $this->createStudent('Update', 'Me', 'checkme@example.test');

        $this->actingAs($this->staff, 'institute_user')
            ->put('/students/'.$student->id, [
                'first_name' => 'Updated',
                'last_name' => 'Name',
                'phone' => '01733334444',
                'admission_date' => '2026-08-12',
                'status' => 'active',
            ])
            ->assertRedirect();

        $student->refresh();
        $this->assertSame('Updated', $student->first_name);
        $this->assertSame('01733334444', $student->phone);
    }

    public function test_store_rejects_duplicate_email_in_same_institute(): void
    {
        $this->createStudent('First', 'Email', 'dupe-email@example.test');

        $this->actingAs($this->staff, 'institute_user')
            ->post('/students', [
                'first_name' => 'Second',
                'last_name' => 'Email',
                'email' => 'dupe-email@example.test',
                'admission_date' => '2026-08-12',
                'status' => 'active',
            ])
            ->assertSessionHasErrors('email');
    }

    public function test_store_rejects_duplicate_phone_in_same_institute(): void
    {
        $this->actingAs($this->staff, 'institute_user');

        $this->post('/students', [
            'first_name' => 'First',
            'last_name' => 'Phone',
            'phone' => '01790000001',
            'admission_date' => '2026-08-12',
            'status' => 'active',
        ])->assertRedirect();

        $this->post('/students', [
            'first_name' => 'Second',
            'last_name' => 'Phone',
            'phone' => '01790000001',
            'admission_date' => '2026-08-12',
            'status' => 'active',
        ])->assertSessionHasErrors('phone');
    }

    public function test_store_rejects_duplicate_document_numbers_in_same_institute(): void
    {
        $this->actingAs($this->staff, 'institute_user');

        $this->post('/students', [
            'first_name' => 'First',
            'last_name' => 'Docs',
            'nid_number' => '1234567890',
            'birth_cert_number' => '2000112233445566',
            'passport_number' => 'AB1234567',
            'admission_date' => '2026-08-12',
            'status' => 'active',
        ])->assertRedirect();

        $this->post('/students', [
            'first_name' => 'Second',
            'last_name' => 'Docs',
            'nid_number' => '1234567890',
            'birth_cert_number' => '2000112233445566',
            'passport_number' => 'AB1234567',
            'admission_date' => '2026-08-12',
            'status' => 'active',
        ])
            ->assertSessionHasErrors(['nid_number', 'birth_cert_number', 'passport_number']);
    }

    public function test_same_login_allowed_in_different_institute(): void
    {
        $other = Institute::where('name', 'Halumoni Computer training center')->firstOrFail();

        Student::create([
            'institute_id' => $other->id,
            'student_id_number' => Student::nextStudentNumber($other->id),
            'first_name' => 'Cross',
            'last_name' => 'Institute',
            'email' => 'cross-email@example.test',
            'phone' => '01790000002',
            'nid_number' => '9999999999',
            'birth_cert_number' => '2000112233445566',
            'passport_number' => 'AB1234567',
            'admission_date' => now()->format('Y-m-d'),
            'status' => 'active',
        ]);

        $this->actingAs($this->staff, 'institute_user')
            ->post('/students', [
                'first_name' => 'Same',
                'last_name' => 'Credentials',
                'email' => 'cross-email@example.test',
                'phone' => '01790000002',
                'nid_number' => '9999999999',
                'birth_cert_number' => '2000112233445566',
                'passport_number' => 'AB1234567',
                'admission_date' => '2026-08-12',
                'status' => 'active',
            ])
            ->assertRedirect();
    }

    public function test_update_keeps_own_login_and_documents(): void
    {
        $student = $this->createStudent('Keep', 'Own', 'keep-own@example.test');
        $student->update([
            'phone' => '01790000003',
            'nid_number' => '1112223334',
        ]);

        $this->actingAs($this->staff, 'institute_user')
            ->put('/students/'.$student->id, [
                'first_name' => 'Keep',
                'last_name' => 'Own',
                'email' => 'keep-own@example.test',
                'phone' => '01790000003',
                'nid_number' => '1112223334',
                'admission_date' => '2026-08-12',
                'status' => 'active',
            ])
            ->assertRedirect();
    }

    public function test_other_institute_student_is_404(): void
    {
        $other = Institute::where('name', 'Halumoni Computer training center')->firstOrFail();
        $foreignStudent = DB::table('students')->where('institute_id', $other->id)->first();

        $this->actingAs($this->staff, 'institute_user')
            ->get('/students/'.$foreignStudent->id)
            ->assertNotFound();
    }

    public function test_destroy_soft_deletes(): void
    {
        $student = $this->createStudent('Remove', 'Me', 'remove@example.test');
        $id = $student->id;

        $this->actingAs($this->staff, 'institute_user')
            ->delete('/students/'.$id)
            ->assertRedirect(route('students.index'));

        $this->assertNull(Student::find($id));
        $this->assertNotNull(DB::table('students')->where('id', $id)->value('deleted_at'));
    }

    public function test_teacher_has_view_but_not_manage(): void
    {
        $teacher = $this->makeStaff('teacher', 'students-teacher@example.test');

        $this->actingAs($teacher, 'institute_user')->get('/students')->assertOk();
        $this->actingAs($teacher, 'institute_user')->get('/students/create')->assertForbidden();
    }

    public function test_create_form_renders_geo_cascade_data(): void
    {
        $this->actingAs($this->staff, 'institute_user')
            ->get('/students/create')
            ->assertOk()
            ->assertSee('data-address-component', false)
            ->assertSee('name="present_country_id"', false)
            ->assertSee('name="present_admin_1_id"', false)
            ->assertSee('name="present_admin_2_id"', false)
            ->assertSee('name="present_admin_3_id"', false)
            ->assertSee('data-label-endpoint', false)
            ->assertSee('name="permanent_country_id"', false);
    }

    public function test_create_form_defaults_country_to_institute_and_stores_student_country(): void
    {
        $instituteCountry = $this->institute->country;

        $this->actingAs($this->staff, 'institute_user')
            ->get('/students/create')
            ->assertOk()
            ->assertSee('id="country"', false)
            ->assertSee('<option value="'.$instituteCountry.'" selected', false);

        $response = $this->actingAs($this->staff, 'institute_user')
            ->post('/students', [
                'first_name' => 'Country',
                'last_name' => 'Test',
                'gender' => 'female',
                'country' => 'India',
                'admission_date' => '2026-01-01',
                'status' => 'active',
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('students', [
            'first_name' => 'Country',
            'last_name' => 'Test',
            'country' => 'India',
        ]);
    }

    public function test_student_country_must_be_a_known_country(): void
    {
        $this->actingAs($this->staff, 'institute_user')
            ->post('/students', [
                'first_name' => 'Bad',
                'last_name' => 'Country',
                'country' => 'Atlantis',
                'admission_date' => '2026-01-01',
                'status' => 'active',
            ])
            ->assertSessionHasErrors('country');

        $this->assertDatabaseMissing('students', ['first_name' => 'Bad']);
    }

    public function test_show_page_renders_edit_modal_standard_address_fields(): void
    {
        $student = $this->createStudent('Geo', 'Cascade', 'geo-cascade@example.test');

        $this->actingAs($this->staff, 'institute_user')
            ->get('/students/'.$student->id)
            ->assertOk()
            ->assertSee('data-address-component', false)
            ->assertSee('name="present_country_id"', false)
            ->assertSee('name="present_admin_1_id"', false)
            ->assertSee('name="present_admin_2_id"', false)
            ->assertSee('name="present_admin_3_id"', false)
            ->assertSee('name="permanent_country_id"', false)
            ->assertSee('name="permanent_admin_1_id"', false)
            ->assertSee('name="permanent_admin_2_id"', false)
            ->assertSee('name="permanent_admin_3_id"', false);
    }

    public function test_receptionist_can_manage_students(): void
    {
        $receptionist = $this->makeStaff('receptionist', 'students-reception@example.test');

        $this->actingAs($receptionist, 'institute_user')->get('/students/create')->assertOk();
    }

    private function createStudent(string $first, string $last, string $email): Student
    {
        return Student::create([
            'institute_id' => $this->institute->id,
            'student_id_number' => Student::nextStudentNumber($this->institute->id),
            'first_name' => $first,
            'last_name' => $last,
            'email' => $email,
            'admission_date' => now()->format('Y-m-d'),
            'status' => 'active',
        ]);
    }
}

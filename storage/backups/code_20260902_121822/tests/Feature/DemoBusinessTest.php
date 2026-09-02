<?php

namespace Tests\Feature;

use App\Models\Institute;
use App\Models\Membership;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DemoBusinessTest extends TestCase
{
    use DatabaseTransactions;

    private function runSeeder(): void
    {
        $this->artisan('demo:seed-all')->assertSuccessful();
    }

    public function test_creates_all_27_businesses(): void
    {
        $this->runSeeder();

        $this->assertDatabaseCount('institutes', 27);
        $this->assertEquals(27, User::where('account_type', 'owner')->count());
    }

    public function test_each_business_has_one_owner(): void
    {
        $this->runSeeder();

        $owners = User::where('account_type', 'owner')->get();
        $this->assertCount(27, $owners);

        $ownerRoleId = \App\Models\Role::where('slug', 'institute-owner')->value('id');

        foreach ($owners as $owner) {
            $membership = Membership::where('user_id', $owner->id)
                ->where('role_id', $ownerRoleId)
                ->first();

            $this->assertNotNull($membership, "Owner {$owner->email} has no owner membership");
        }
    }

    public function test_owner_password_auth_works(): void
    {
        $this->runSeeder();

        $owner = User::where('email', 'School@gmail.com')->first();
        $this->assertNotNull($owner);
        $this->assertTrue(Hash::check('12345678', $owner->getAuthPassword()));
    }

    public function test_tenants_are_isolated(): void
    {
        $this->runSeeder();

        $a = Institute::where('slug', 'school-demo')->first();
        $b = Institute::where('slug', 'hospital-demo')->first();

        $this->assertNotNull($a);
        $this->assertNotNull($b);
        $this->assertNotEquals($a->id, $b->id);

        $studentsA = \App\Models\Student::where('institute_id', $a->id)->count();
        $studentsB = \App\Models\Student::where('institute_id', $b->id)->count();

        $this->assertGreaterThan(0, $studentsA);
        $this->assertEquals(0, $studentsB);
    }

    public function test_idempotent_re_run_skips(): void
    {
        $this->runSeeder();
        $count1 = Institute::count();

        $output = $this->artisan('demo:seed-all');
        $output->assertSuccessful();

        $this->assertEquals($count1, Institute::count());
    }

    public function test_all_industries_represented(): void
    {
        $this->runSeeder();

        $industries = Institute::pluck('industry')->unique()->sort()->values()->all();
        $expected = [
            'education', 'healthcare', 'information_technology', 'finance',
            'retail', 'manufacturing', 'real_estate', 'transport',
            'restaurant', 'hotels', 'personal_finance', 'other',
        ];

        $this->assertEqualsCanonicalizing($expected, $industries);
    }

    public function test_education_institutes_have_students_and_teachers(): void
    {
        $this->runSeeder();

        $eduIds = Institute::where('industry', 'education')->pluck('id');

        $students = \App\Models\Student::whereIn('institute_id', $eduIds)->count();
        $teachers = \App\Models\TeacherProfile::whereIn('institute_id', $eduIds)->count();

        $this->assertGreaterThan(0, $students);
        $this->assertGreaterThan(0, $teachers);
    }

    public function test_non_education_institutes_have_no_students(): void
    {
        $this->runSeeder();

        $nonEduIds = Institute::where('industry', '!=', 'education')->pluck('id');

        $students = \App\Models\Student::whereIn('institute_id', $nonEduIds)->count();
        $this->assertEquals(0, $students);
    }
}

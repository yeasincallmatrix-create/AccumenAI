<?php

namespace Tests\Feature;

use App\Models\Institute;
use App\Models\Membership;
use App\Models\Role;
use App\Models\User;
use App\Services\ModuleAccessService;
use App\Support\Workspace;
use Database\Seeders\EducationPermissionSeeder;
use Database\Seeders\EducationSubModuleSeeder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class EducationSubModuleAccessTest extends TestCase
{
    use DatabaseTransactions;

    private Institute $institute;
    private User $owner;
    private ModuleAccessService $moduleAccess;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);

        (new EducationSubModuleSeeder)->run();
        (new EducationPermissionSeeder)->run();

        $this->institute = Institute::create([
            'name' => 'Edu SubMod Test',
            'slug' => 'edu-submod-' . uniqid(),
            'industry' => 'education',
            'sub_industry' => 'school',
            'country' => 'Bangladesh',
            'status' => 'active',
        ]);

        $this->owner = User::factory()->create([
            'account_type' => 'owner',
            'email_verified_at' => now(),
            'status' => 'active',
        ]);

        $roleId = Role::where('slug', 'institute-owner')->whereNull('institute_id')->value('id')
            ?? Role::where('slug', 'staff')->whereNull('institute_id')->value('id');

        Membership::create([
            'user_id' => $this->owner->id,
            'institution_id' => $this->institute->id,
            'role_id' => $roleId,
            'status' => 'active',
        ]);

        $this->moduleAccess = app(ModuleAccessService::class);

        $this->actingAs($this->owner, 'web');
        Workspace::set($this->institute->id);
    }

    private function enableModule(string $key): void
    {
        $this->moduleAccess->enableModule($this->institute, $key);
    }

    private function disableModule(string $key): void
    {
        $this->moduleAccess->disableModule($this->institute, $key);
    }

    public function test_students_route_blocked_when_students_disabled(): void
    {
        $this->enableModule('education');
        $this->disableModule('education.students');

        $response = $this->get(route('students.index'));
        $response->assertStatus(403);
    }

    public function test_students_route_works_when_students_enabled(): void
    {
        $this->enableModule('education');
        $this->enableModule('education.students');

        $response = $this->get(route('students.index'));
        $response->assertSuccessful();
    }

    public function test_exams_route_blocked_when_exams_disabled(): void
    {
        $this->enableModule('education');
        $this->disableModule('education.exams');

        $response = $this->get(route('exams.index'));
        $response->assertStatus(403);
    }

    public function test_exams_route_works_when_exams_enabled(): void
    {
        $this->enableModule('education');
        $this->enableModule('education.exams');

        $response = $this->get(route('exams.index'));
        $response->assertSuccessful();
    }

    public function test_all_sub_modules_blocked_when_parent_disabled(): void
    {
        $this->disableModule('education');
        $this->enableModule('education.students');
        $this->enableModule('education.classes');
        $this->enableModule('education.exams');

        $routes = [
            'students.index',
            'exams.index',
        ];

        foreach ($routes as $routeName) {
            $response = $this->get(route($routeName));
            $response->assertStatus(403);
        }
    }

    public function test_attendance_blocked_when_disabled(): void
    {
        $this->enableModule('education');
        $this->disableModule('education.attendance');

        $response = $this->get(route('academic-attendance.mark.index'));
        $response->assertStatus(403);
    }

    public function test_analytics_blocked_when_disabled(): void
    {
        $this->enableModule('education');
        $this->disableModule('education.analytics');

        $response = $this->get(route('academic.analytics.index'));
        $response->assertStatus(403);
    }
}

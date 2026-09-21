<?php

namespace Tests\Feature\TrainingCenter;

use App\Models\Institute;
use App\Models\Membership;
use App\Models\Role;
use App\Models\User;
use App\Services\ModuleAccessService;
use App\Support\Workspace;
use Database\Seeders\TrainingCenterSubModuleSeeder;
use Database\Seeders\TrainingCenterPermissionSeeder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class TrainingCenterModuleAccessTest extends TestCase
{
    use DatabaseTransactions;

    private Institute $institute;
    private ModuleAccessService $moduleAccess;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);

        (new TrainingCenterSubModuleSeeder)->run();
        (new TrainingCenterPermissionSeeder)->run();

        $this->institute = Institute::create([
            'name' => 'TC Access Test',
            'slug' => 'tc-access-' . uniqid(),
            'industry' => 'training_center',
            'sub_industry' => 'training_institute',
            'status' => 'active',
        ]);

        $owner = User::factory()->create([
            'account_type' => 'owner',
            'email_verified_at' => now(),
            'status' => 'active',
        ]);

        $roleId = Role::where('slug', 'institute-owner')->whereNull('institute_id')->value('id')
            ?? Role::where('slug', 'staff')->whereNull('institute_id')->value('id');

        Membership::create([
            'user_id' => $owner->id,
            'institution_id' => $this->institute->id,
            'role_id' => $roleId,
            'status' => 'active',
        ]);

        $this->moduleAccess = app(ModuleAccessService::class);

        $this->actingAs($owner, 'web');
        Workspace::set($this->institute->id);
    }

    public function test_training_route_requires_module_access(): void
    {
        app(ModuleAccessService::class)->disableModule($this->institute, 'training_center');

        $response = $this->get(route('training.attendance.index'));
        $response->assertStatus(403);
    }

    public function test_institute_without_training_center_gets_403(): void
    {
        $other = Institute::create([
            'name' => 'Other',
            'slug' => 'other-' . uniqid(),
            'industry' => 'education',
            'status' => 'active',
        ]);

        $user = User::factory()->create([
            'account_type' => 'owner',
            'email_verified_at' => now(),
            'status' => 'active',
        ]);

        $roleId = Role::where('slug', 'institute-owner')->whereNull('institute_id')->value('id');
        Membership::create([
            'user_id' => $user->id,
            'institution_id' => $other->id,
            'role_id' => $roleId,
            'status' => 'active',
        ]);

        $this->actingAs($user, 'web');
        Workspace::set($other->id);

        $this->moduleAccess->enableModule($other, 'training_center');

        $response = $this->get(route('training.attendance.index'));
        $response->assertStatus(403);
    }

    public function test_institute_with_training_center_can_access(): void
    {
        $this->moduleAccess->enableModule($this->institute, 'training_center');

        $response = $this->get(route('training.attendance.index'));
        $response->assertSuccessful();
    }

    public function test_disabled_module_blocks_route(): void
    {
        $this->moduleAccess->enableModule($this->institute, 'training_center');
        $this->moduleAccess->disableModule($this->institute, 'training_center');

        $response = $this->get(route('training.attendance.index'));
        $response->assertStatus(403);
    }

    public function test_enabled_module_grants_access(): void
    {
        $this->moduleAccess->enableModule($this->institute, 'training_center');

        $routes = [
            'training.attendance.index',
            'training.exams.index',
            'training.fees.index',
            'training.reports.index',
        ];

        foreach ($routes as $routeName) {
            $response = $this->get(route($routeName));
            $response->assertSuccessful();
        }
    }

    public function test_owner_bypasses_check(): void
    {
        $this->moduleAccess->enableModule($this->institute, 'training_center');

        $response = $this->get(route('training.attendance.index'));
        $response->assertSuccessful();
    }
}

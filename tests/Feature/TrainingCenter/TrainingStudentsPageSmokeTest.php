<?php

namespace Tests\Feature\TrainingCenter;

use App\Models\Institute;
use App\Models\Membership;
use App\Models\Role;
use App\Models\User;
use App\Services\ModuleAccessService;
use App\Support\Workspace;
use Database\Seeders\TrainingCenterPermissionSeeder;
use Database\Seeders\TrainingCenterSubModuleSeeder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class TrainingStudentsPageSmokeTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);

        (new TrainingCenterSubModuleSeeder)->run();
        (new TrainingCenterPermissionSeeder)->run();
    }

    public function test_training_students_index_renders_without_error(): void
    {
        $institute = Institute::create([
            'name' => 'TC Students Smoke',
            'slug' => 'tc-students-smoke-' . uniqid(),
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
            'institution_id' => $institute->id,
            'role_id' => $roleId,
            'status' => 'active',
        ]);

        app(ModuleAccessService::class)->enableModule($institute, 'training_center');

        \App\Models\Training\TrainingStudent::create([
            'institute_id' => $institute->id,
            'first_name' => 'Smoke',
            'last_name' => 'Trainee',
            'email' => 'smoke.' . uniqid() . '@example.test',
            'status' => 'active',
            'admission_date' => now()->toDateString(),
            'dob' => now()->subYears(20)->toDateString(),
            'nid_number' => '1234567890',
            'present_address' => 'Test Road 1',
        ]);

        $this->actingAs($owner, 'web');
        Workspace::set($institute->id);

        $this->get(route('training.students.index'))
            ->assertSuccessful()
            ->assertSee('EDIT_DATA', false);
    }
}

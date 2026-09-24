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

class TrainingAdjacentPagesSmokeTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);

        (new TrainingCenterSubModuleSeeder)->run();
        (new TrainingCenterPermissionSeeder)->run();
    }

    public function test_adjacent_training_pages_render(): void
    {
        $institute = Institute::create([
            'name' => 'TC Adjacent Smoke',
            'slug' => 'tc-adjacent-' . uniqid(),
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

        $this->actingAs($owner, 'web');
        Workspace::set($institute->id);

        $routes = [
            'training.students.index',
            'training.exams.index',
            'training.batches.index',
            'training.classes.index',
            'training.attendance.index',
            'training.enrollments.index',
            'training.certificates.index',
            'training.fees.index',
            'training.marks.index',
            'training.results.index',
            'training.reports.index',
            'training.settings.index',
        ];

        $failures = [];
        foreach ($routes as $routeName) {
            $response = $this->get(route($routeName));
            if (! $response->isSuccessful()) {
                $failures[] = $routeName . ' => ' . $response->status()
                    . ($response->exception ? ' | ' . $response->exception->getMessage() : '');
            }
        }

        $this->assertSame([], $failures, "Non-200 training pages:\n" . implode("\n", $failures));
    }
}

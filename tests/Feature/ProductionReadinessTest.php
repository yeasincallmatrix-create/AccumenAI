<?php

namespace Tests\Feature;

use App\Models\Institute;
use App\Models\Membership;
use App\Models\User;
use App\Services\Accounting\AccountingSetupService;
use App\Services\MembershipService;
use App\Services\UserAccountService;
use App\Support\BranchContext;
use App\Support\TenantContext;
use App\Support\Workspace;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ProductionReadinessTest extends TestCase
{
    use DatabaseTransactions;

    protected string $password = 'secret12345';

    protected function setUp(): void
    {
        parent::setUp();

        TenantContext::clear();
        BranchContext::clear();
        Workspace::clear();
    }

    protected function owner(string $email): User
    {
        return (new UserAccountService)->registerOwner([
            'name' => 'Step100 Owner',
            'first_name' => 'Step100',
            'last_name' => 'Owner',
            'email' => $email,
            'password_hash' => bcrypt($this->password),
            'status' => 'active',
        ]);
    }

    protected function institute(string $name): Institute
    {
        return Institute::create([
            'name' => $name.' '.uniqid(),
            'slug' => \Illuminate\Support\Str::slug($name.' '.uniqid()),
            'status' => 'active',
        ]);
    }

    protected function roleId(string $slug): int
    {
        return \App\Models\Role::where('slug', $slug)->firstOrFail()->id;
    }

    protected function assign(User $user, Institute $institute, string $roleSlug, array $attributes = []): Membership
    {
        return (new MembershipService)->assign($user, $institute->id, $this->roleId($roleSlug), $attributes);
    }

    protected function asUser(User $user, int $workspaceId): static
    {
        return $this->withSession([Workspace::SESSION_KEY => $workspaceId])->actingAs($user, 'web');
    }

    public function test_health_endpoint_returns_ok(): void
    {
        $response = $this->getJson('/health');

        $response->assertOk();
        $response->assertJsonStructure(['status', 'checks', 'timestamp']);
    }

    public function test_health_endpoint_checks_database(): void
    {
        $response = $this->getJson('/health');

        $response->assertOk();
        $response->assertJsonPath('checks.database.healthy', true);
    }

    public function test_health_endpoint_checks_cache(): void
    {
        $response = $this->getJson('/health');

        $response->assertOk();
        $response->assertJsonPath('checks.cache.healthy', true);
    }

    public function test_production_dashboard_renders(): void
    {
        $mawa = $this->institute('MAWA ACADEMY');
        $owner = $this->owner('step100-prod@example.test');
        $this->assign($owner, $mawa, 'institute-owner');

        $this->asUser($owner, (int) $mawa->id)
            ->get(route('accounting.production.index'))
            ->assertOk()
            ->assertSee('Production Dashboard');
    }

    public function test_performance_dashboard_renders(): void
    {
        $mawa = $this->institute('MAWA ACADEMY');
        $owner = $this->owner('step100-perf@example.test');
        $this->assign($owner, $mawa, 'institute-owner');

        $this->asUser($owner, (int) $mawa->id)
            ->get(route('accounting.production.performance'))
            ->assertOk()
            ->assertSee('Performance Metrics');
    }

    public function test_health_check_command_runs(): void
    {
        $this->artisan('health:check')
            ->assertExitCode(0);
    }
}

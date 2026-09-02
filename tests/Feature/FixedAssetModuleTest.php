<?php

namespace Tests\Feature;

use App\Models\AssetCategory;
use App\Models\AssetDepreciationRun;
use App\Models\AssetDisposal;
use App\Models\AssetLocation;
use App\Models\Country;
use App\Models\FixedAsset;
use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\Membership;
use App\Models\Role;
use App\Models\User;
use App\Services\Accounting\AccountingSetupService;
use App\Services\FixedAsset\FixedAssetService;
use App\Services\MembershipService;
use App\Services\UserAccountService;
use App\Support\BranchContext;
use App\Support\TenantContext;
use App\Support\Workspace;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * STEP 89 — Fixed Asset Management Module web layer tests.
 */
class FixedAssetModuleTest extends TestCase
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

    protected function tearDown(): void
    {
        TenantContext::clear();
        BranchContext::clear();
        Workspace::clear();
        parent::tearDown();
    }

    private function country(): Country
    {
        return Country::withoutGlobalScopes()->firstOrCreate(
            ['iso2' => 'BD'],
            ['name' => 'Bangladesh', 'iso3' => 'BDR', 'phone_code' => '880', 'status' => true]
        );
    }

    private function institute(): Institute
    {
        $country = $this->country();

        return Institute::create([
            'name' => 'FA Module Inst '.uniqid(),
            'slug' => 'fa-module-'.uniqid(),
            'country' => $country->name,
            'country_id' => $country->id,
            'industry' => 'education',
            'status' => 'active',
        ]);
    }

    private function owner(Institute $institute): User
    {
        $user = (new UserAccountService)->registerOwner([
            'name' => 'FA Owner',
            'first_name' => 'FA',
            'last_name' => 'Owner',
            'email' => 'fa-owner-'.uniqid().'@example.test',
            'password_hash' => bcrypt($this->password),
            'status' => 'active',
        ]);

        $roleId = Role::where('slug', 'institute-owner')->firstOrFail()->id;
        (new MembershipService)->assign($user, $institute->id, $roleId);

        return $user;
    }

    private function setupAccounting(Institute $institute): void
    {
        app(AccountingSetupService::class)->setupForInstitute($institute->id);
    }

    private function asUser(User $user, int $workspaceId): static
    {
        return $this->withSession([Workspace::SESSION_KEY => $workspaceId])->actingAs($user, 'web');
    }

    public function test_assets_index_renders(): void
    {
        $inst = $this->institute();
        $this->setupAccounting($inst);
        $owner = $this->owner($inst);

        $this->asUser($owner, $inst->id)
            ->get(route('fixed_assets.assets.index'))
            ->assertOk()
            ->assertSee('Fixed Assets');
    }

    public function test_asset_show_renders(): void
    {
        $inst = $this->institute();
        $this->setupAccounting($inst);
        $owner = $this->owner($inst);

        $asset = app(FixedAssetService::class)->create($inst->id, null, [
            'name' => 'Test Laptop',
            'serial_number' => 'LP-001',
        ]);

        $this->asUser($owner, $inst->id)
            ->get(route('fixed_assets.assets.show', $asset))
            ->assertOk()
            ->assertSee('Test Laptop')
            ->assertSee('LP-001');
    }

    public function test_asset_store_creates(): void
    {
        $inst = $this->institute();
        $this->setupAccounting($inst);
        $owner = $this->owner($inst);

        $this->asUser($owner, $inst->id)
            ->post(route('fixed_assets.assets.store'), [
                'name' => 'New Server',
                'acquisition_cost' => 15000,
                'useful_life_months' => 60,
                'depreciation_method' => 'straight_line',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('fixed_assets', [
            'institute_id' => $inst->id,
            'name' => 'New Server',
        ]);
    }

    public function test_asset_categories_index_renders(): void
    {
        $inst = $this->institute();
        $this->setupAccounting($inst);
        $owner = $this->owner($inst);

        AssetCategory::create([
            'institute_id' => $inst->id,
            'name' => 'IT Equipment',
            'code' => 'IT',
            'is_active' => true,
        ]);

        $this->asUser($owner, $inst->id)
            ->get(route('fixed_assets.categories.index'))
            ->assertOk()
            ->assertSee('Asset Categories')
            ->assertSee('IT Equipment');
    }

    public function test_depreciation_index_renders(): void
    {
        $inst = $this->institute();
        $this->setupAccounting($inst);
        $owner = $this->owner($inst);

        $this->asUser($owner, $inst->id)
            ->get(route('fixed_assets.depreciation.index'))
            ->assertOk()
            ->assertSee('Depreciation Runs');
    }

    public function test_asset_register_report_renders(): void
    {
        $inst = $this->institute();
        $this->setupAccounting($inst);
        $owner = $this->owner($inst);

        $this->asUser($owner, $inst->id)
            ->get(route('fixed_assets.reports.register'))
            ->assertOk()
            ->assertSee('Asset Register');
    }

    public function test_asset_depreciation_report_renders(): void
    {
        $inst = $this->institute();
        $this->setupAccounting($inst);
        $owner = $this->owner($inst);

        $this->asUser($owner, $inst->id)
            ->get(route('fixed_assets.reports.depreciation'))
            ->assertOk()
            ->assertSee('Depreciation Schedule');
    }

    public function test_tenant_isolation_fixed_assets(): void
    {
        $instA = $this->institute();
        $instB = $this->institute();
        $this->setupAccounting($instA);
        $this->setupAccounting($instB);

        $ownerA = $this->owner($instA);
        $ownerB = $this->owner($instB);

        app(FixedAssetService::class)->create($instA->id, null, ['name' => 'Asset A Only']);
        app(FixedAssetService::class)->create($instB->id, null, ['name' => 'Asset B Only']);

        $this->asUser($ownerA, $instA->id)
            ->get(route('fixed_assets.assets.index'))
            ->assertOk()
            ->assertSee('Asset A Only')
            ->assertDontSee('Asset B Only');

        $this->asUser($ownerB, $instB->id)
            ->get(route('fixed_assets.assets.index'))
            ->assertOk()
            ->assertSee('Asset B Only')
            ->assertDontSee('Asset A Only');
    }
}

<?php

namespace Tests\Feature;

use App\Models\AssetAuditLog;
use App\Models\AssetLocation;
use App\Models\Branch;
use App\Models\Country;
use App\Models\FixedAsset;
use App\Models\Institute;
use App\Models\Permission;
use App\Models\Role;
use App\Services\Accounting\AccountingSetupService;
use App\Services\FixedAsset\FixedAssetService;
use App\Support\BranchContext;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * STEP 17 — Lifecycle, transfer, method change, tenant isolation and permission
 * grants. Historical posted depreciation stays immutable across method changes.
 */
class FixedAssetLifecycleTest extends TestCase
{
    use DatabaseTransactions;

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
            ['name' => 'Bangladesh', 'iso3' => 'BDR', 'phone_code' => '880', 'status' => true]
        );
    }

    private function institute(string $industry = 'education'): Institute
    {
        $country = $this->country();

        return Institute::create([
            'name' => 'LC Inst',
            'slug' => str()->slug('LC Inst-'.uniqid()),
            'country' => $country->name,
            'country_id' => $country->id,
            'industry' => $industry,
            'status' => 'active',
        ]);
    }

    private function branch(Institute $institute, string $name = 'Main'): Branch
    {
        return Branch::create(['institute_id' => $institute->id, 'name' => $name, 'status' => 'active']);
    }

    private function location(Institute $institute, ?Branch $branch, string $name): AssetLocation
    {
        return AssetLocation::create([
            'institute_id' => $institute->id,
            'branch_id' => $branch?->id,
            'name' => $name,
        ]);
    }

    public function test_lifecycle_draft_to_fully_depreciated(): void
    {
        $institute = $this->institute();
        $branch = $this->branch($institute);
        app(AccountingSetupService::class)->setupForInstitute($institute->id, $branch->id);

        $service = app(FixedAssetService::class);
        $asset = $service->create($institute->id, $branch->id, [
            'name' => 'Projector',
            'acquisition_cost' => 1200,
            'residual_value' => 0,
            'useful_life_months' => 12,
            'depreciation_method' => 'straight_line',
            'depreciation_start_date' => '2026-01-01',
        ]);

        $this->assertSame('draft', $asset->status);

        $service->capitalize($asset, null, null, ['paid_immediately' => true]);
        $this->assertSame('active', $asset->fresh()->status);

        for ($m = 1; $m <= 12; $m++) {
            $service->runDepreciation($institute->id, $branch->id, sprintf('2026-%02d-01', $m), sprintf('2026-%02d-28', $m));
        }

        $this->assertSame('fully_depreciated', $asset->fresh()->status);
    }

    public function test_transfer_moves_branch_and_location(): void
    {
        $institute = $this->institute();
        $branchA = $this->branch($institute, 'Head Office');
        $branchB = $this->branch($institute, 'Branch B');
        app(AccountingSetupService::class)->setupForInstitute($institute->id, $branchA->id);

        $locA = $this->location($institute, $branchA, 'HQ Room');
        $locB = $this->location($institute, $branchB, 'B Warehouse');

        $service = app(FixedAssetService::class);
        $asset = $service->create($institute->id, $branchA->id, [
            'name' => 'Van',
            'location_id' => $locA->id,
            'department' => 'Logistics',
            'acquisition_cost' => 500000,
            'residual_value' => 0,
            'useful_life_months' => 60,
            'depreciation_method' => 'straight_line',
        ]);

        $transfer = $service->transfer($asset, [
            'to_branch_id' => $branchB->id,
            'to_location_id' => $locB->id,
            'to_department' => 'Branch Logistics',
            'reason' => 'relocation',
        ]);

        $asset->refresh();
        $this->assertSame($branchB->id, $asset->branch_id);
        $this->assertSame($locB->id, $asset->location_id);
        $this->assertSame('Branch Logistics', $asset->department);
        $this->assertSame($branchA->id, $transfer->from_branch_id);
    }

    public function test_method_change_preserves_posted_history(): void
    {
        $institute = $this->institute();
        $branch = $this->branch($institute);
        app(AccountingSetupService::class)->setupForInstitute($institute->id, $branch->id);

        $service = app(FixedAssetService::class);
        $asset = $service->create($institute->id, $branch->id, [
            'name' => 'Machine',
            'acquisition_cost' => 12000,
            'residual_value' => 0,
            'useful_life_months' => 12,
            'depreciation_method' => 'straight_line',
            'depreciation_start_date' => '2026-01-01',
        ]);
        $asset = $service->capitalize($asset, null, null, ['paid_immediately' => true]);

        $service->runDepreciation($institute->id, $branch->id, '2026-01-01', '2026-01-31');
        $entriesBefore = $asset->fresh()->depreciationEntries()->count();
        $accumulatedBefore = (float) $asset->fresh()->accumulated_depreciation;

        $change = $service->changeMethod($asset, [
            'new_method' => 'reducing_balance',
            'new_useful_life_months' => 24,
            'new_residual_value' => 500,
            'reason' => 'policy update',
        ]);

        $this->assertSame('requested', $change->status);

        $asset = $service->approveMethodChange($change);

        $this->assertSame('reducing_balance', $asset->depreciation_method);
        $this->assertSame(24, $asset->useful_life_months);
        $this->assertSame($entriesBefore, $asset->depreciationEntries()->count());
        $this->assertSame($accumulatedBefore, (float) $asset->accumulated_depreciation);
    }

    public function test_tenant_isolation_via_global_scope(): void
    {
        $instituteA = $this->institute();
        $instituteB = $this->institute();

        $service = app(FixedAssetService::class);
        $assetA = $service->create($instituteA->id, null, ['name' => 'Secret Asset']);

        TenantContext::set($instituteB->id);
        $this->assertNull(FixedAsset::query()->find($assetA->id));

        TenantContext::set($instituteA->id);
        $this->assertNotNull(FixedAsset::query()->find($assetA->id));
    }

    public function test_asset_audit_trail_records_events(): void
    {
        $institute = $this->institute();
        $branch = $this->branch($institute);
        app(AccountingSetupService::class)->setupForInstitute($institute->id, $branch->id);

        $service = app(FixedAssetService::class);
        $asset = $service->create($institute->id, $branch->id, [
            'name' => 'Audited Asset',
            'acquisition_cost' => 1000,
            'residual_value' => 0,
            'useful_life_months' => 12,
            'depreciation_method' => 'straight_line',
        ]);
        $service->capitalize($asset, null, null, ['paid_immediately' => true]);

        $events = AssetAuditLog::query()
            ->where('institute_id', $institute->id)
            ->where('asset_id', $asset->id)
            ->pluck('event')
            ->all();

        $this->assertContains('created', $events);
        $this->assertContains('capitalized', $events);
    }

    public function test_asset_permissions_granted_by_role(): void
    {
        $permissionIds = Permission::query()->where('module', 'asset')->pluck('id');
        $this->assertCount(14, $permissionIds);

        $owner = Role::query()->where('slug', 'institute-owner')->firstOrFail();
        $manager = Role::query()->where('slug', 'branch-manager')->firstOrFail();
        $receptionist = Role::query()->where('slug', 'receptionist')->firstOrFail();

        $granted = fn (Role $role) => DB::table('role_permissions')
            ->where('role_id', $role->id)
            ->whereIn('permission_id', $permissionIds)
            ->count();

        $this->assertSame(14, $granted($owner));

        $managerSlugs = Permission::query()
            ->whereIn('id', DB::table('role_permissions')->where('role_id', $manager->id)->whereIn('permission_id', $permissionIds)->pluck('permission_id'))
            ->pluck('slug')
            ->all();
        $this->assertNotContains('asset.approve', $managerSlugs);
        $this->assertNotContains('asset.dispose', $managerSlugs);
        $this->assertContains('asset.transfer', $managerSlugs);

        $receptionistSlugs = Permission::query()
            ->whereIn('id', DB::table('role_permissions')->where('role_id', $receptionist->id)->whereIn('permission_id', $permissionIds)->pluck('permission_id'))
            ->pluck('slug')
            ->all();
        $this->assertSame(['asset.view'], $receptionistSlugs);
    }
}

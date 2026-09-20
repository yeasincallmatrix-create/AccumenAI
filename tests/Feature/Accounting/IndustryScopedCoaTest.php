<?php

namespace Tests\Feature\Accounting;

use App\Models\AccountGroup;
use App\Models\ChartOfAccount;
use App\Models\Industry;
use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\Role;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Phase F - industry-scoped COA (visibleTo-only).
 *
 * Locked decisions:
 * - 4001/4002 tagged ["education","training_center"]; 4010/5007 universal.
 * - Only visibleTo()/visible() filter; TenantScoped default path untouched.
 * - Institute::create + InstituteUser::create (no factories exist).
 */
class IndustryScopedCoaTest extends TestCase
{
    use DatabaseTransactions;

    protected function tenantWithIndustry(string $slug): Institute
    {
        ChartOfAccount::clearIndustrySlugCache();

        $industry = Industry::where('slug', $slug)->firstOrFail();

        $institute = Institute::create([
            'name' => 'PhaseF '.Str::random(8),
            'slug' => 'phase-f-'.Str::lower(Str::random(8)),
            'status' => 'active',
            'industry' => $slug,
            'industry_id' => $industry->id,
            'advanced_accounting_enabled' => true,
        ]);

        InstituteUser::create([
            'institute_id' => $institute->id,
            'role_id' => Role::where('slug', 'institute-owner')->firstOrFail()->id,
            'first_name' => 'Phase',
            'last_name' => 'F',
            'email' => 'phasef-'.Str::random(8).'@test.test',
            'phone' => '017'.rand(10000000, 99999999),
            'password_hash' => bcrypt('password'),
            'status' => 'active',
            'email_verified_at' => now(),
        ]);

        return $institute;
    }

    protected function tenantGroupId(int $instituteId, string $type): ?int
    {
        return AccountGroup::withoutGlobalScope('institute')
            ->where('institute_id', $instituteId)
            ->where('category', $type)
            ->value('id')
            ?? AccountGroup::withoutGlobalScope('institute')
                ->whereNull('institute_id')
                ->where('is_system', 1)
                ->where('category', $type)
                ->value('id');
    }

    public function test_school_sees_tuition_via_training_center_slug(): void
    {
        $tenant = $this->tenantWithIndustry('training_center');
        $visible = ChartOfAccount::visibleTo($tenant->id)->pluck('code')->toArray();

        // Critical: training_center slug passes (name collision-safe resolver).
        $this->assertContains('4001', $visible);
        $this->assertContains('4002', $visible);
    }

    public function test_hospital_does_not_see_tuition(): void
    {
        $tenant = $this->tenantWithIndustry('healthcare');
        $visible = ChartOfAccount::visibleTo($tenant->id)->pluck('code')->toArray();

        $this->assertNotContains('4001', $visible);
        $this->assertNotContains('4002', $visible);
    }

    public function test_universal_still_visible(): void
    {
        $tenant = $this->tenantWithIndustry('healthcare');
        $visible = ChartOfAccount::visibleTo($tenant->id)->pluck('code')->toArray();

        foreach (['1000', '1100', '1200', '2000', '2100', '3000', '4000', '4010', '5007', '5000'] as $code) {
            $this->assertContains($code, $visible, "Universal {$code} missing");
        }
    }

    public function test_tenant_custom_visible(): void
    {
        $tenant = $this->tenantWithIndustry('healthcare');

        $custom = ChartOfAccount::withoutGlobalScope('institute')->create([
            'institute_id' => $tenant->id,
            'branch_id' => null,
            'account_group_id' => $this->tenantGroupId($tenant->id, 'asset'),
            'code' => '9999',
            'name' => 'Custom',
            'type' => 'asset',
            'is_system' => 0,
            'is_active' => 1,
        ]);

        $visibleIds = ChartOfAccount::visibleTo($tenant->id)->pluck('id')->toArray();
        $this->assertContains($custom->id, $visibleIds);
    }
}

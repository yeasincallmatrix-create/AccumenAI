<?php

namespace Tests\Feature\Accounting;

use App\Models\ChartOfAccount;
use App\Models\Industry;
use App\Models\Institute;
use Illuminate\Support\Str;
use Tests\TestCase;

class IndustryScopedCoaTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Hermetic tags: shared test DB may be touched by parallel lanes.
        $tags = [
            '4001' => ['education', 'training_center'],
            '4002' => ['education', 'training_center'],
            '4003' => ['retail'],
            '5007' => ['retail', 'manufacturing'],
        ];
        foreach ($tags as $code => $industries) {
            \DB::table('chart_of_accounts')
                ->whereNull('institute_id')
                ->where('code', $code)
                ->update(['industries' => json_encode($industries)]);
        }
    }

    protected function instituteWithIndustry(string $slug): Institute
    {
        $industry = Industry::where('slug', $slug)->firstOrFail();

        return Institute::create([
            'name' => 'Ind Test '.Str::random(8),
            'slug' => 'ind-test-'.Str::random(8),
            'status' => 'active',
            'industry_id' => $industry->id,
        ]);
    }

    public function test_healthcare_does_not_see_education_accounts(): void
    {
        $hospital = $this->instituteWithIndustry('healthcare');
        $visible = ChartOfAccount::visibleTo($hospital->id)->pluck('code')->toArray();

        $this->assertNotContains('4001', $visible);
        $this->assertNotContains('4002', $visible);
        $this->assertContains('1000', $visible);
    }

    public function test_education_does_not_see_retail_only(): void
    {
        $school = $this->instituteWithIndustry('education');
        $visible = ChartOfAccount::visibleTo($school->id)->pluck('code')->toArray();

        $this->assertContains('4001', $visible);
        $this->assertContains('4002', $visible);
        $this->assertNotContains('4003', $visible);
        $this->assertNotContains('5007', $visible);
    }

    public function test_universal_visible_to_all(): void
    {
        $school = $this->instituteWithIndustry('education');
        $visible = ChartOfAccount::visibleTo($school->id)->pluck('code')->toArray();

        foreach (['1000', '1100', '1200', '2000', '2100', '3000', '4000', '5000', '4010'] as $universal) {
            $this->assertContains($universal, $visible, "Universal {$universal} missing");
        }
    }

    public function test_tenant_custom_always_visible(): void
    {
        $school = $this->instituteWithIndustry('education');
        $custom = ChartOfAccount::withoutGlobalScope('institute')->create([
            'institute_id' => $school->id,
            'account_group_id' => \App\Models\AccountGroup::withoutGlobalScope('institute')
                ->where('institute_id', $school->id)->value('id')
                ?? \App\Models\AccountGroup::withoutGlobalScope('institute')
                    ->whereNull('institute_id')->value('id'),
            'code' => '9999',
            'name' => 'Custom',
            'type' => 'asset',
            'is_system' => 0,
            'is_active' => 1,
        ]);

        $visible = ChartOfAccount::visibleTo($school->id)->pluck('id')->toArray();
        $this->assertContains($custom->id, $visible);
    }

    public function test_default_scope_filters_by_industry(): void
    {
        $hospital = $this->instituteWithIndustry('healthcare');
        \App\Support\TenantContext::set($hospital->id);

        $codes = ChartOfAccount::query()->pluck('code')->toArray();

        $this->assertNotContains('4001', $codes);
        $this->assertContains('1000', $codes);

        \App\Support\TenantContext::clear();
    }
}

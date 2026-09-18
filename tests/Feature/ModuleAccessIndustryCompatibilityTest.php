<?php

namespace Tests\Feature;

use App\Models\Institute;
use App\Services\ModuleAccessService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ModuleAccessIndustryCompatibilityTest extends TestCase
{
    use DatabaseTransactions;

    private ModuleAccessService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ModuleAccessService::class);
    }

    private function institute(string $industry = null): Institute
    {
        return Institute::create([
            'name' => 'IC-Test-' . uniqid(),
            'slug' => 'ic-test-' . uniqid(),
            'status' => 'active',
            'industry' => $industry,
        ]);
    }

    public function test_is_industry_compatible_is_public_and_callable(): void
    {
        $inst = $this->institute('education');
        $result = $this->service->isIndustryCompatible($inst, 'crm');
        $this->assertTrue($result);
    }

    public function test_education_module_with_education_industry(): void
    {
        $inst = $this->institute('education');
        $this->assertTrue($this->service->isIndustryCompatible($inst, 'education'));
    }

    public function test_education_module_with_healthcare_industry(): void
    {
        $inst = $this->institute('healthcare');
        $this->assertFalse($this->service->isIndustryCompatible($inst, 'education'));
    }

    public function test_medical_module_with_healthcare_industry(): void
    {
        $inst = $this->institute('healthcare');
        $this->assertTrue($this->service->isIndustryCompatible($inst, 'medical'));
    }

    public function test_medical_module_with_education_industry(): void
    {
        $inst = $this->institute('education');
        $this->assertFalse($this->service->isIndustryCompatible($inst, 'medical'));
    }

    public function test_training_center_module_with_training_center_industry(): void
    {
        $inst = $this->institute('training_center');
        $this->assertTrue($this->service->isIndustryCompatible($inst, 'training_center'));
    }

    public function test_training_center_module_with_education_industry(): void
    {
        $inst = $this->institute('education');
        $this->assertFalse($this->service->isIndustryCompatible($inst, 'training_center'));
    }

    public function test_non_industry_module_always_compatible(): void
    {
        $inst = $this->institute('education');
        $this->assertTrue($this->service->isIndustryCompatible($inst, 'crm'));
        $this->assertTrue($this->service->isIndustryCompatible($inst, 'finance'));
        $this->assertTrue($this->service->isIndustryCompatible($inst, 'hr'));
        $this->assertTrue($this->service->isIndustryCompatible($inst, 'sales'));
        $this->assertTrue($this->service->isIndustryCompatible($inst, 'inventory'));
    }

    public function test_non_industry_module_compatible_with_null_industry(): void
    {
        $inst = $this->institute(null);
        $this->assertTrue($this->service->isIndustryCompatible($inst, 'crm'));
        $this->assertTrue($this->service->isIndustryCompatible($inst, 'education'));
    }

    public function test_industry_module_with_wrong_industry_is_incompatible(): void
    {
        $inst = $this->institute('retail');
        $this->assertFalse($this->service->isIndustryCompatible($inst, 'education'));
        $this->assertFalse($this->service->isIndustryCompatible($inst, 'medical'));
        $this->assertFalse($this->service->isIndustryCompatible($inst, 'training_center'));
    }
}

<?php

namespace Tests\Feature;

use App\Models\Institute;
use App\Services\ModuleAccessService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class IndustryModuleMatrixTest extends TestCase
{
    use DatabaseTransactions;

    protected ModuleAccessService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ModuleAccessService::class);
    }

    public function test_healthcare_gets_purchase_default(): void
    {
        $institute = $this->makeInstitute('healthcare');
        $enabled = $this->service->resolveEnabled($institute);

        $this->assertTrue($enabled['purchase'] ?? false, 'purchase must be enabled for healthcare');
        $this->assertTrue($enabled['medical'] ?? false, 'medical must be enabled for healthcare');
        $this->assertTrue($enabled['medical.billing'] ?? false, 'medical.billing must be enabled for healthcare');
    }

    public function test_retail_gets_sales_and_purchase_default(): void
    {
        $institute = $this->makeInstitute('retail');
        $enabled = $this->service->resolveEnabled($institute);

        $this->assertTrue($enabled['sales'] ?? false, 'sales must be enabled for retail');
        $this->assertTrue($enabled['purchase'] ?? false, 'purchase must be enabled for retail');
        $this->assertTrue($enabled['inventory'] ?? false, 'inventory must be enabled for retail');
    }

    public function test_education_does_not_get_sales_by_default(): void
    {
        $institute = $this->makeInstitute('education');
        $enabled = $this->service->resolveEnabled($institute);

        $this->assertTrue($enabled['education'] ?? false, 'education must be enabled for education industry');
        $this->assertFalse($enabled['medical'] ?? true, 'medical must NOT be enabled for education');
    }

    public function test_manufacturing_gets_sales_purchase_inventory(): void
    {
        $institute = $this->makeInstitute('manufacturing');
        $enabled = $this->service->resolveEnabled($institute);

        $this->assertTrue($enabled['sales'] ?? false, 'sales must be enabled for manufacturing');
        $this->assertTrue($enabled['purchase'] ?? false, 'purchase must be enabled for manufacturing');
        $this->assertTrue($enabled['inventory'] ?? false, 'inventory must be enabled for manufacturing');
        $this->assertTrue($enabled['manufacturing'] ?? false, 'manufacturing module must be enabled for manufacturing industry');
    }

    public function test_core_modules_always_enabled(): void
    {
        foreach (['healthcare', 'education', 'training_center', 'retail', 'manufacturing', 'real_estate'] as $industry) {
            $institute = $this->makeInstitute($industry);
            $enabled = $this->service->resolveEnabled($institute);

            $this->assertTrue($enabled['crm'] ?? false, "CRM missing for {$industry}");
            $this->assertTrue($enabled['accounting'] ?? false, "Accounting missing for {$industry}");
            $this->assertTrue($enabled['reports'] ?? false, "Reports missing for {$industry}");
        }
    }

    public function test_medical_not_available_for_education(): void
    {
        $institute = $this->makeInstitute('education');
        $enabled = $this->service->resolveEnabled($institute);

        $this->assertFalse($enabled['medical'] ?? true, 'medical must NOT be available for education');
        $this->assertFalse($enabled['medical.billing'] ?? true, 'medical.billing must NOT be available for education');
    }

    public function test_sales_and_purchase_have_submodules(): void
    {
        $enabled = $this->service->resolveEnabled($this->makeInstitute('retail'));

        $this->assertTrue($enabled['sales'] ?? false, 'sales parent must be enabled for retail');
        $this->assertTrue($enabled['purchase'] ?? false, 'purchase parent must be enabled for retail');
    }

    protected function makeInstitute(string $industry): Institute
    {
        return Institute::create([
            'name' => 'Matrix '.uniqid(),
            'slug' => 'matrix-'.uniqid(),
            'status' => 'active',
            'industry' => $industry,
        ]);
    }
}

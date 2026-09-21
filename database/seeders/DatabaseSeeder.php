<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $this->call(CurrencySeeder::class);
        $this->call(SystemRoleSeeder::class);
        $this->call(RoleSeeder::class);
        $this->call(TaxPermissionSeeder::class);
        $this->call(LabAnalyzerPermissionSeeder::class);
        // B95: AI tool gating permissions (finance.view / crm.view).
        // Placed with other permission seeders, before RolePermissionSeeder
        // so institute-owner grant picks them up (plus AiTool seeder grants
        // owner/admin/accountant directly). Same namespace — no import needed.
        $this->call(AiToolPermissionSeeder::class);
        $this->call(ModuleTogglePermissionSeeder::class);
        $this->call(RolePermissionSeeder::class);
        $this->call(ModuleRegistrySeeder::class);
        $this->call(MedicalSubModuleSeeder::class);
        $this->call(EducationSubModuleSeeder::class);
        $this->call(EducationPermissionSeeder::class);
        $this->call(TrainingCenterSubModuleSeeder::class);
        $this->call(TrainingCenterPermissionSeeder::class);
        $this->call(IndustryTaxonomySeeder::class);
        $this->call(IndustryTaxonomyTestSeeder::class);
        $this->call(AcademicStructureSeeder::class);
        $this->call(GradeScaleSeeder::class);
        $this->call(AdditionalCountrySeeder::class);
        $this->call(AcademicAssessmentSeeder::class);
        $this->call(CertificateSeeder::class);
        $this->call(FeatureRegistrySeeder::class);
        $this->call(PackageFeatureSeeder::class);
        // Hybrid COA Phase C: global groups BEFORE global accounts.
        $this->call(GlobalAccountGroupsSeeder::class);
        $this->call(GlobalChartOfAccountsSeeder::class);
        // Phase F follow-up: canonical industry tags (self-healing).
        $this->call(IndustryTagSeeder::class);
        // B82: shared global catalogs (idempotent; also wired in TestCase).
        $this->call(DocumentCategorySeeder::class);
        $this->call(CrmLeadStatusSeeder::class);
        $this->call(ThemeSeeder::class);
        $this->call(TaxDeductionRuleSeeder::class);
        $this->call(CountryTaxConfigSeeder::class);
    }
}

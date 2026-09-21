<?php

namespace Tests\Feature\Settings;

use App\Models\Institute;
use App\Models\ModuleRegistry;
use App\Models\PackageModule;
use App\Models\Role;
use App\Models\SubscriptionPackage;
use App\Models\User;
use App\Services\MembershipService;
use App\Services\ModuleAccessService;
use App\Services\UserAccountService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Tests\TestCase;

class ModuleManagementIndustryFilterTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    protected function createInstituteWithIndustry(string $industry): array
    {
        $institute = Institute::create([
            'name' => 'Industry-Test-' . uniqid(),
            'slug' => 'industry-test-' . uniqid(),
            'status' => 'active',
            'industry' => $industry,
        ]);

        $free = SubscriptionPackage::whereRaw('LOWER(slug) = ?', ['free'])->first();
        if ($free) {
            $institute->update(['package_id' => $free->id]);
        }

        $owner = (new UserAccountService)->registerOwner([
            'name' => 'Test Owner',
            'first_name' => 'Test',
            'last_name' => 'Owner',
            'email' => 'industry-owner-' . uniqid() . '@example.test',
            'password_hash' => bcrypt('password'),
            'status' => 'active',
            'email_verified_at' => now(),
        ]);

        $roleId = Role::where('slug', 'institute-owner')->firstOrFail()->id;
        (new MembershipService)->assign($owner, $institute->id, $roleId);

        return [$institute, $owner];
    }

    protected function asUser(User $user, int $workspaceId): static
    {
        return $this->withSession([\App\Support\Workspace::SESSION_KEY => $workspaceId])
            ->actingAs($user, 'web');
    }

    protected function ensurePackageModules(Institute $institute, array $moduleKeys): void
    {
        foreach ($moduleKeys as $key) {
            PackageModule::updateOrCreate(
                ['package_id' => $institute->package_id, 'module_key' => $key],
                ['enabled' => true]
            );
        }
        app(ModuleAccessService::class)->flushCache($institute->id);
    }

    public function test_medical_institute_does_not_see_education_modules(): void
    {
        [$institute, $owner] = $this->createInstituteWithIndustry('healthcare');
        $this->ensurePackageModules($institute, [
            'crm', 'medical', 'medical.opd', 'medical.ipd', 'education', 'education.classes',
        ]);

        $response = $this->asUser($owner, $institute->id)
            ->get(route('settings.modules'));

        $response->assertOk();
        $response->assertDontSee('education.classes');
        $response->assertDontSee('education.exams');
        $response->assertDontSee('education.students');
    }

    public function test_education_institute_does_not_see_medical_modules(): void
    {
        [$institute, $owner] = $this->createInstituteWithIndustry('education');
        $this->ensurePackageModules($institute, [
            'crm', 'education', 'education.classes', 'medical', 'medical.opd', 'medical.pharmacy',
        ]);

        $response = $this->asUser($owner, $institute->id)
            ->get(route('settings.modules'));

        $response->assertOk();
        $response->assertDontSee('medical.opd');
        $response->assertDontSee('medical.pharmacy');
        $response->assertDontSee('medical.ipd');
    }

    public function test_core_modules_visible_to_all_industries(): void
    {
        [$medicalInst, $medOwner] = $this->createInstituteWithIndustry('healthcare');
        $this->ensurePackageModules($medicalInst, ['crm', 'finance', 'reports']);

        [$eduInst, $eduOwner] = $this->createInstituteWithIndustry('education');
        $this->ensurePackageModules($eduInst, ['crm', 'finance', 'reports']);

        $medResponse = $this->asUser($medOwner, $medicalInst->id)
            ->get(route('settings.modules'));
        $medResponse->assertOk();
        $medResponse->assertSee('CRM');

        $eduResponse = $this->asUser($eduOwner, $eduInst->id)
            ->get(route('settings.modules'));
        $eduResponse->assertOk();
        $eduResponse->assertSee('CRM');
    }

    public function test_is_industry_compatible_blocks_child_for_wrong_industry(): void
    {
        $service = app(ModuleAccessService::class);

        $medInst = Institute::create([
            'name' => 'Med-Compat-' . uniqid(),
            'slug' => 'med-compat-' . uniqid(),
            'status' => 'active',
            'industry' => 'healthcare',
        ]);

        $this->assertFalse($service->isIndustryCompatible($medInst, 'education.classes'));
        $this->assertFalse($service->isIndustryCompatible($medInst, 'education.exams'));
        $this->assertTrue($service->isIndustryCompatible($medInst, 'medical.opd'));
        $this->assertTrue($service->isIndustryCompatible($medInst, 'medical.pharmacy'));

        $eduInst = Institute::create([
            'name' => 'Edu-Compat-' . uniqid(),
            'slug' => 'edu-compat-' . uniqid(),
            'status' => 'active',
            'industry' => 'education',
        ]);

        $this->assertTrue($service->isIndustryCompatible($eduInst, 'education.classes'));
        $this->assertFalse($service->isIndustryCompatible($eduInst, 'medical.opd'));
    }

    public function test_is_industry_compatible_allows_core_modules(): void
    {
        $service = app(ModuleAccessService::class);

        $medInst = Institute::create([
            'name' => 'Med-Core-' . uniqid(),
            'slug' => 'med-core-' . uniqid(),
            'status' => 'active',
            'industry' => 'healthcare',
        ]);

        $this->assertTrue($service->isIndustryCompatible($medInst, 'crm'));
        $this->assertTrue($service->isIndustryCompatible($medInst, 'finance'));
        $this->assertTrue($service->isIndustryCompatible($medInst, 'reports'));
        $this->assertTrue($service->isIndustryCompatible($medInst, 'hr'));
        $this->assertTrue($service->isIndustryCompatible($medInst, 'sales'));
    }
}

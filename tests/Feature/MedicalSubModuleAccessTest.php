<?php

namespace Tests\Feature;

use App\Models\Institute;
use App\Models\Membership;
use App\Models\Role;
use App\Models\User;
use App\Services\ModuleAccessService;
use App\Support\Workspace;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class MedicalSubModuleAccessTest extends TestCase
{
    use DatabaseTransactions;

    private Institute $institute;
    private User $doctor;
    private ModuleAccessService $moduleAccess;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $this->institute = Institute::create([
            'name' => 'SubMod Access Test Hospital',
            'slug' => 'submod-access-' . uniqid(),
            'industry' => 'healthcare',
            'sub_industry' => 'hospital',
            'country' => 'Bangladesh',
            'status' => 'active',
        ]);

        $this->doctor = User::factory()->create([
            'account_type' => 'staff',
            'email_verified_at' => now(),
            'status' => 'active',
        ]);

        $roleId = Role::where('slug', 'doctor')->value('id')
            ?? Role::where('slug', 'staff')->value('id')
            ?? Role::where('slug', 'institute-owner')->value('id');

        Membership::create([
            'user_id' => $this->doctor->id,
            'institution_id' => $this->institute->id,
            'role_id' => $roleId,
            'status' => 'active',
        ]);

        $this->moduleAccess = app(ModuleAccessService::class);

        $this->actingAs($this->doctor, 'web');
        Workspace::set($this->institute->id);
    }

    private function enableModule(string $key): void
    {
        $this->moduleAccess->enableModule($this->institute, $key);
    }

    private function disableModule(string $key): void
    {
        $this->moduleAccess->disableModule($this->institute, $key);
    }

    public function test_opd_route_blocked_when_opd_disabled(): void
    {
        $this->enableModule('medical');
        $this->disableModule('medical.opd');

        $response = $this->get(route('medical.opd.appointments.index'));
        $response->assertStatus(403);
    }

    public function test_opd_route_works_when_opd_enabled(): void
    {
        $this->enableModule('medical');
        $this->enableModule('medical.opd');

        $response = $this->get(route('medical.opd.appointments.index'));
        $response->assertStatus(200);
    }

    public function test_all_sub_modules_blocked_when_parent_disabled(): void
    {
        $this->disableModule('medical');
        $this->enableModule('medical.opd');
        $this->enableModule('medical.ipd');
        $this->enableModule('medical.pharmacy');
        $this->enableModule('medical.laboratory');
        $this->enableModule('medical.billing');

        $routes = [
            'medical.opd.appointments.index',
            'medical.ipd.admissions.index',
            'medical.pharmacy.medicines.index',
            'medical.laboratory.orders.index',
            'medical.billing.invoices.index',
        ];

        foreach ($routes as $routeName) {
            $response = $this->get(route($routeName));
            $response->assertStatus(403);
        }
    }

    public function test_pharmacy_blocked_but_opd_works(): void
    {
        $this->enableModule('medical');
        $this->enableModule('medical.opd');
        $this->disableModule('medical.pharmacy');

        $opdResponse = $this->get(route('medical.opd.appointments.index'));
        $opdResponse->assertStatus(200);

        $pharmacyResponse = $this->get(route('medical.pharmacy.medicines.index'));
        $pharmacyResponse->assertStatus(403);
    }

    public function test_laboratory_blocked_when_disabled(): void
    {
        $this->enableModule('medical');
        $this->disableModule('medical.laboratory');

        $response = $this->get(route('medical.laboratory.orders.index'));
        $response->assertStatus(403);
    }

    public function test_billing_blocked_when_disabled(): void
    {
        $this->enableModule('medical');
        $this->disableModule('medical.billing');

        $response = $this->get(route('medical.billing.invoices.index'));
        $response->assertStatus(403);
    }

    public function test_ipd_blocked_when_disabled(): void
    {
        $this->enableModule('medical');
        $this->disableModule('medical.ipd');

        $response = $this->get(route('medical.ipd.admissions.index'));
        $response->assertStatus(403);
    }
}

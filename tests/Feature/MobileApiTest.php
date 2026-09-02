<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Country;
use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class MobileApiTest extends TestCase
{
    use DatabaseTransactions;

    private function country(): Country
    {
        return Country::firstOrCreate(
            ['iso2' => 'BD'],
            ['name' => 'Bangladesh', 'iso3' => 'BGD', 'phone_code' => '880', 'status' => true]
        );
    }

    private function institute(string $name = 'Mobile API Inst'): Institute
    {
        $country = $this->country();
        return Institute::create([
            'name' => $name . ' ' . uniqid(),
            'slug' => str()->slug($name . ' ' . uniqid()),
            'country' => $country->name,
            'country_id' => $country->id,
            'industry' => 'education',
            'status' => 'active',
        ]);
    }

    private function branch(Institute $institute, string $name = 'Main'): Branch
    {
        return Branch::create([
            'institute_id' => $institute->id,
            'name' => $name . ' ' . uniqid(),
            'status' => 'active',
        ]);
    }

    private function user(Institute $institute, string $role = 'institute-owner', ?int $branchId = null): InstituteUser
    {
        return InstituteUser::create([
            'institute_id' => $institute->id,
            'role_id' => Role::where('slug', $role)->firstOrFail()->id,
            'branch_id' => $branchId,
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => $role . '-' . uniqid() . '@example.test',
            'phone' => '017' . rand(10000000, 99999999),
            'password_hash' => bcrypt('secret'),
            'status' => 'active',
        ]);
    }

    public function test_sales_quotations_api_returns_json(): void
    {
        $inst = $this->institute();
        $user = $this->user($inst);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/sales/quotations', ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_hr_employees_api_returns_json(): void
    {
        $inst = $this->institute();
        $user = $this->user($inst);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/hr/employees', ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_inventory_items_api_returns_json(): void
    {
        $inst = $this->institute();
        $user = $this->user($inst);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/inventory/items', ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_api_requires_authentication(): void
    {
        $this->getJson('/api/sales/quotations', ['Accept' => 'application/json'])
            ->assertStatus(401);
    }

    public function test_api_requires_institute_context(): void
    {
        $userWithoutInstitute = User::create([
            'name' => 'No Institute',
            'first_name' => 'No',
            'last_name' => 'Institute',
            'email' => 'no-institute-' . uniqid() . '@example.test',
            'password_hash' => bcrypt('secret'),
            'status' => 'active',
        ]);

        $this->actingAs($userWithoutInstitute, 'sanctum')
            ->getJson('/api/sales/quotations', ['Accept' => 'application/json'])
            ->assertForbidden()
            ->assertJsonPath('message', 'No active institute workspace.');
    }
}

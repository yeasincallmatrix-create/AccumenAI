<?php

namespace Tests\Feature;

use App\Models\Currency;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Verify that Phase-0 seed data prerequisites are in place.
 *
 * These assertions guard against regressions: if any of them fail,
 * the root-cause seeders need attention before other suites can pass.
 */
class TestHarnessBaselineTest extends TestCase
{
    use DatabaseTransactions;

    public function test_currencies_table_is_seeded(): void
    {
        $this->assertGreaterThan(0, Currency::count(), 'currencies table must not be empty');

        foreach (['BDT', 'USD', 'MYR', 'INR', 'EUR'] as $code) {
            $this->assertDatabaseHas('currencies', ['code' => $code]);
        }
    }

    public function test_institute_owner_role_exists(): void
    {
        $role = Role::where('slug', 'institute-owner')
            ->whereNull('institute_id')
            ->first();

        $this->assertNotNull($role, 'institute-owner role with NULL institute_id must exist');
        $this->assertNotEmpty($role->is_system, 'institute-owner must be a system role');
    }

    public function test_all_system_roles_exist(): void
    {
        foreach (['institute-owner', 'institute-admin', 'branch-manager', 'teacher', 'accountant', 'receptionist', 'exam-controller', 'trainer'] as $slug) {
            $this->assertDatabaseHas('roles', [
                'slug' => $slug,
                'institute_id' => null,
            ]);
        }
    }

    public function test_tax_permissions_exist(): void
    {
        foreach (['tax.view', 'tax.manage', 'tax.report', 'tax.settings', 'tax.audit'] as $slug) {
            $this->assertDatabaseHas('permissions', ['slug' => $slug]);
        }
    }

    public function test_industries_table_is_seeded(): void
    {
        $count = \DB::table('industries')->count();
        $this->assertGreaterThanOrEqual(3, $count, "Expected at least 3 industries, found {$count}");
    }

    public function test_sub_industries_table_is_seeded(): void
    {
        $count = \DB::table('sub_industries')->count();
        $this->assertGreaterThanOrEqual(3, $count, "Expected at least 3 sub-industries, found {$count}");
    }

    public function test_database_seeder_runs_cleanly(): void
    {
        $this->artisan('db:seed', ['--class' => \Database\Seeders\CurrencySeeder::class])
            ->assertExitCode(0);

        $this->artisan('db:seed', ['--class' => \Database\Seeders\RoleSeeder::class])
            ->assertExitCode(0);

        $this->artisan('db:seed', ['--class' => \Database\Seeders\TaxPermissionSeeder::class])
            ->assertExitCode(0);
    }
}

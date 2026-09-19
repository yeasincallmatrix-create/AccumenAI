<?php

namespace Tests;

use App\Models\Currency;
use App\Models\Industry;
use App\Models\Permission;
use App\Models\Role;
use App\Support\BranchContext;
use App\Support\TenantContext;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\IndustryTaxonomyTestSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SystemRoleSeeder;
use Database\Seeders\TaxPermissionSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        session(['mawa_lang' => 'en']);

        TenantContext::clear();
        BranchContext::clear();

        // Ensure seed data that the test suite depends on exists.
        // Idempotent guards — only seed when rows are missing.
        if (Currency::count() === 0) {
            (new CurrencySeeder)->run();
        }

        if (Role::where('slug', 'institute-owner')->whereNull('institute_id')->doesntExist()) {
            (new RoleSeeder)->run();
        }

        $requiredRoles = ['institute-owner', 'institute-admin', 'branch-manager', 'teacher', 'accountant', 'receptionist', 'exam-controller', 'trainer'];
        $existingRoles = Role::whereNull('institute_id')->pluck('slug')->toArray();
        if (count(array_diff($requiredRoles, $existingRoles)) > 0) {
            (new SystemRoleSeeder)->run();
        }

        $requiredPerms = ['tax.view', 'tax.manage', 'tax.report', 'tax.settings', 'tax.audit'];
        $existingPerms = Permission::whereIn('slug', $requiredPerms)->pluck('slug')->toArray();
        if (count(array_diff($requiredPerms, $existingPerms)) > 0) {
            (new TaxPermissionSeeder)->run();
        }

        if (Industry::count() === 0) {
            (new IndustryTaxonomyTestSeeder)->run();
        }
    }
}

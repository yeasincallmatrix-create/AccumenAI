<?php

namespace Tests;

use App\Models\ApprovalWorkflow;
use App\Models\AssessmentType;
use App\Models\ChartOfAccount;
use App\Models\Component;
use App\Models\Currency;
use App\Models\ExamType;
use App\Models\FiscalYear;
use App\Models\GradeScale;
use App\Models\Industry;
use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SubscriptionPackage;
use App\Support\BranchContext;
use App\Support\TenantContext;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\IndustryTaxonomyTestSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SystemRoleSeeder;
use Database\Seeders\TaxPermissionSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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

        $this->seedCountryCurrencyMap();

        $this->seedRoles();

        $this->seedPermissions();

        if (Industry::count() === 0) {
            (new IndustryTaxonomyTestSeeder)->run();
        }

        // Phase 2 Batch 1 (Option A): global-safe reference masters are
        // auto-seeded. Tenant-scoped helpers below (approval workflows,
        // chart of accounts, fiscal years) are OPT-IN — tests must pass an
        // institute id — so setUp() never creates orphan tenant rows.
        $this->seedReferenceMasters();
    }

    /**
     * Idempotent role seed (extracted from setUp, no behaviour change).
     */
    protected function seedRoles(): void
    {
        if (Role::where('slug', 'institute-owner')->whereNull('institute_id')->doesntExist()) {
            (new RoleSeeder)->run();
        }

        $requiredRoles = ['institute-owner', 'institute-admin', 'branch-manager', 'teacher', 'accountant', 'receptionist', 'exam-controller', 'trainer'];
        $existingRoles = Role::whereNull('institute_id')->pluck('slug')->toArray();
        if (count(array_diff($requiredRoles, $existingRoles)) > 0) {
            (new SystemRoleSeeder)->run();
        }
    }

    /**
     * Idempotent permission seed (extracted from setUp, no behaviour change).
     */
    protected function seedPermissions(): void
    {
        $requiredPerms = ['tax.view', 'tax.manage', 'tax.report', 'tax.settings', 'tax.audit'];
        $existingPerms = Permission::whereIn('slug', $requiredPerms)->pluck('slug')->toArray();
        if (count(array_diff($requiredPerms, $existingPerms)) > 0) {
            (new TaxPermissionSeeder)->run();
        }
    }

    /**
     * Global-safe reference masters. All rows use institute_id NULL so they
     * are shared across tenants. Idempotent via firstOrCreate + count guards.
     */
    protected function seedReferenceMasters(): void
    {
        if (Schema::hasTable('components') && DB::table('components')->count() === 0) {
            foreach ([
                ['slug' => 'written', 'name' => 'Written', 'display_order' => 1],
                ['slug' => 'mcq', 'name' => 'MCQ', 'display_order' => 2],
                ['slug' => 'practical', 'name' => 'Practical', 'display_order' => 3],
                ['slug' => 'viva', 'name' => 'Viva', 'display_order' => 4],
            ] as $row) {
                Component::firstOrCreate(
                    ['slug' => $row['slug'], 'institute_id' => null],
                    ['name' => $row['name'], 'display_order' => $row['display_order'], 'status' => true],
                );
            }
        } else {
            // Top-up: individual slugs may still be missing even when table is non-empty.
            if (Schema::hasTable('components')) {
                foreach (['written' => 'Written', 'mcq' => 'MCQ'] as $slug => $name) {
                    if (Component::where('slug', $slug)->whereNull('institute_id')->doesntExist()) {
                        Component::firstOrCreate(
                            ['slug' => $slug, 'institute_id' => null],
                            ['name' => $name, 'status' => true],
                        );
                    }
                }
            }
        }

        if (Schema::hasTable('assessment_types') && DB::table('assessment_types')->count() === 0) {
            foreach ([
                ['slug' => 'first-term', 'name' => 'First Term', 'display_order' => 1],
                ['slug' => 'mid-term', 'name' => 'Mid Term', 'display_order' => 2],
                ['slug' => 'final', 'name' => 'Final', 'display_order' => 3],
            ] as $row) {
                AssessmentType::firstOrCreate(
                    ['slug' => $row['slug'], 'institute_id' => null],
                    ['name' => $row['name'], 'display_order' => $row['display_order'], 'status' => true],
                );
            }
        }

        if (Schema::hasTable('exam_types') && DB::table('exam_types')->count() === 0) {
            foreach ([
                ['slug' => 'class-test', 'name' => 'Class Test', 'weight_percent' => 10],
                ['slug' => 'midterm', 'name' => 'Midterm', 'weight_percent' => 30],
                ['slug' => 'final', 'name' => 'Final', 'weight_percent' => 60],
            ] as $row) {
                ExamType::firstOrCreate(
                    ['slug' => $row['slug'], 'institute_id' => null],
                    ['name' => $row['name'], 'weight_percent' => $row['weight_percent']],
                );
            }
        }

        if (Schema::hasTable('subscription_packages')
            && SubscriptionPackage::where('slug', 'free')->doesntExist()) {
            SubscriptionPackage::firstOrCreate(
                ['slug' => 'free'],
                ['name' => 'FREE', 'price_monthly' => 0, 'price_yearly' => 0, 'is_default' => true, 'status' => 'active'],
            );
        }

        if (Schema::hasTable('grade_scales') && DB::table('grade_scales')->count() === 0) {
            GradeScale::firstOrCreate(
                ['name' => 'Global Default Grade Scale', 'institute_id' => null],
                ['status' => true, 'display_order' => 0],
            );
        }
    }

    /**
     * OPT-IN: seed a default approval workflow for one institute.
     * No-op when $instituteId is null so setUp() never creates orphan rows.
     * Resolves created_by from the institute's first user to satisfy the
     * approval_workflows_created_by FK (NULL when no user exists).
     */
    protected function seedApprovalWorkflows(?int $instituteId = null): ?ApprovalWorkflow
    {
        if ($instituteId === null || ! Schema::hasTable('approval_workflows')) {
            return null;
        }

        $institute = Institute::find($instituteId);
        if ($institute === null) {
            return null;
        }

        $creatorId = InstituteUser::where('institute_id', $institute->id)->min('id');

        $workflow = ApprovalWorkflow::firstOrCreate(
            ['institute_id' => $institute->id, 'name' => 'Default Expense Workflow'],
            ['module' => 'expense', 'amount_from' => 0, 'amount_to' => 999999, 'is_active' => true, 'created_by' => $creatorId],
        );

        if (Schema::hasTable('approval_steps') && $workflow->steps()->count() === 0) {
            $managerId = Role::where('slug', 'branch-manager')->whereNull('institute_id')->value('id');
            $adminId = Role::where('slug', 'institute-admin')->whereNull('institute_id')->value('id');
            $workflow->steps()->createMany([
                ['institute_id' => $institute->id, 'step_order' => 1, 'approver_role_id' => $managerId],
                ['institute_id' => $institute->id, 'step_order' => 2, 'approver_role_id' => $adminId],
            ]);
        }

        return $workflow->fresh();
    }

    /**
     * OPT-IN: seed a minimal chart of accounts for one institute.
     * Creates the codes AccountingIntegrationTest looks up (1001 cash,
     * 4001 tuition income). No-op when $instituteId is null.
     */
    protected function seedChartOfAccounts(?int $instituteId = null, ?int $branchId = null): void
    {
        if ($instituteId === null || ! Schema::hasTable('chart_of_accounts')) {
            return;
        }

        if (Institute::whereKey($instituteId)->doesntExist()) {
            return;
        }

        $defaults = [
            ['code' => '1001', 'name' => 'Cash in Hand', 'type' => 'asset', 'is_cash' => true],
            ['code' => '4001', 'name' => 'Tuition Income', 'type' => 'income'],
        ];

        foreach ($defaults as $row) {
            ChartOfAccount::firstOrCreate(
                ['institute_id' => $instituteId, 'code' => $row['code']],
                [
                    'branch_id' => $branchId,
                    'name' => $row['name'],
                    'type' => $row['type'],
                    'is_cash' => $row['is_cash'] ?? false,
                    'is_active' => true,
                    'is_system' => true,
                ],
            );
        }
    }

    /**
     * OPT-IN: seed the current fiscal year for one institute.
     * No-op when $instituteId is null.
     */
    protected function seedFiscalYear(?int $instituteId = null, ?int $branchId = null): ?FiscalYear
    {
        if ($instituteId === null || ! Schema::hasTable('fiscal_years')) {
            return null;
        }

        if (Institute::whereKey($instituteId)->doesntExist()) {
            return null;
        }

        return FiscalYear::firstOrCreate(
            ['institute_id' => $instituteId, 'branch_id' => $branchId, 'is_current' => true],
            ['name' => 'FY '.date('Y'), 'start_date' => date('Y-01-01'), 'end_date' => date('Y-12-31'), 'status' => 'open'],
        );
    }

    protected function seedCountryCurrencyMap(): void
    {
        if (! Schema::hasTable('country_currency_map')) {
            return;
        }

        if (DB::table('country_currency_map')->count() > 0) {
            return;
        }

        foreach (CurrencySeeder::countryCurrencyMap() as $map) {
            DB::table('country_currency_map')->updateOrInsert(
                ['country_code' => $map['country_code']],
                $map,
            );
        }
    }
}

<?php

namespace Tests;

use App\Models\ApprovalWorkflow;
use App\Models\AssessmentType;
use App\Models\ChartOfAccount;
use App\Models\Component;
use App\Models\Currency;
use App\Models\ExamType;
use App\Models\FiscalYear;
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
use Database\Seeders\AccountingPermissionSeeder;
use Database\Seeders\TaxPermissionSeeder;
use Database\Seeders\TestInstituteFixtureSeeder;
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

        // Module registry + package modules (idempotent — updateOrCreate).
        if (Schema::hasTable('module_registry')) {
            (new \Database\Seeders\ModuleRegistrySeeder)->run();
        }

        // B82: global reference rows tests resolve via firstOrFail()
        // (document categories, lead statuses, themes). Idempotent —
        // each seeder no-ops when its rows already exist.
        $this->seedSharedCatalogs();

        // Phase 7 STEP 2A: test-only institute fixtures (Tutu Center,
        // Mawa Academy) for tests that hardcode firstOrFail() by name.
        // TEST-ONLY — never runs outside the testing environment.
        $this->seedTestInstitutes();

        // Phase 8 FIX 4B: remove orphaned institute-scoped roles that break
        // firstOrFail() lookups (e.g. scoped receptionist rows shadowing
        // the global one). TEST-ONLY via setUp().
        $this->cleanupOrphanedRoles();

        // B85b: remove stale all-NULL global grade scale left by old DB
        // dumps ('Global Default Grade Scale', scope 0:0:0:0). Its unique
        // scope_key collides with every test-created global scale.
        // TEST-ONLY via setUp(); idempotent no-op on a clean database.
        $this->cleanupOrphanGradeScale();

        // Phase E.2: enable advanced accounting on seeded institutes so
        // gated advanced routes (TB/GL/AL/journals/audit/periods) resolve
        // without per-file edits. Toggle tests set explicit state.
        if (Schema::hasColumn('institutes', 'advanced_accounting_enabled')) {
            DB::table('institutes')->update(['advanced_accounting_enabled' => true]);
            Institute::creating(function ($institute) {
                if ($institute->advanced_accounting_enabled === null) {
                    $institute->advanced_accounting_enabled = true;
                }
            });
        }
    }

    /**
     * Remove orphaned institute-scoped roles not referenced by any
     * membership (institution_user) or institute_user. These break
     * firstOrFail() lookups (scoped row wins over the global row).
     * Idempotent; only deletes demonstrably unreferenced rows.
     */
    protected function cleanupOrphanedRoles(): void
    {
        if (! Schema::hasTable('roles')) {
            return;
        }

        $orphanedIds = DB::table('roles')
            ->whereNotNull('institute_id')
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('institution_user')
                    ->whereColumn('institution_user.role_id', 'roles.id');
            })
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('institute_users')
                    ->whereColumn('institute_users.role_id', 'roles.id');
            })
            ->pluck('id');

        if ($orphanedIds->isNotEmpty()) {
            DB::table('roles')->whereIn('id', $orphanedIds)->delete();
        }
    }

    /**
     * B85b: delete the stale all-NULL global grade scale ('Global Default
     * Grade Scale') that old DB dumps carry. Identified by all four scope
     * columns NULL plus the exact dump name — never by id alone — so
     * legitimate scales are never touched. Bands deleted first explicitly
     * (grade_scale_rows FK cascades, but explicit order is safer).
     * Idempotent: no-op when the orphan is absent. Sees committed rows
     * only (runs inside the test transaction), so live test rows are
     * never visible here.
     */
    protected function cleanupOrphanGradeScale(): void
    {
        if (! Schema::hasTable('grade_scales')) {
            return;
        }

        $orphanId = DB::table('grade_scales')
            ->whereNull('institute_id')
            ->whereNull('country_id')
            ->whereNull('education_system_id')
            ->whereNull('academic_level_id')
            ->where('name', 'Global Default Grade Scale')
            ->value('id');

        if ($orphanId === null) {
            return;
        }

        DB::table('grade_scale_rows')->where('grade_scale_id', $orphanId)->delete();
        DB::table('grade_scales')->where('id', $orphanId)->delete();
    }

    /**
     * B82: seed shared global catalogs (no institute scope).
     * Guards keep this cheap on an already-seeded database.
     */
    protected function seedSharedCatalogs(): void
    {
        // Slug-specific guard: seed when the anchor rows tests resolve
        // are missing, even if the table already holds other rows.
        if (Schema::hasTable('document_categories')
            && \App\Models\DocumentCategory::where('slug', 'photo')->doesntExist()) {
            (new \Database\Seeders\DocumentCategorySeeder)->run();
        }

        if (Schema::hasTable('crm_lead_statuses')
            && \App\Models\CrmLeadStatus::where('is_default', true)->doesntExist()) {
            (new \Database\Seeders\CrmLeadStatusSeeder)->run();
        }

        // B87: ensure CRM lead sources tests resolve by slug exist
        // (walk_in, referral). Shared global catalog, no institute scope.
        // Inline firstOrCreate (no dedicated seeder file) — idempotent,
        // race-tolerant for paratest workers sharing monetix_test.
        if (Schema::hasTable('crm_lead_sources')) {
            foreach ([
                ['slug' => 'walk_in', 'name' => 'Walk-in', 'display_order' => 1],
                ['slug' => 'referral', 'name' => 'Referral', 'display_order' => 2],
            ] as $row) {
                if (\App\Models\CrmLeadSource::where('slug', $row['slug'])->doesntExist()) {
                    try {
                        \App\Models\CrmLeadSource::firstOrCreate(
                            ['slug' => $row['slug']],
                            ['name' => $row['name'], 'display_order' => $row['display_order'], 'status' => 'active'],
                        );
                    } catch (\Illuminate\Database\QueryException $e) {
                        // Parallel worker won the race — row exists now.
                        if (\App\Models\CrmLeadSource::where('slug', $row['slug'])->doesntExist()) {
                            throw $e;
                        }
                    }
                }
            }
        }

        // Slug-specific guard: the themes table ships with committed rows
        // that lack ocean-blue, so count() === 0 would never fire.
        if (Schema::hasTable('themes')
            && \App\Models\Theme::where('slug', 'ocean-blue')->doesntExist()) {
            (new \Database\Seeders\ThemeSeeder)->run();
        }
    }

    /**
     * TEST-ONLY fixture seed: institutes hardcoded by name in feature tests.
     * Guarded to the testing environment so production rows are never
     * touched (the user explicitly deleted "Tutu Center" from production).
     * No per-name early return (B83b): the seeder is fully idempotent
     * (firstOrCreate + race-tolerant), so always run it — a stale
     * existence guard would silently skip newly added fixtures.
     */
    protected function seedTestInstitutes(): void
    {
        if (! app()->environment('testing')) {
            return;
        }

        if (! Schema::hasTable('institutes')) {
            return;
        }

        (new TestInstituteFixtureSeeder)->run();
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

        $requiredAccountingPerms = ['accounts.view', 'journals.post', 'reports.financial.view', 'settings.accounting.manage'];
        $existingAccountingPerms = Permission::whereIn('slug', $requiredAccountingPerms)->pluck('slug')->toArray();
        if (count(array_diff($requiredAccountingPerms, $existingAccountingPerms)) > 0) {
            (new AccountingPermissionSeeder)->run();
        }

        // B92: AI core tool gating permissions (finance.view gates
        // get_financial_summary, crm.view gates get_crm_summary).
        // AiToolPermissionSeeder is idempotent via firstOrCreate +
        // insertOrIgnore — safe to call repeatedly. Guarded to avoid
        // re-running once both slugs exist. B79: race-tolerant for
        // paratest workers sharing monetix_test — concurrent firstOrCreate
        // on the unique slug index can deadlock (SQLSTATE 40001); when a
        // parallel worker won the race the rows exist now, so swallow,
        // otherwise rethrow (same pattern as B87 crm_lead_sources above).
        // DeadlockException must be listed explicitly: it extends
        // PDOException directly (sibling of QueryException), so catching
        // QueryException alone misses MySQL 1213 deadlocks.
        if (Permission::where('slug', 'finance.view')->doesntExist()
            || Permission::where('slug', 'crm.view')->doesntExist()) {
            try {
                (new \Database\Seeders\AiToolPermissionSeeder)->run();
            } catch (\Illuminate\Database\QueryException | \Illuminate\Database\DeadlockException $e) {
                if (Permission::where('slug', 'finance.view')->doesntExist()
                    || Permission::where('slug', 'crm.view')->doesntExist()) {
                    throw $e;
                }
            }
        }

        if (Permission::where('slug', 'institute.settings.module.view')->doesntExist()
            || Permission::where('slug', 'institute.settings.module.toggle')->doesntExist()) {
            try {
                (new \Database\Seeders\ModuleTogglePermissionSeeder)->run();
            } catch (\Illuminate\Database\QueryException | \Illuminate\Database\DeadlockException $e) {
                if (Permission::where('slug', 'institute.settings.module.view')->doesntExist()
                    || Permission::where('slug', 'institute.settings.module.toggle')->doesntExist()) {
                    throw $e;
                }
            }
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

        // B85: no global grade-scale seed. grade_scales.scope_key is unique
        // on (institute, country, system, level) with NULLs as 0, so a single
        // pre-seeded all-NULL row collides with every test-created global
        // scale (32 dup-key errors). Tests that need a global scale create
        // their own; no test references a harness-seeded default by name.
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
     * Creates the codes AccountingIntegrationTest looks up (1000 cash,
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
            ['code' => '1000', 'name' => 'Cash', 'type' => 'asset', 'is_cash' => true],
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

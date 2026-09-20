<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Phase 7c (B89) — AI tool permission registry repair.
 *
 * Production symptom: the AI core tools `get_financial_summary` and
 * `get_crm_summary` deny every non-owner actor (admin, accountant) because
 * the permission slugs they gate on (`finance.view` / `crm.view`,
 * see GetFinancialSummaryTool::permission() and GetCrmSummaryTool::permission())
 * are absent from the `permissions` table and no role holds them.
 *
 * This seeder (Option B2b — no AI permission seeder existed) creates the
 * two gating permissions and attaches them to the roles the test contract
 * plus product direction require:
 *   - finance.view → institute-owner, institute-admin, accountant
 *     (AiCoreToolingTest: admin is allowed get_financial_summary)
 *   - crm.view     → institute-owner, institute-admin, accountant
 *     (AiCoreToolingTest: accountant is allowed get_crm_summary)
 *
 * NOTE on slugs: the tools gate on `finance.view` / `crm.view` — there are
 * no `ai.*` permission slugs anywhere in the codebase, so none are created.
 *
 * NOTE on roles: no `sales` system role exists (see SystemRoleSeeder), so
 * the CRM grant covers the existing finance-facing roles only; teacher and
 * all other roles stay denied (fail-closed, no rows added for them).
 *
 * Idempotent via firstOrCreate + insertOrIgnore — safe to re-run.
 * Does NOT touch existing seeder logic.
 *
 * Run explicitly:
 *
 *   php artisan db:seed --class=AiToolPermissionSeeder
 */
class AiToolPermissionSeeder extends Seeder
{
    /**
     * Gating permissions required by the AI core tools.
     *
     * @return array<int, array{slug: string, module: string, name: string}>
     */
    public static function permissions(): array
    {
        return [
            ['slug' => 'finance.view', 'module' => 'finance', 'name' => 'View Finance'],
            ['slug' => 'crm.view', 'module' => 'crm', 'name' => 'View CRM'],
        ];
    }

    /**
     * Role slug => permission slugs to grant.
     *
     * @return array<string, array<int, string>>
     */
    public static function roleGrants(): array
    {
        return [
            'institute-owner' => ['finance.view', 'crm.view'],
            'institute-admin' => ['finance.view', 'crm.view'],
            'accountant' => ['finance.view', 'crm.view'],
        ];
    }

    public function run(): void
    {
        foreach (self::permissions() as $perm) {
            Permission::firstOrCreate(
                ['slug' => $perm['slug']],
                ['module' => $perm['module'], 'name' => $perm['name']],
            );
        }

        $permissionIds = Permission::whereIn(
            'slug',
            collect(self::roleGrants())->flatten()->unique()->all()
        )->pluck('id', 'slug');

        $attached = 0;
        foreach (self::roleGrants() as $roleSlug => $slugs) {
            $role = Role::where('slug', $roleSlug)->whereNull('institute_id')->first();

            if (! $role) {
                $this->command?->warn("AiToolPermissionSeeder: role [{$roleSlug}] not found, skipping its grants.");

                continue;
            }

            foreach ($slugs as $slug) {
                $permissionId = $permissionIds[$slug] ?? null;

                if ($permissionId === null) {
                    continue;
                }

                $attached += DB::table('role_permissions')->insertOrIgnore([
                    'role_id' => $role->id,
                    'permission_id' => $permissionId,
                ]);
            }
        }

        $this->command?->info("AI tool permissions seeded ({$attached} new role grant(s)).");
    }
}

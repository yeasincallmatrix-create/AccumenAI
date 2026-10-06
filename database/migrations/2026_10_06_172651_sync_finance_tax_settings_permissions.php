<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * The ten finance/tax/settings slugs being reconciled.
     *
     * @var array<string, array{module: string, name: string}>
     */
    private array $permissions = [
        'finance.view' => ['module' => 'finance', 'name' => 'View Finance'],
        'finance.manage' => ['module' => 'finance', 'name' => 'Finance Manage'],
        'tax.view' => ['module' => 'tax', 'name' => 'View Tax'],
        'tax.report' => ['module' => 'tax', 'name' => 'Tax Report'],
        'tax.audit' => ['module' => 'tax', 'name' => 'Tax Audit'],
        'tax.manage' => ['module' => 'tax', 'name' => 'Manage Tax'],
        'tax.settings' => ['module' => 'tax', 'name' => 'Tax Settings'],
        'settings.view' => ['module' => 'settings', 'name' => 'View Settings'],
        'settings.manage' => ['module' => 'settings', 'name' => 'Settings Manage'],
        'education.manage' => ['module' => 'education', 'name' => 'Education Manage'],
    ];

    /**
     * Grants are only ever written for GLOBAL roles (institute_id IS NULL).
     * Existing tenant-scoped grants are left untouched.
     *
     * @var array<string, array<int, string>>
     */
    private array $grants = [
        'finance.view' => ['accountant', 'institute-admin', 'institute-owner'],
        'finance.manage' => ['institute-owner'],
        'tax.view' => ['institute-owner'],
        'tax.report' => ['institute-owner'],
        'tax.audit' => ['institute-owner'],
        'tax.manage' => ['institute-owner'],
        'tax.settings' => ['institute-owner'],
        'settings.view' => ['institute-owner'],
        'settings.manage' => ['institute-owner'],
        'education.manage' => ['institute-owner'],
    ];

    /**
     * Slugs that existed in neither database before this migration, so they
     * are the only permission rows rollback is allowed to remove.
     *
     * @var array<int, string>
     */
    private array $newlySeeded = [
        'finance.manage',
        'settings.manage',
        'education.manage',
    ];

    public function up(): void
    {
        // Part A — add the slugs if the current database does not have them.
        foreach ($this->permissions as $slug => $meta) {
            if (DB::table('permissions')->where('slug', $slug)->exists()) {
                continue;
            }

            DB::table('permissions')->insert([
                'module' => $meta['module'],
                'name' => $meta['name'],
                'slug' => $slug,
                'created_at' => now(),
            ]);
        }

        // Part B — grant to the target global roles, skipping anything that
        // already exists (uq_role_permissions would reject duplicates anyway).
        foreach ($this->grants as $slug => $roleSlugs) {
            $permission = DB::table('permissions')->where('slug', $slug)->first();

            if (! $permission) {
                continue;
            }

            foreach ($roleSlugs as $roleSlug) {
                $role = DB::table('roles')
                    ->whereNull('institute_id')
                    ->where('slug', $roleSlug)
                    ->first();

                if (! $role) {
                    continue;
                }

                $exists = DB::table('role_permissions')
                    ->where('role_id', $role->id)
                    ->where('permission_id', $permission->id)
                    ->exists();

                if ($exists) {
                    continue;
                }

                DB::table('role_permissions')->insert([
                    'role_id' => $role->id,
                    'permission_id' => $permission->id,
                ]);
            }
        }

        // Part C — viewer is a system role; only touch rows still marked 0.
        $flipped = DB::table('roles')
            ->whereNull('institute_id')
            ->where('slug', 'viewer')
            ->where('is_system', 0)
            ->update(['is_system' => 1]);

        if ($flipped > 0) {
            Log::info('Marked global viewer role as a system role.', [
                'database' => DB::getDatabaseName(),
                'rows' => $flipped,
            ]);
        }
    }

    public function down(): void
    {
        // Only the three slugs that existed in neither database may go.
        foreach ($this->newlySeeded as $slug) {
            $permission = DB::table('permissions')->where('slug', $slug)->first();

            if (! $permission) {
                continue;
            }

            $grantedTo = DB::table('role_permissions')
                ->join('roles', 'roles.id', '=', 'role_permissions.role_id')
                ->where('role_permissions.permission_id', $permission->id)
                ->pluck('roles.slug')
                ->all();

            if ($grantedTo !== []) {
                Log::warning('Removing permission together with its grants.', [
                    'slug' => $slug,
                    'roles' => $grantedTo,
                ]);
            }

            // FK fk_role_permissions_permission is ON DELETE CASCADE.
            DB::table('permissions')->where('id', $permission->id)->delete();

            Log::info('Removed permission seeded by this migration.', ['slug' => $slug]);
        }

        // Only a test database had viewer.is_system = 0 before up() ran.
        $database = DB::getDatabaseName();

        if ($database === 'monetix_test' || str_ends_with($database, '_test')) {
            $restored = DB::table('roles')
                ->whereNull('institute_id')
                ->where('slug', 'viewer')
                ->where('is_system', 1)
                ->update(['is_system' => 0]);

            Log::info('Restored global viewer role to a non-system role.', [
                'database' => $database,
                'rows' => $restored,
            ]);

            return;
        }

        Log::warning('viewer.is_system left at 1 on a non-test database.', [
            'database' => $database,
        ]);
    }
};

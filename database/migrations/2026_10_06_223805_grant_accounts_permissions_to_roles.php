<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * Approved grant matrix for the accounts.* slugs (global roles only).
     * institute-owner already holds all three, so up() skips it idempotently.
     *
     * @var array<string, array<int, string>>
     */
    private array $matrix = [
        'accounts.view' => ['institute-owner', 'institute-admin', 'accountant', 'branch-manager', 'receptionist', 'viewer'],
        'accounts.edit' => ['institute-owner', 'institute-admin', 'accountant'],
        'accounts.delete' => ['institute-owner', 'institute-admin'],
    ];

    /**
     * Pairs this migration is known to CREATE.
     *
     * Step 1 verified, in BOTH target databases, that before this migration
     * only institute-owner held any accounts.* slug. Every pair below was
     * therefore absent beforehand, so rollback may remove exactly these and
     * nothing else. institute-owner's three pre-existing pairs are never
     * listed here and are never deleted.
     *
     * @var array<string, array<int, string>>
     */
    private array $createdByThisMigration = [
        'accounts.view' => ['institute-admin', 'accountant', 'branch-manager', 'receptionist', 'viewer'],
        'accounts.edit' => ['institute-admin', 'accountant'],
        'accounts.delete' => ['institute-admin'],
    ];

    public function up(): void
    {
        foreach ($this->matrix as $slug => $roleSlugs) {
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

                Log::info('Granted accounts permission to global role.', [
                    'database' => DB::getDatabaseName(),
                    'permission' => $slug,
                    'role' => $roleSlug,
                ]);
            }
        }
    }

    public function down(): void
    {
        foreach ($this->createdByThisMigration as $slug => $roleSlugs) {
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

                $deleted = DB::table('role_permissions')
                    ->where('role_id', $role->id)
                    ->where('permission_id', $permission->id)
                    ->delete();

                if ($deleted > 0) {
                    Log::info('Removed accounts grant seeded by this migration.', [
                        'database' => DB::getDatabaseName(),
                        'permission' => $slug,
                        'role' => $roleSlug,
                    ]);
                }
            }
        }

        Log::warning('Rolled back accounts.* grants. Pre-existing grants (notably institute-owner) were preserved; if a listed pair was seeded elsewhere it was removed too.', [
            'database' => DB::getDatabaseName(),
        ]);
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * Extended finance.* grant matrix, mirroring the accounts.* pattern
     * approved in A2.1b (accountant/admin manage; branch-manager views).
     *
     * @var array<string, array<int, string>>
     */
    private array $matrix = [
        'finance.view' => ['institute-owner', 'institute-admin', 'accountant', 'branch-manager'],
        'finance.manage' => ['institute-owner', 'institute-admin', 'accountant'],
    ];

    /**
     * The three pairs this migration CREATEs.
     *
     * Step 1 verified, in BOTH target databases, that the pre-existing set was
     * exactly: finance.manage -> institute-owner, and finance.view ->
     * accountant | institute-admin | institute-owner. Every pair below was
     * therefore absent beforehand, so rollback may remove exactly these.
     *
     * @var array<string, array<int, string>>
     */
    private array $createdByThisMigration = [
        'finance.view' => ['branch-manager'],
        'finance.manage' => ['accountant', 'institute-admin'],
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

                Log::info('Granted finance permission to global role.', [
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
                    Log::info('Removed finance grant seeded by this migration.', [
                        'database' => DB::getDatabaseName(),
                        'permission' => $slug,
                        'role' => $roleSlug,
                    ]);
                }
            }
        }

        Log::warning('Rolled back extended finance.* grants. Pre-existing grants (institute-owner/finance.manage, finance.view for accountant|institute-admin|institute-owner) were preserved.', [
            'database' => DB::getDatabaseName(),
        ]);
    }
};

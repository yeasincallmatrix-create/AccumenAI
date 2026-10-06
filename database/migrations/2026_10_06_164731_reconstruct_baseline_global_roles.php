<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The nine canonical global roles (institute_id IS NULL).
     * Reconstructed from the lost 0000_00_00_000000_baseline_roles file.
     *
     * @var array<string, string>
     */
    private array $globalRoles = [
        'accountant' => 'Accountant',
        'branch-manager' => 'Branch Manager',
        'exam-controller' => 'Exam Controller',
        'institute-admin' => 'Institute Admin',
        'institute-owner' => 'Institute Owner',
        'receptionist' => 'Receptionist',
        'teacher' => 'Teacher',
        'trainer' => 'Trainer',
        'viewer' => 'Viewer',
    ];

    /**
     * Roles this migration is allowed to remove on rollback.
     *
     * @var array<int, string>
     */
    private array $removableOnRollback = [
        'accountant',
        'branch-manager',
        'exam-controller',
        'institute-admin',
        'viewer',
    ];

    public function up(): void
    {
        foreach ($this->globalRoles as $slug => $name) {
            $exists = DB::table('roles')
                ->whereNull('institute_id')
                ->where('slug', $slug)
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('roles')->insert([
                'institute_id' => null,
                'name' => $name,
                'slug' => $slug,
                'is_system' => 1,
                'status' => 'active',
                'created_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        foreach ($this->removableOnRollback as $slug) {
            $role = DB::table('roles')
                ->whereNull('institute_id')
                ->where('slug', $slug)
                ->first();

            if (! $role) {
                continue;
            }

            if ($this->isReferenced($role->id)) {
                Log::warning('Rolled-back role kept: still referenced by users.', [
                    'role_id' => $role->id,
                    'slug' => $slug,
                ]);

                continue;
            }

            DB::table('roles')->where('id', $role->id)->delete();
        }
    }

    private function isReferenced(int $roleId): bool
    {
        foreach (['institute_users', 'institution_user'] as $table) {
            if (Schema::hasTable($table) && DB::table($table)->where('role_id', $roleId)->exists()) {
                return true;
            }
        }

        return false;
    }
};

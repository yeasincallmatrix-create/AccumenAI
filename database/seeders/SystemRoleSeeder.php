<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * Seed the global system-level staff roles (institute_id = NULL).
 *
 * These are the foundational roles defined in seed_data.sql that every
 * institute inherits via RoleTemplateService. Only institute-owner was
 * previously seeded; the remaining 7 were missing, causing
 * ModelNotFoundException in ~40+ tests.
 *
 * Idempotent via firstOrCreate — safe to re-run.
 *
 * Run explicitly:
 *
 *   php artisan db:seed --class=SystemRoleSeeder
 */
class SystemRoleSeeder extends Seeder
{
    /**
     * System-level roles: slug => [name, is_system].
     *
     * All have institute_id = NULL (global, not per-institute).
     * Permission assignment is handled by RoleTemplateService per-institute.
     */
    public static function roles(): array
    {
        return [
            'institute-admin' => ['name' => 'Institute Admin', 'is_system' => true],
            'branch-manager'  => ['name' => 'Branch Manager', 'is_system' => true],
            'teacher'         => ['name' => 'Teacher', 'is_system' => true],
            'accountant'      => ['name' => 'Accountant', 'is_system' => true],
            'receptionist'    => ['name' => 'Receptionist', 'is_system' => true],
            'exam-controller' => ['name' => 'Exam Controller', 'is_system' => true],
            'trainer'         => ['name' => 'Trainer', 'is_system' => true],
        ];
    }

    public function run(): void
    {
        $created = 0;
        $existing = 0;

        foreach (self::roles() as $slug => $def) {
            $role = Role::firstOrCreate(
                ['institute_id' => null, 'slug' => $slug],
                ['name' => $def['name'], 'is_system' => $def['is_system'], 'status' => 'active']
            );

            if ($role->wasRecentlyCreated) {
                $created++;
            } else {
                $existing++;
            }
        }

        $this->command?->info(
            "System roles: {$created} created, {$existing} existing."
        );
    }
}

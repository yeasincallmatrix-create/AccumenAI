<?php

namespace App\Services\System;

use Illuminate\Support\Facades\DB;

/**
 * Step 101 — Seed Integrity Protection.
 * Audits required system data that must exist for the platform to operate.
 */
class SeedIntegrityService
{
    /**
     * Required roles that must exist (slug => name).
     */
    public const REQUIRED_ROLES = [
        'institute-owner' => 'Institute Owner',
        'branch-manager' => 'Branch Manager',
        'teacher' => 'Teacher',
        'accountant' => 'Accountant',
    ];

    /**
     * Required permissions (sample critical ones).
     */
    public const REQUIRED_PERMISSIONS = [
        'education.manage',
        'students.view',
        'students.manage',
        'finance.view',
        'finance.manage',
        'settings.manage',
    ];

    public function check(): array
    {
        $missing = [];
        $details = [];

        // Roles
        foreach (self::REQUIRED_ROLES as $slug => $name) {
            $exists = DB::table('roles')->where('slug', $slug)->exists();
            if (! $exists) {
                $missing[] = "role:$slug";
                $details["role:$slug"] = "Missing role $slug";
            }
        }

        // Permissions
        foreach (self::REQUIRED_PERMISSIONS as $perm) {
            $exists = DB::table('permissions')->where('slug', $perm)->exists();
            if (! $exists) {
                $missing[] = "permission:$perm";
                $details["permission:$perm"] = "Missing permission $perm";
            }
        }

        // Modules — check from ModuleRegistry or config
        $modulesCount = 0;
        try {
            $modulesCount = DB::table('module_registry')->count();
            if ($modulesCount === 0) {
                $missing[] = 'modules:empty';
                $details['modules:empty'] = 'No modules registered';
            }
        } catch (\Exception $e) {
            // table may not exist yet — treat as missing
            $missing[] = 'modules:table_missing';
            $details['modules:table_missing'] = $e->getMessage();
        }

        // Industries — config vs industry_settings
        $industries = array_keys(config('industry_rules.global.industries', []));
        foreach ($industries as $ind) {
            // At least 'all' must exist
        }
        $hasAll = DB::table('industry_settings')->where('industry_key', 'all')->exists();
        if (! $hasAll) {
            $missing[] = 'industry_settings:all';
            $details['industry_settings:all'] = 'Missing default industry_settings for key=all';
        }

        // Themes
        $themesCount = DB::table('themes')->count();
        if ($themesCount === 0) {
            $missing[] = 'themes:empty';
            $details['themes:empty'] = 'No themes seeded';
        }
        $hasDefaultTheme = DB::table('themes')->where('is_default', true)->exists();
        if (! $hasDefaultTheme) {
            $missing[] = 'themes:default';
            $details['themes:default'] = 'No default theme';
        }

        // Countries
        $countriesCount = DB::table('countries')->count();
        if ($countriesCount === 0) {
            $missing[] = 'countries:empty';
            $details['countries:empty'] = 'No countries seeded';
        }

        // Administrative levels
        $levelsCount = DB::table('administrative_levels')->count();
        if ($levelsCount === 0) {
            $missing[] = 'administrative_levels:empty';
            $details['administrative_levels:empty'] = 'No administrative levels';
        }

        return [
            'missing' => $missing,
            'details' => $details,
            'healthy' => empty($missing),
        ];
    }

    public function seedDefaults(): array
    {
        $created = [];

        // Seed industry_settings default
        if (! DB::table('industry_settings')->where('industry_key', 'all')->exists()) {
            $theme = DB::table('themes')->where('is_default', true)->value('slug') ?? DB::table('themes')->value('slug') ?? 'ocean-blue';
            DB::table('industry_settings')->insert([
                'industry_key' => 'all',
                'theme_slug' => $theme,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $created[] = 'industry_settings:all';
        }

        return $created;
    }
}

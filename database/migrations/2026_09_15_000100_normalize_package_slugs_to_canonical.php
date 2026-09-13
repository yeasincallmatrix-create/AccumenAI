<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Map legacy slugs to canonical FREE/BASIC/ADVANCED/PREMIUM
        $map = [
            'free' => ['slug' => 'FREE', 'name' => 'FREE'],
            'starter' => ['slug' => 'BASIC', 'name' => 'BASIC'],
            'professional' => ['slug' => 'ADVANCED', 'name' => 'ADVANCED'],
            'enterprise' => ['slug' => 'PREMIUM', 'name' => 'PREMIUM'],
        ];

        foreach ($map as $old => $new) {
            $pkg = DB::table('subscription_packages')->whereRaw('LOWER(slug) = ?', [$old])->first();
            if ($pkg) {
                // Update to canonical if not already exists
                $exists = DB::table('subscription_packages')->where('slug', $new['slug'])->exists();
                if (! $exists) {
                    DB::table('subscription_packages')->where('id', $pkg->id)->update([
                        'slug' => $new['slug'],
                        'name' => $new['name'],
                        'updated_at' => now(),
                    ]);
                } else {
                    // Canonical already exists (idempotent) — reassign institutes from old to canonical and remove old
                    $canonicalId = DB::table('subscription_packages')->where('slug', $new['slug'])->value('id');
                    DB::table('institutes')->where('package_id', $pkg->id)->update(['package_id' => $canonicalId]);
                    // Move package_modules if missing
                    $oldModules = DB::table('package_modules')->where('package_id', $pkg->id)->get();
                    foreach ($oldModules as $pm) {
                        $has = DB::table('package_modules')->where('package_id', $canonicalId)->where('module_key', $pm->module_key)->exists();
                        if (! $has) {
                            DB::table('package_modules')->insert([
                                'package_id' => $canonicalId,
                                'module_key' => $pm->module_key,
                                'enabled' => $pm->enabled,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);
                        }
                    }
                    DB::table('package_modules')->where('package_id', $pkg->id)->delete();
                    DB::table('subscription_packages')->where('id', $pkg->id)->delete();
                }
            }
        }

        // Ensure missing canonical packages exist (fresh DB) — use actual table columns
        $allCanonical = [
            'FREE' => ['name' => 'FREE', 'price_monthly' => 0, 'price_yearly' => 0],
            'BASIC' => ['name' => 'BASIC', 'price_monthly' => 1999, 'price_yearly' => 19990],
            'ADVANCED' => ['name' => 'ADVANCED', 'price_monthly' => 4999, 'price_yearly' => 49990],
            'PREMIUM' => ['name' => 'PREMIUM', 'price_monthly' => 9999, 'price_yearly' => 99990],
        ];
        foreach ($allCanonical as $slug => $attrs) {
            if (! DB::table('subscription_packages')->where('slug', $slug)->exists()) {
                DB::table('subscription_packages')->insert([
                    'slug' => $slug,
                    'name' => $attrs['name'],
                    'price_monthly' => $attrs['price_monthly'],
                    'price_yearly' => $attrs['price_yearly'],
                    'is_default' => false,
                    'status' => 'active',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // Ensure FREE package has correct minimal modules if empty (backward compat)
        $freeId = DB::table('subscription_packages')->where('slug', 'FREE')->value('id');
        if ($freeId) {
            $freeModules = DB::table('package_modules')->where('package_id', $freeId)->pluck('module_key')->toArray();
            if (empty($freeModules)) {
                foreach (['crm','notifications'] as $m) {
                    DB::table('package_modules')->insert([
                        'package_id' => $freeId,
                        'module_key' => $m,
                        'enabled' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        $reverse = [
            'FREE' => ['slug' => 'free', 'name' => 'Free'],
            'BASIC' => ['slug' => 'starter', 'name' => 'Starter'],
            'ADVANCED' => ['slug' => 'professional', 'name' => 'Professional'],
            'PREMIUM' => ['slug' => 'enterprise', 'name' => 'Enterprise'],
        ];
        foreach ($reverse as $canon => $old) {
            $pkg = DB::table('subscription_packages')->where('slug', $canon)->first();
            if ($pkg) {
                DB::table('subscription_packages')->where('id', $pkg->id)->update([
                    'slug' => $old['slug'],
                    'name' => $old['name'],
                    'updated_at' => now(),
                ]);
            }
        }
    }
};

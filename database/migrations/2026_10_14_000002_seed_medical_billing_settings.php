<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Medical billing journal settings (defaults, one row per institute).
 *
 *   medical.billing.revenue_mode.{institute_id} = 'accrual' | 'cash'
 *   medical.billing.auto_post.{institute_id}    = '1' | '0'
 *
 * `settings.key` is globally UNIQUE (key/value only, no institute column), so
 * per-tenant values are carried by a tenant-suffixed key — the same
 * Setting::get()/set() mechanism the rest of the app uses, never raw INSERTs.
 * Tenants seeded here default to accrual + auto-post on; any tenant not
 * seeded (created later) falls back to config('medical.billing.*').
 *
 * Idempotent: an existing value is never overwritten.
 */
return new class extends Migration
{
    private const DEFAULTS = [
        'medical.billing.revenue_mode' => 'accrual',
        'medical.billing.auto_post' => '1',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('settings') || ! Schema::hasTable('institutes')) {
            return;
        }

        $instituteIds = DB::table('institutes')
            ->whereNull('deleted_at')
            ->pluck('id');

        foreach ($instituteIds as $instituteId) {
            foreach (self::DEFAULTS as $key => $value) {
                $scopedKey = $key.'.'.$instituteId;

                if (Setting::get($scopedKey) === null) {
                    Setting::set($scopedKey, $value);
                }
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('settings')) {
            return;
        }

        DB::table('settings')
            ->where('key', 'like', 'medical.billing.%')
            ->delete();
    }
};

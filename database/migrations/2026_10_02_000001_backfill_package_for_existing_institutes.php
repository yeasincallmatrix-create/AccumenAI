<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Organizations created before the mandatory package-selection step already
 * run on the FREE fallback. Pin them to the FREE package so the new dashboard
 * gate (App\Http\Middleware\EnsurePackageSelected) does not lock out existing
 * tenants — only organizations created from now on start with package_id NULL
 * and must pick a plan during registration.
 */
return new class extends Migration
{
    public function up(): void
    {
        $freeId = DB::table('subscription_packages')
            ->whereRaw('LOWER(slug) = ?', ['free'])
            ->value('id');

        if (! $freeId) {
            return;
        }

        DB::table('institutes')
            ->whereNull('package_id')
            ->update(['package_id' => $freeId]);
    }

    public function down(): void
    {
        // intentionally no-op: do not revert package assignment
    }
};

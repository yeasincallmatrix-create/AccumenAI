<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $freeId = DB::table('subscription_packages')
            ->where('slug', 'free')
            ->value('id');

        if (! $freeId) {
            return; // nothing to assign; safe no-op
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

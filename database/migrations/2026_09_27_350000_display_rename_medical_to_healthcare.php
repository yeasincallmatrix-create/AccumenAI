<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {
            // 1. Rename medical row's DISPLAY NAME (slug stays 'medical')
            $updated = DB::table('industries')
                ->where('slug', 'medical')
                ->update([
                    'name' => 'Healthcare',
                    'description' => 'Healthcare industry (hospitals, clinics, diagnostics, pharmacy)',
                    'updated_at' => now(),
                ]);
            echo "Medical row display renamed to Healthcare: {$updated}\n";

            // 2. Deactivate duplicate healthcare slug row
            $deactivated = DB::table('industries')
                ->where('slug', 'healthcare')
                ->update([
                    'status' => 'inactive',
                    'description' => 'Legacy duplicate — merged into medical (display: Healthcare). Full key rename deferred.',
                    'updated_at' => now(),
                ]);
            echo "Healthcare duplicate deactivated: {$deactivated}\n";

            // 3. Verify active count
            $active = DB::table('industries')->where('status', 'active')->count();
            echo "Active industries: {$active}\n";
        });
    }

    public function down(): void
    {
        DB::table('industries')->where('slug', 'medical')
            ->update(['name' => 'Medical', 'description' => null, 'updated_at' => now()]);
        DB::table('industries')->where('slug', 'healthcare')
            ->update(['status' => 'active', 'description' => null, 'updated_at' => now()]);
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Single healthcare industry tab (supersedes the earlier display rename
     * that kept a duplicate 'medical' row and deactivated 'healthcare').
     *
     * Ensures: healthcare active named Healthcare; medical row removed.
     */
    public function up(): void
    {
        DB::transaction(function () {
            // 1. Ensure the canonical healthcare row is active.
            $updated = DB::table('industries')
                ->where('slug', 'healthcare')
                ->update([
                    'name' => 'Healthcare',
                    'description' => 'Healthcare industry (hospitals, clinics, diagnostics, pharmacy)',
                    'status' => 'active',
                    'updated_at' => now(),
                ]);
            echo "Healthcare row ensured active: {$updated}\n";

            // 2. Remove the duplicate medical slug row.
            $deleted = DB::table('industries')->where('slug', 'medical')->delete();
            echo "Duplicate medical row removed: {$deleted}\n";

            // 3. Verify active count
            $active = DB::table('industries')->where('status', 'active')->count();
            echo "Active industries: {$active}\n";
        });
    }

    public function down(): void
    {
        echo "No-op: single-healthcare merge is not auto-reversible.\n";
    }
};

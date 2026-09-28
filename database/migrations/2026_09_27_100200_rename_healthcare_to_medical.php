<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Single healthcare industry (reversal of the earlier Option D rename).
     *
     * Decision: 'healthcare' is canonical for industry identity
     * (institutes.industry, config, subcategories, admin tabs).
     * 'medical' remains ONLY as a module_registry key (medical.*).
     *
     *   package_industries.industry_key: medical -> healthcare (all rows)
     *   package_industry_modules.industry_key: medical -> healthcare
     *   industries: DELETE slug=medical duplicate; healthcare kept active.
     */
    public function up(): void
    {
        DB::transaction(function () {
            if (Schema::hasTable('package_industries')) {
                $renamed = DB::table('package_industries')
                    ->where('industry_key', 'medical')
                    ->update(['industry_key' => 'healthcare', 'updated_at' => now()]);
                echo "Renamed {$renamed} package_industries rows: medical -> healthcare.\n";
            }

            if (Schema::hasTable('package_industry_modules')) {
                $rekeyed = DB::table('package_industry_modules')
                    ->where('industry_key', 'medical')
                    ->update(['industry_key' => 'healthcare', 'updated_at' => now()]);
                echo "Re-keyed {$rekeyed} package_industry_modules rows: medical -> healthcare.\n";
            }

            if (Schema::hasTable('industries') && Schema::hasColumn('industries', 'slug')) {
                $deleted = DB::table('industries')->where('slug', 'medical')->delete();
                echo "Deleted duplicate industries slug=medical: {$deleted} row(s).\n";

                DB::table('industries')
                    ->where('slug', 'healthcare')
                    ->update([
                        'name' => 'Healthcare',
                        'description' => 'Healthcare industry (hospitals, clinics, diagnostics, pharmacy)',
                        'status' => 'active',
                        'updated_at' => now(),
                    ]);
                echo "Ensured industries slug=healthcare active.\n";
            }
        });
    }

    public function down(): void
    {
        // No safe automatic reversal: re-creating the duplicate slug would
        // re-split the admin tabs. Restore from backup if needed.
        echo "No-op: single-healthcare merge is not auto-reversible.\n";
    }
};

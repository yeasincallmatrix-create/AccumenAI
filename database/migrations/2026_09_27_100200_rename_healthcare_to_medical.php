<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Foundation Fix 3 (Option D - scoped rename).
     *
     *   package_industries.industry_key: healthcare -> medical (4 rows)
     *   industries: INSERT new row slug=medical (collision-free check)
     *   industries slug=healthcare: KEPT, marked as legacy/deprecated
     *   industry_subcategories / institutes / config keys / seeder mappings:
     *   UNCHANGED (see TODO(big-bang-rename) in the foundation commit).
     *
     * STOP checks (unexpected state aborts the migration):
     *   - package_industries healthcare/medical must be (4/0) pending,
     *     (0/4) applied or (0/0) unseeded (test DB); anything else throws.
     *   - industries.slug 'medical' must be new before the insert.
     */
    public function up(): void
    {
        DB::transaction(function () {
            if (Schema::hasTable('package_industries')) {
                $healthcare = DB::table('package_industries')
                    ->where('industry_key', 'healthcare')
                    ->count();
                $medical = DB::table('package_industries')
                    ->where('industry_key', 'medical')
                    ->count();

                if ($healthcare > 0 && $medical > 0) {
                    throw new RuntimeException(
                        "STOP: mixed package_industries state (healthcare={$healthcare}, medical={$medical})."
                    );
                }

                if ($medical === 0 && ! in_array($healthcare, [0, 4], true)) {
                    throw new RuntimeException(
                        "STOP: unexpected package_industries healthcare count {$healthcare} (expected 4 pending / 0 applied-or-unseeded)."
                    );
                }

                if ($healthcare === 4 && $medical === 0) {
                    $renamed = DB::table('package_industries')
                        ->where('industry_key', 'healthcare')
                        ->update(['industry_key' => 'medical', 'updated_at' => now()]);
                    echo "Renamed {$renamed} package_industries rows: healthcare -> medical.\n";
                } else {
                    echo "package_industries: healthcare={$healthcare}, medical={$medical} - nothing to rename.\n";
                }
            }

            if (Schema::hasTable('industries') && Schema::hasColumn('industries', 'slug')) {
                $slugTaken = DB::table('industries')->where('slug', 'medical')->exists();

                if ($slugTaken) {
                    echo "industries.slug 'medical' already present - insert skipped (collision-free check passed).\n";
                } else {
                    DB::table('industries')->insert([
                        'name' => 'Medical',
                        'slug' => 'medical',
                        'description' => 'Medical / hospital industry (package key). Legacy taxonomy key: healthcare.',
                        'status' => 'active',
                        'sort_order' => 14,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    echo "Created industries row slug=medical.\n";
                }

                $note = 'Legacy taxonomy key - superseded by medical for package mapping.';
                $marked = DB::table('industries')
                    ->where('slug', 'healthcare')
                    ->where(function ($query) use ($note) {
                        $query->whereNull('description')
                            ->orWhere('description', '!=', $note);
                    })
                    ->update(['description' => $note, 'updated_at' => now()]);
                echo "Kept industries slug=healthcare as legacy (marked {$marked} row(s)).\n";
            }

            echo "industry_subcategories untouched (healthcare preserved per Option D).\n";
        });
    }

    public function down(): void
    {
        DB::transaction(function () {
            if (Schema::hasTable('industries')) {
                DB::table('industries')
                    ->where('slug', 'healthcare')
                    ->update(['description' => null, 'updated_at' => now()]);

                $legacyExists = DB::table('industries')
                    ->where('slug', 'healthcare')
                    ->exists();

                if ($legacyExists) {
                    DB::table('industries')->where('slug', 'medical')->delete();
                }
            }

            if (Schema::hasTable('package_industries')) {
                DB::table('package_industries')
                    ->where('industry_key', 'medical')
                    ->update(['industry_key' => 'healthcare', 'updated_at' => now()]);
            }
        });
    }
};

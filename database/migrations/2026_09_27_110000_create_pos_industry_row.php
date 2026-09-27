<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Part 2 finding B - industries row for POS.
     *
     * The pos registry parent exists (type=industry) and package_industries
     * maps pos -> 4 universal packages, but `industries` had no slug=pos row,
     * so /admin/package-industries?industry=pos fell back to the first
     * industry. Same pattern as the medical row created in Foundation Fixes.
     *
     * Collision-free: the insert is skipped when slug=pos already exists.
     */
    public function up(): void
    {
        if (! Schema::hasTable('industries')) {
            return;
        }

        $exists = DB::table('industries')->where('slug', 'pos')->exists();

        if ($exists) {
            echo "industries.slug 'pos' already present - insert skipped (collision-free check passed).\n";

            return;
        }

        DB::table('industries')->insert([
            'name' => 'POS',
            'slug' => 'pos',
            'description' => 'Point of Sale retail industry.',
            'status' => 'active',
            'sort_order' => 15,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        echo "Created industries row slug=pos.\n";
    }

    public function down(): void
    {
        if (! Schema::hasTable('industries')) {
            return;
        }

        DB::table('industries')
            ->where('slug', 'pos')
            ->where('name', 'POS')
            ->delete();
    }
};

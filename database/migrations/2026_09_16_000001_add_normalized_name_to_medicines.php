<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('medicines', function (Blueprint $table) {
            $table->string('normalized_name', 250)->nullable()->after('brand_name');
        });

        if (! Schema::hasIndex('medicines', 'idx_med_inst_norm')) {
            Schema::table('medicines', function (Blueprint $table) {
                $table->index(['institute_id', 'normalized_name'], 'idx_med_inst_norm');
            });
        }

        // Backfill existing rows
        DB::table('medicines')->orderBy('id')->chunk(500, function ($rows) {
            foreach ($rows as $row) {
                $normalized = preg_replace(
                    '/[^a-z0-9]/',
                    '',
                    strtolower(trim(($row->brand_name ?? '').' '.($row->strength ?? '')))
                );
                DB::table('medicines')->where('id', $row->id)->update([
                    'normalized_name' => $normalized,
                ]);
            }
        });

        // Add unique constraint (safe: 0 duplicate groups found in DB audit)
        if (! Schema::hasIndex('medicines', 'uniq_med_inst_norm')) {
            Schema::table('medicines', function (Blueprint $table) {
                $table->unique(['institute_id', 'normalized_name'], 'uniq_med_inst_norm');
            });
        }
    }

    public function down(): void
    {
        Schema::table('medicines', function (Blueprint $table) {
            $table->dropIndex('idx_med_inst_norm');
            $table->dropColumn('normalized_name');
        });
    }
};

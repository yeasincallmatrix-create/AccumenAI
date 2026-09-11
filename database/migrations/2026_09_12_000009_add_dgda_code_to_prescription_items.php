<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Snapshot the catalog DGDA code onto each prescribed item so the
     * save-time notice, print/PDF column and detail badges read the code
     * as prescribed (later catalog edits must not rewrite history).
     */
    public function up(): void
    {
        Schema::table('prescription_items', function (Blueprint $table) {
            $table->string('dgda_code', 100)->nullable()->after('medicine_name');
        });

        // Backfill from the linked catalog rows (point-in-time best effort
        // for prescriptions written before the snapshot existed).
        DB::table('prescription_items')
            ->join('medicines', 'medicines.id', '=', 'prescription_items.medicine_id')
            ->whereNull('prescription_items.dgda_code')
            ->whereNotNull('medicines.dgda_code')
            ->where('medicines.dgda_code', '!=', '')
            ->update(['prescription_items.dgda_code' => DB::raw('medicines.dgda_code')]);
    }

    public function down(): void
    {
        Schema::table('prescription_items', function (Blueprint $table) {
            $table->dropColumn('dgda_code');
        });
    }
};

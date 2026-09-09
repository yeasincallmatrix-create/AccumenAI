<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // DGDA registry linkage on the existing pharmacy medicine catalog.
        // Codes are registry-global (unique across institutes); all columns
        // start empty (status pending) — real DAR codes must come from the
        // DGDA/OCL registry via medical:dgda-sync, never invented locally.
        Schema::table('medicines', function (Blueprint $table) {
            $table->string('dgda_code', 60)->nullable()->unique()->after('id');
            $table->string('dgda_dar_number', 60)->nullable()->after('dgda_code');
            $table->string('dgda_concept_id', 160)->nullable()->after('dgda_dar_number');
            $table->timestamp('dgda_synced_at')->nullable()->after('dgda_concept_id');
            $table->string('dgda_status', 20)->default('pending')->after('dgda_synced_at');
        });
    }

    public function down(): void
    {
        Schema::table('medicines', function (Blueprint $table) {
            $table->dropColumn([
                'dgda_code', 'dgda_dar_number', 'dgda_concept_id',
                'dgda_synced_at', 'dgda_status',
            ]);
        });
    }
};

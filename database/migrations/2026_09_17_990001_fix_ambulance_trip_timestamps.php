<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * MySQL implicitly gave the first TIMESTAMP column
     * (ambulance_trips.requested_at) DEFAULT CURRENT_TIMESTAMP
     * ON UPDATE CURRENT_TIMESTAMP, so every trip update reset
     * requested_at to now() and broke duration calculations.
     * Strip both implicit attributes; the column stays required
     * at the application (validation) layer.
     */
    public function up(): void
    {
        if (! Schema::hasTable('ambulance_trips')) {
            return;
        }

        DB::statement('ALTER TABLE ambulance_trips MODIFY requested_at TIMESTAMP NULL DEFAULT NULL');
    }

    public function down(): void
    {
        if (! Schema::hasTable('ambulance_trips')) {
            return;
        }

        DB::statement('ALTER TABLE ambulance_trips MODIFY requested_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP');
    }
};

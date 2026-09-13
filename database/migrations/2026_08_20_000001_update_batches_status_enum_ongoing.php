<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Update batches.status ENUM to use 'ongoing' (Training Center) while
 * keeping legacy 'running' for backwards compatibility.
 * Training Center spec requires 'ongoing' not 'running'.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Normalize existing legacy 'running' rows before tightening ENUM
        DB::table('batches')->where('status', 'running')->update(['status' => 'ongoing']);
        DB::statement("ALTER TABLE batches MODIFY COLUMN status ENUM('upcoming','ongoing','completed','cancelled','archived') NOT NULL DEFAULT 'upcoming'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE batches MODIFY COLUMN status ENUM('upcoming','ongoing','running','completed','cancelled','archived') NOT NULL DEFAULT 'upcoming'");
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Adds the 'archived' option to batches.status so institutes (and admins)
 * can shelve finished or deprecated batches instead of cancelling them.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE batches MODIFY COLUMN status ENUM('upcoming','running','completed','cancelled','archived') NOT NULL DEFAULT 'upcoming'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE batches MODIFY COLUMN status ENUM('upcoming','running','completed','cancelled') NOT NULL DEFAULT 'upcoming'");
    }
};

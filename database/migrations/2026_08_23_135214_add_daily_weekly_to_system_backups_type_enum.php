<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE system_backups MODIFY COLUMN type ENUM('manual','pre_restore','pre_orphan_cleanup','scheduled','health_check','daily','weekly') NOT NULL DEFAULT 'manual'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE system_backups MODIFY COLUMN type ENUM('manual','pre_restore','pre_orphan_cleanup','scheduled','health_check') NOT NULL DEFAULT 'manual'");
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE `audit_logs` MODIFY COLUMN `action` VARCHAR(100) NOT NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE `audit_logs` MODIFY COLUMN `action` VARCHAR(40) NOT NULL');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE `payments` ADD UNIQUE KEY `uq_payments_institute_receipt` (`institute_id`, `receipt_number`)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE `payments` DROP INDEX `uq_payments_institute_receipt`');
    }
};

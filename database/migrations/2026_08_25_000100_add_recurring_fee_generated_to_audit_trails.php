<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE `accounting_audit_trails` MODIFY COLUMN `action` ENUM('create','update','delete','post','reverse','void','waive','lock','close','reopen','import','migrate','export','recurring_fee_generated') NOT NULL");
    }

    public function down(): void
    {
        DB::statement("UPDATE `accounting_audit_trails` SET `action` = 'import' WHERE `action` = 'recurring_fee_generated'");
        DB::statement("ALTER TABLE `accounting_audit_trails` MODIFY COLUMN `action` ENUM('create','update','delete','post','reverse','void','waive','lock','close','reopen','import','migrate','export') NOT NULL");
    }
};

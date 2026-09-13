<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE purchase_sequences MODIFY COLUMN document_type ENUM('invoice', 'quotation', 'order', 'return', 'receipt') NOT NULL DEFAULT 'invoice'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE purchase_sequences MODIFY COLUMN document_type ENUM('invoice', 'quotation', 'order', 'return') NOT NULL DEFAULT 'invoice'");
    }
};

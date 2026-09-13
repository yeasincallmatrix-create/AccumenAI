<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE sales_sequences MODIFY COLUMN document_type ENUM('quotation','sales_order','delivery','sales_return','credit_note') NOT NULL");
    }
    public function down(): void
    {
        DB::statement("ALTER TABLE sales_sequences MODIFY COLUMN document_type ENUM('quotation','sales_order','delivery') NOT NULL");
    }
};

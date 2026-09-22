<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Add a virtual 'category' column mapping type -> category label.
     * Fixes schema drift: tests insert 'category' but column was missing since 9/20.
     */
    public function up(): void
    {
        DB::statement("
            ALTER TABLE chart_of_accounts
            ADD COLUMN category VARCHAR(50)
            GENERATED ALWAYS AS (
                CASE
                    WHEN type IN ('asset','liability','equity') THEN 'balance_sheet'
                    WHEN type IN ('income','expense') THEN 'income_statement'
                    ELSE NULL
                END
            ) VIRTUAL
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE chart_of_accounts DROP COLUMN category");
    }
};

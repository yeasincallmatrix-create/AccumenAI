<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('institutes')) {
            return;
        }

        if (! Schema::hasColumn('institutes', 'country_code')) {
            Schema::table('institutes', function (Blueprint $table) {
                $table->char('country_code', 2)->nullable()->after('country');
                $table->index('country_code', 'institutes_country_code_index');
            });
        }

        // Backfill existing rows from countries.iso2 via country_id; default BD where unresolvable.
        if (Schema::hasTable('countries') && Schema::hasColumn('countries', 'iso2')) {
            DB::statement(
                'UPDATE institutes i
                 LEFT JOIN countries c ON c.id = i.country_id
                 SET i.country_code = COALESCE(UPPER(c.iso2), \'BD\')
                 WHERE i.country_code IS NULL'
            );
        } else {
            DB::statement('UPDATE institutes SET country_code = \'BD\' WHERE country_code IS NULL');
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('institutes') && Schema::hasColumn('institutes', 'country_code')) {
            Schema::table('institutes', function (Blueprint $table) {
                $table->dropIndex('institutes_country_code_index');
                $table->dropColumn('country_code');
            });
        }
    }
};

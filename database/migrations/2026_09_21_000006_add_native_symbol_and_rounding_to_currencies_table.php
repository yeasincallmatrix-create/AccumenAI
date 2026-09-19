<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('currencies', function (Blueprint $table) {
            if (! Schema::hasColumn('currencies', 'symbol_native')) {
                $table->string('symbol_native', 10)->nullable()->after('symbol');
            }
            if (! Schema::hasColumn('currencies', 'rounding')) {
                $table->tinyInteger('rounding')->default(0)->after('decimal_places');
            }
        });
    }

    public function down(): void
    {
        Schema::table('currencies', function (Blueprint $table) {
            $table->dropColumn(['symbol_native', 'rounding']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Step 36 — fix the existing (previously dead) reg_no_sequence source of
     * truth instead of inventing a second generator.
     *
     * The legacy table is a bare auto-increment id with no usable key. Adding a
     * per-combo (year + zip + trade) row lets the platform issue the 12-digit
     * YY + ZZZZ + TTT + SSS registration numbers where SSS is sequential per
     * Year + ZIP + Trade. The sequence is platform-global so the composed
     * number stays globally unique under uq_students_registration_number.
     */
    public function up(): void
    {
        Schema::table('reg_no_sequence', function (Blueprint $table) {
            $table->char('year_code', 2)->nullable()->after('id');
            $table->char('zip_code', 4)->nullable()->after('year_code');
            $table->char('trade_code', 3)->nullable()->after('zip_code');
            $table->unsignedInteger('last_sequence')->default(0)->after('trade_code');

            $table->unique(['year_code', 'zip_code', 'trade_code'], 'uq_reg_no_sequence_combo');
        });
    }

    public function down(): void
    {
        Schema::table('reg_no_sequence', function (Blueprint $table) {
            $table->dropUnique('uq_reg_no_sequence_combo');
            $table->dropColumn(['year_code', 'zip_code', 'trade_code', 'last_sequence']);
        });
    }
};

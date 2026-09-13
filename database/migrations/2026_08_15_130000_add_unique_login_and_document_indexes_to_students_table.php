<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Enforce per-institute uniqueness on the login and identity-document
     * columns: no two students of the same institute share an email, phone
     * number, NID, birth certificate number or passport number. The same
     * values may still exist in a different institute.
     */
    public function up(): void
    {
        // Normalise blank strings to NULL so "not provided" never collides
        // with the unique indexes (NULL values are allowed to repeat).
        DB::table('students')->update([
            'email' => DB::raw("NULLIF(email, '')"),
            'phone' => DB::raw("NULLIF(phone, '')"),
            'nid_number' => DB::raw("NULLIF(nid_number, '')"),
            'birth_cert_number' => DB::raw("NULLIF(birth_cert_number, '')"),
            'passport_number' => DB::raw("NULLIF(passport_number, '')"),
        ]);

        Schema::table('students', function (Blueprint $table) {
            $table->unique(['institute_id', 'email'], 'uq_students_inst_email');
            $table->unique(['institute_id', 'phone'], 'uq_students_inst_phone');
            $table->unique(['institute_id', 'nid_number'], 'uq_students_inst_nid');
            $table->unique(['institute_id', 'birth_cert_number'], 'uq_students_inst_birth_cert');
            $table->unique(['institute_id', 'passport_number'], 'uq_students_inst_passport');
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropUnique('uq_students_inst_email');
            $table->dropUnique('uq_students_inst_phone');
            $table->dropUnique('uq_students_inst_nid');
            $table->dropUnique('uq_students_inst_birth_cert');
            $table->dropUnique('uq_students_inst_passport');
        });
    }
};

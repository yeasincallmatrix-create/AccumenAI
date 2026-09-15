<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $indexes = DB::select("SHOW INDEX FROM prescriptions WHERE Key_name = 'uq_prescriptions_institute_number'");
        if (! empty($indexes)) {
            DB::statement('ALTER TABLE prescriptions DROP INDEX uq_prescriptions_institute_number');
        }

        $duplicates = DB::select(
            'SELECT institute_id, prescription_number, COUNT(*) AS c FROM prescriptions GROUP BY institute_id, prescription_number HAVING c > 1'
        );
        if (empty($duplicates)) {
            DB::statement('ALTER TABLE prescriptions ADD UNIQUE uq_prescriptions_institute_number (institute_id, prescription_number, version)');
        }
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE prescriptions DROP INDEX uq_prescriptions_institute_number');
        DB::statement('ALTER TABLE prescriptions ADD UNIQUE uq_prescriptions_institute_number (institute_id, prescription_number)');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $v0Rows = DB::table('prescriptions')->where('version', 0)->get();

        foreach ($v0Rows as $rx) {
            $maxVersion = DB::table('prescriptions')
                ->where('doctor_id', $rx->doctor_id)
                ->where('patient_id', $rx->patient_id)
                ->where('prescription_date', $rx->prescription_date)
                ->where('id', '!=', $rx->id)
                ->max('version') ?? 0;

            DB::table('prescriptions')
                ->where('id', $rx->id)
                ->update(['version' => $maxVersion + 1]);
        }
    }

    public function down(): void
    {
        // Cannot reverse — no-op
    }
};

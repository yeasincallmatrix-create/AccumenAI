<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $hasVersion = DB::select("SHOW COLUMNS FROM prescriptions LIKE 'version'");
        if (empty($hasVersion)) {
            DB::statement('ALTER TABLE prescriptions ADD COLUMN version INT UNSIGNED NOT NULL DEFAULT 1 AFTER id');
            DB::statement('ALTER TABLE prescriptions ADD COLUMN parent_prescription_id BIGINT UNSIGNED NULL AFTER version');
            DB::statement('ALTER TABLE prescriptions ADD COLUMN amendment_reason TEXT NULL AFTER parent_prescription_id');
            DB::statement('ALTER TABLE prescriptions ADD COLUMN amended_by BIGINT UNSIGNED NULL AFTER amendment_reason');
            DB::statement('ALTER TABLE prescriptions ADD COLUMN amended_at TIMESTAMP NULL AFTER amended_by');
        }

        $indexes = DB::select("SHOW INDEX FROM prescriptions WHERE Key_name = 'idx_rx_doctor_patient_date_version'");
        if (empty($indexes)) {
            DB::statement('ALTER TABLE prescriptions ADD INDEX idx_rx_doctor_patient_date_version (doctor_id, patient_id, prescription_date, version)');
        }

        $fks = DB::select("SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'prescriptions' AND COLUMN_NAME = 'parent_prescription_id' AND REFERENCED_TABLE_NAME = 'prescriptions'");
        if (empty($fks)) {
            DB::statement('ALTER TABLE prescriptions ADD CONSTRAINT fk_prescription_parent FOREIGN KEY (parent_prescription_id) REFERENCES prescriptions(id) ON DELETE RESTRICT');
        }
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE prescriptions DROP INDEX idx_rx_doctor_patient_date_version');
        DB::statement('ALTER TABLE prescriptions DROP FOREIGN KEY fk_prescription_parent');
        DB::statement('ALTER TABLE prescriptions DROP COLUMN version, DROP COLUMN parent_prescription_id, DROP COLUMN amendment_reason, DROP COLUMN amended_by, DROP COLUMN amended_at');
    }
};

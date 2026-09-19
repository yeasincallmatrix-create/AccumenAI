<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * B80: add stored full_name to the three person-name tables that miss it.
 *
 * Root cause: NormalizesPersonNames::saving() syncs full_name from
 * first_name/last_name whenever isFillable('full_name') is true — which it
 * is for every $guarded = [] model (CrmLead, CrmContact, HrEmployee).
 * Without the column every save with a dirty first/last name fails with
 * SQLSTATE[42S22] Unknown column 'full_name' (~179 test errors, spread
 * over hr_employees / crm_contacts / crm_leads).
 *
 * A stored column (not an accessor) is required: app services query
 * where('full_name', 'like') and orderBy('full_name'), and the saving
 * hook writes the attribute on every save. Convention matches the
 * existing students / institute_users / platform_admins tables.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['crm_leads', 'crm_contacts', 'hr_employees'] as $table) {
            if (Schema::hasTable($table) && ! Schema::hasColumn($table, 'full_name')) {
                Schema::table($table, function (Blueprint $table) {
                    $table->string('full_name', 255)->nullable()->after('id');
                });
            }
        }
    }

    public function down(): void
    {
        foreach (['crm_leads', 'crm_contacts', 'hr_employees'] as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'full_name')) {
                Schema::table($table, function (Blueprint $table) {
                    $table->dropColumn('full_name');
                });
            }
        }
    }
};

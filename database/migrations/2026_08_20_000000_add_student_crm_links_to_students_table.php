<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Step 34 — Education ↔ AccumenAI Core: link a student to its CRM lead
 * (captured at admission) and CRM contact (established when the admission
 * lead is converted at enrollment).
 *
 * Both links are nullable and soft (FK SET NULL on CRM delete) so existing
 * education workflows and historical students are never touched.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->foreignId('crm_contact_id')
                ->nullable()
                ->after('status')
                ->constrained('crm_contacts')
                ->nullOnDelete();

            $table->foreignId('crm_lead_id')
                ->nullable()
                ->after('crm_contact_id')
                ->constrained('crm_leads')
                ->nullOnDelete();

            $table->index('crm_contact_id', 'students_crm_contact_idx');
            $table->index('crm_lead_id', 'students_crm_lead_idx');
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropIndex('students_crm_contact_idx');
            $table->dropIndex('students_crm_lead_idx');
            $table->dropForeign(['crm_contact_id']);
            $table->dropForeign(['crm_lead_id']);
            $table->dropColumn(['crm_contact_id', 'crm_lead_id']);
        });
    }
};

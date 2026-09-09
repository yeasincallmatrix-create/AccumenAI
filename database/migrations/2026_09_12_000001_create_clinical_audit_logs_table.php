<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 01 — Clinical record integrity.
 *
 * Generalized append-only audit for clinical amendments. Complements (does
 * NOT replace) prescription_audit_logs and queue_audit_logs, which keep
 * recording lifecycle events in their existing shape.
 *
 * WHO:    user_id + user_type + actor_name (denormalized so the trail
 *         survives user deletion).
 * WHAT:   action + auditable_type/id + old_values/new_values (changed
 *         attributes only for updates; full snapshot for deletes).
 * WHEN:   created_at (rows are never updated or deleted by the app).
 * TENANT: institute_id (FK, cascades with the tenant like all HMS tables).
 * WHY:    reason (required only on destructive paths, e.g. admission
 *         delete; nullable elsewhere).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clinical_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institute_id')->constrained('institutes')->cascadeOnDelete();
            $table->foreignId('patient_id')->nullable()->constrained('patients')->nullOnDelete();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('user_type', 30)->nullable();
            $table->string('actor_name', 150)->nullable();
            $table->string('auditable_type', 120);
            $table->unsignedBigInteger('auditable_id');
            $table->string('action', 40);
            $table->string('reason', 255)->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            $table->index(['institute_id', 'auditable_type', 'auditable_id'], 'idx_clinical_audit_subject');
            $table->index('patient_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clinical_audit_logs');
    }
};

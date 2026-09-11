<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 13 — Validated clinical decision support foundation.
 *
 * - cds_rules: rule identity (key unique). Global vocabulary, carries NO
 *   patient data and NO clinical logic beyond metadata — evaluation reads
 *   immutable versions.
 * - cds_rule_versions: each material rule change is a NEW row (history is
 *   never edited in place). Findings forever reference the exact version
 *   that produced them.
 * - cds_findings: tenant-scoped clinical findings. Prescription link is
 *   NULL ON DELETE (draft removal keeps an attributable finding against
 *   the patient); rule-version link is RESTRICT (versions are immortal).
 *   Severity/status are controlled strings enforced in code (cross-version
 *   MySQL CHECK support is unreliable on the project's matrix).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cds_rules', function (Blueprint $table) {
            $table->id();
            // Stable identity, e.g. CDS-DUP-INGREDIENT-001.
            $table->string('rule_key', 80)->unique();
            // ALLERGY|INTERACTION|DUPLICATE_THERAPY|CONTRAINDICATION|
            // DOSE_CHECK|DURATION_CHECK|PATIENT_FACTOR|TERMINOLOGY|OTHER
            $table->string('rule_type', 30);
            // INFO|LOW|MODERATE|HIGH|CRITICAL
            $table->string('severity', 10);
            // block|warn — explicit per rule, never derived from severity.
            $table->string('block_policy', 10)->default('warn');
            // draft|validated|active|suspended|retired
            $table->string('status', 20)->default('draft');
            $table->string('source', 120);
            $table->string('source_version', 60)->nullable();
            $table->string('validated_by', 120)->nullable();
            $table->timestamp('validated_at')->nullable();
            $table->unsignedInteger('current_version')->default(1);
            $table->timestamp('effective_from')->nullable();
            $table->timestamp('effective_until')->nullable();
            $table->timestamps();

            $table->index(['status', 'rule_type']);
        });

        Schema::create('cds_rule_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cds_rule_id')->constrained('cds_rules')->restrictOnDelete();
            $table->unsignedInteger('version');
            // Interpreter input, e.g. {"mode":"shared_ingredient"} or
            // {"pairs":[["100","200"]]}. Never executable code.
            $table->json('definition');
            $table->string('status', 20)->default('draft');
            $table->timestamp('effective_from')->nullable();
            $table->timestamp('effective_until')->nullable();
            $table->string('validated_by', 120)->nullable();
            $table->timestamp('validated_at')->nullable();
            $table->timestamps();

            $table->unique(['cds_rule_id', 'version'], 'uq_cds_rule_version');
            $table->index('status');
        });

        Schema::create('cds_findings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institute_id')->constrained('institutes')->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->foreignId('prescription_id')->nullable()->constrained('prescriptions')->nullOnDelete();
            $table->foreignId('cds_rule_version_id')->constrained('cds_rule_versions')->restrictOnDelete();
            $table->string('severity', 10);
            // open|acknowledged|overridden|resolved
            $table->string('status', 20)->default('open');
            $table->text('message');
            $table->json('explanation')->nullable();
            $table->json('trigger_data')->nullable();
            $table->timestamp('evaluated_at')->nullable();
            // Actor snapshot (mixed guards) — no FK by design.
            $table->unsignedBigInteger('resolved_by')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->text('resolution_reason')->nullable();
            $table->timestamps();

            $table->index(['institute_id', 'patient_id']);
            $table->index(['prescription_id', 'status']);
            $table->index('cds_rule_version_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cds_findings');
        Schema::dropIfExists('cds_rule_versions');
        Schema::dropIfExists('cds_rules');
    }
};

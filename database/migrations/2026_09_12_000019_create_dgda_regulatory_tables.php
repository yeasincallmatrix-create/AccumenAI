<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 11 — DGDA regulatory records & import batches.
 *
 * A DGDA registration (marketing authorization: DAR number + brand +
 * manufacturer + validity) is a REGULATORY record, not a clinical product.
 * It links to a global medicine_product when determinable, never to tenant
 * catalogs, prices, stock or prescriptions. No destructive cascades:
 * registrations are never deleted by product work (restrict), batches are
 * append-only operational history.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dgda_import_batches', function (Blueprint $table) {
            $table->id();
            $table->string('source', 40)->default('dgda_official_export');
            $table->string('filename', 200)->nullable();
            $table->string('checksum', 64)->nullable();
            $table->string('status', 20)->default('pending'); // pending|partial|complete|failed
            $table->unsignedInteger('records_seen')->default(0);
            $table->unsignedInteger('records_valid')->default(0);
            $table->unsignedInteger('records_created')->default(0);
            $table->unsignedInteger('records_updated')->default(0);
            $table->unsignedInteger('records_unchanged')->default(0);
            $table->unsignedInteger('records_rejected')->default(0);
            $table->unsignedInteger('records_unmatched')->default(0);
            $table->unsignedInteger('records_ambiguous')->default(0);
            $table->text('error_summary')->nullable();
            $table->string('source_version', 100)->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('dgda_registrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dgda_import_batch_id')->nullable()
                ->constrained('dgda_import_batches')->nullOnDelete();
            // Stable source identity: DAR numbers are registry-issued.
            $table->string('dar_number', 60)->unique();
            $table->string('brand_name', 190)->nullable();
            $table->string('generic_name', 190)->nullable();
            $table->string('strength_raw', 100)->nullable();
            $table->string('dosage_form_raw', 60)->nullable();
            $table->string('manufacturer_name', 190)->nullable();
            $table->string('importer_name', 190)->nullable();
            // Verbatim source status — never reduced to true/false here.
            $table->string('status_raw', 60)->nullable();
            $table->date('valid_upto')->nullable();
            // Normalized product link (nullable until deterministically matched).
            $table->foreignId('medicine_product_id')->nullable()
                ->constrained('medicine_products')->nullOnDelete();
            $table->string('match_status', 20)->default('unmatched');
            // unmatched|exact_identifier|deterministic|ambiguous|invalid
            $table->text('match_detail')->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamp('retrieved_at')->nullable();
            $table->timestamps();

            $table->index('medicine_product_id');
            $table->index('match_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dgda_registrations');
        Schema::dropIfExists('dgda_import_batches');
    }
};

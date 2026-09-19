<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('lab_messages')) {
            return;
        }

        Schema::create('lab_messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('institute_id');
            $table->unsignedBigInteger('analyzer_id')->nullable();

            // Direction: inbound (analyzer → LIS), outbound (LIS → analyzer)
            $table->string('direction', 20)->default('inbound');

            // Protocol + adapter
            $table->string('protocol', 30);
            $table->string('adapter_key', 100)->nullable();
            $table->string('adapter_version', 20)->nullable();

            // Idempotency
            $table->string('message_id', 100)->nullable(); // HL7 MSH-10 or similar
            $table->string('idempotency_hash', 64); // SHA-256 of raw payload

            // Raw content
            $table->mediumText('raw_payload');
            $table->json('parsed_json')->nullable();

            // Status: received, parsed, matched, stored, error, dead, duplicate
            $table->string('status', 20)->default('received');
            $table->string('error_code', 50)->nullable();
            $table->text('error_message')->nullable();
            $table->integer('attempts')->default(0);
            $table->timestamp('last_attempted_at')->nullable();

            // Matching
            $table->string('accession_number', 100)->nullable();
            $table->unsignedBigInteger('sample_id')->nullable();
            $table->unsignedBigInteger('lab_order_id')->nullable();

            // Source
            $table->string('source_ip', 45)->nullable();
            $table->string('source_host', 100)->nullable();

            // Timing (received_at defaults to now; strict-mode MySQL rejects
            // a bare NOT NULL timestamp without default)
            $table->timestamp('received_at')->useCurrent();
            $table->timestamp('processed_at')->nullable();

            $table->timestamps();

            $table->foreign('institute_id')->references('id')->on('institutes')->onDelete('cascade');
            $table->foreign('analyzer_id')->references('id')->on('lab_analyzers')->onDelete('set null');
            $table->foreign('sample_id')->references('id')->on('lab_samples')->onDelete('set null');
            $table->foreign('lab_order_id')->references('id')->on('lab_orders')->onDelete('set null');

            $table->unique(['institute_id', 'idempotency_hash'], 'uniq_message_idempotency');
            $table->index(['institute_id', 'status', 'created_at']);
            $table->index(['analyzer_id', 'received_at']);
            $table->index('accession_number');
            $table->index('message_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lab_messages');
    }
};

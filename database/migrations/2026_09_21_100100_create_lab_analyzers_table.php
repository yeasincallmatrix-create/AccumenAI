<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('lab_analyzers')) {
            return;
        }

        Schema::create('lab_analyzers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('institute_id');
            $table->unsignedBigInteger('branch_id')->nullable();

            $table->string('code', 50);
            $table->string('name', 200);
            $table->string('manufacturer', 200)->nullable();
            $table->string('model', 100)->nullable();
            $table->string('serial_no', 100)->nullable();
            $table->string('instrument_type', 50); // hematology, biochemistry, immunoassay, urinalysis, coagulation, blood_gas, electrolyte, other
            $table->string('protocol', 30); // astm, hl7, vendor, file, csv
            $table->string('adapter_key', 100); // e.g., sysmex_xn, mindray_bc
            $table->string('adapter_version', 20)->default('v1');
            $table->string('connection_type', 30); // serial, tcp, usb, file

            // Connection details
            $table->string('host', 100)->nullable();
            $table->integer('port')->nullable();
            $table->string('serial_port', 50)->nullable();
            $table->integer('baud_rate')->nullable();
            $table->string('parity', 10)->nullable();
            $table->integer('stop_bits')->nullable();
            $table->integer('data_bits')->nullable();

            // Capabilities (JSON): {"result_upload": true, "worklist": false, "query": false, "bidirectional": false}
            $table->json('capabilities')->nullable();

            // Status
            $table->boolean('is_enabled')->default(false);
            $table->string('status', 20)->default('inactive'); // active, inactive, maintenance, error
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamp('last_error_at')->nullable();
            $table->text('last_error_message')->nullable();

            $table->text('notes')->nullable();
            $table->json('config')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('institute_id')->references('id')->on('institutes')->onDelete('cascade');
            $table->foreign('branch_id')->references('id')->on('branches')->onDelete('set null');

            $table->unique(['institute_id', 'code'], 'uniq_lab_analyzers_code');
            $table->unique(['institute_id', 'serial_no'], 'uniq_lab_analyzers_serial');
            $table->index(['institute_id', 'is_enabled']);
            $table->index(['institute_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lab_analyzers');
    }
};

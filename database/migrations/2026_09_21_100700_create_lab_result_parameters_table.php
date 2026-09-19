<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('lab_result_parameters')) {
            return;
        }

        Schema::create('lab_result_parameters', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('institute_id');
            $table->unsignedBigInteger('lab_result_id');

            // Universal parameter code: WBC, RBC, HGB, HCT, MCV, MCH, MCHC, RDW, PLT, MPV,
            // NEU_PCT, LYM_PCT, MON_PCT, EOS_PCT, BASO_PCT, NEU_ABS, etc.
            $table->string('parameter_key', 50);
            $table->string('parameter_name', 200)->nullable();

            // Value (either decimal or text)
            $table->decimal('value_decimal', 15, 4)->nullable();
            $table->string('value_text', 100)->nullable();

            $table->string('unit', 30)->nullable();

            // Flag: N (normal), H (high), L (low), HH (critical high), LL (critical low), * (abnormal)
            $table->string('flag', 10)->nullable();

            // Reference range
            $table->decimal('ref_low', 15, 4)->nullable();
            $table->decimal('ref_high', 15, 4)->nullable();
            $table->string('ref_range_text', 100)->nullable();

            // Status: pending, verified, rejected
            $table->string('status', 20)->default('pending');

            // Source (analyzer vs manual)
            $table->unsignedBigInteger('analyzer_id')->nullable();
            $table->unsignedBigInteger('lab_message_id')->nullable();

            $table->timestamps();

            $table->foreign('institute_id')->references('id')->on('institutes')->onDelete('cascade');
            $table->foreign('lab_result_id')->references('id')->on('lab_results')->onDelete('cascade');
            $table->foreign('analyzer_id')->references('id')->on('lab_analyzers')->onDelete('set null');
            $table->foreign('lab_message_id')->references('id')->on('lab_messages')->onDelete('set null');

            $table->unique(['lab_result_id', 'parameter_key'], 'uniq_result_parameter');
            $table->index(['institute_id', 'parameter_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lab_result_parameters');
    }
};

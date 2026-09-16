<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dgda_sync_batches', function (Blueprint $table) {
            $table->id();
            $table->string('batch_type', 30);
            $table->string('source_file', 255)->nullable();
            $table->integer('total_rows')->default(0);
            $table->integer('imported')->default(0);
            $table->integer('updated')->default(0);
            $table->integer('skipped')->default(0);
            $table->integer('failed')->default(0);
            $table->string('status', 20)->default('running');
            $table->text('error_log')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->unsignedBigInteger('started_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dgda_sync_batches');
    }
};

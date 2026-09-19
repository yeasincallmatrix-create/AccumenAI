<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('lab_worklists')) {
            return;
        }

        Schema::create('lab_worklists', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('institute_id');
            $table->unsignedBigInteger('analyzer_id');
            $table->unsignedBigInteger('sample_id')->nullable();
            $table->unsignedBigInteger('lab_order_id')->nullable();

            // Snapshot of order details sent to analyzer
            $table->json('order_snapshot');
            $table->json('tests_snapshot')->nullable();

            // Status: pending, sent, acked, expired, failed
            $table->string('status', 20)->default('pending');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('acked_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->text('error_message')->nullable();

            $table->timestamps();

            $table->foreign('institute_id')->references('id')->on('institutes')->onDelete('cascade');
            $table->foreign('analyzer_id')->references('id')->on('lab_analyzers')->onDelete('cascade');
            $table->foreign('sample_id')->references('id')->on('lab_samples')->onDelete('set null');
            $table->foreign('lab_order_id')->references('id')->on('lab_orders')->onDelete('set null');

            $table->index(['institute_id', 'status']);
            $table->index(['analyzer_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lab_worklists');
    }
};

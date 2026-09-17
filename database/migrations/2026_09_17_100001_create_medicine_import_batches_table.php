<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medicine_import_batches', function (Blueprint $table) {
            $table->id();
            $table->uuid('batch_id')->unique();
            $table->unsignedBigInteger('institute_id');
            $table->unsignedBigInteger('uploaded_by');
            $table->string('original_filename', 255);
            $table->integer('total_rows')->default(0);
            $table->integer('clean_rows')->default(0);
            $table->integer('conflict_rows')->default(0);
            $table->integer('error_rows')->default(0);
            $table->json('parsed_data')->nullable();
            $table->json('conflicts')->nullable();
            $table->string('status', 20)->default('pending_review');
            $table->timestamp('confirmed_at')->nullable();
            $table->integer('imported_count')->default(0);
            $table->integer('skipped_count')->default(0);
            $table->timestamps();

            $table->foreign('institute_id')->references('id')->on('institutes')->onDelete('cascade');
            $table->foreign('uploaded_by')->references('id')->on('users')->onDelete('cascade');
            $table->index(['institute_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medicine_import_batches');
    }
};

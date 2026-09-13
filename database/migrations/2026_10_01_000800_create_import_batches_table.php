<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_batches', function (Blueprint $table) {
            $table->id();
            $table->string('module', 50);
            $table->string('file_name', 255);
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('success_rows')->default(0);
            $table->unsignedInteger('failed_rows')->default(0);
            $table->string('rollback_token', 64)->nullable()->unique();
            $table->enum('status', ['pending', 'processing', 'completed', 'failed', 'rolled_back'])->default('pending');
            $table->json('errors')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index(['module', 'status']);
            $table->index('created_at');
        });

        Schema::create('import_batch_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_batch_id')->constrained('import_batches')->cascadeOnDelete();
            $table->unsignedInteger('row_number');
            $table->json('data');
            $table->enum('status', ['pending', 'success', 'failed'])->default('pending');
            $table->text('error')->nullable();
            $table->timestamps();

            $table->index(['import_batch_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_batch_rows');
        Schema::dropIfExists('import_batches');
    }
};

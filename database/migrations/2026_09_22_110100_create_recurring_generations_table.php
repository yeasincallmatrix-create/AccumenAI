<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('recurring_generations')) {
            return;
        }

        Schema::create('recurring_generations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('institute_id');
            $table->unsignedBigInteger('template_id');
            $table->date('scheduled_for');
            $table->timestamp('generated_at')->nullable();
            $table->string('generated_type', 100)->nullable();
            $table->unsignedBigInteger('generated_id')->nullable();
            $table->string('status', 20);
            // success, failed, skipped
            $table->text('error_message')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('institute_id')->references('id')->on('institutes')->onDelete('cascade');
            $table->foreign('template_id')->references('id')->on('recurring_templates')->onDelete('cascade');
            $table->index(['template_id', 'scheduled_for']);
            $table->index(['institute_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recurring_generations');
    }
};

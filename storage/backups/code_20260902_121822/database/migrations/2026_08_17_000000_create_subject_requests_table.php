<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subject_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('institute_id')->index();
            $table->unsignedBigInteger('category_id')->nullable()->index();
            $table->string('subject_type', 50)->default('professional');
            $table->string('name');
            $table->string('short_name', 100)->nullable();
            $table->string('subject_code', 50)->nullable();
            $table->text('description')->nullable();
            $table->unsignedBigInteger('requested_by')->nullable();
            $table->string('status', 20)->default('pending')->index();
            $table->text('review_note')->nullable();
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['institute_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subject_requests');
    }
};

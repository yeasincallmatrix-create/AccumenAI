<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('enrollments')) {
            Schema::create('enrollments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('institute_id')->constrained()->cascadeOnDelete();
                $table->foreignId('batch_id')->constrained()->cascadeOnDelete();
                $table->foreignId('trainee_id')->constrained('users')->cascadeOnDelete();
                $table->date('enrollment_date')->nullable();
                $table->enum('status', ['active', 'completed', 'dropped'])->default('active');
                $table->enum('payment_status', ['pending', 'paid', 'partial'])->default('pending');
                $table->timestamps();
                $table->softDeletes();
                $table->unique(['batch_id', 'trainee_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('enrollments');
    }
};

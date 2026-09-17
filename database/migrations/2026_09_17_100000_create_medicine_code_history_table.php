<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medicine_code_history', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('institute_id');
            $table->unsignedBigInteger('medicine_id');
            $table->string('old_code', 50)->nullable();
            $table->string('new_code', 50);
            $table->string('reason', 100)->default('sequential_migration');
            $table->timestamp('migrated_at');
            $table->unsignedBigInteger('migrated_by')->nullable();
            $table->timestamps();

            $table->foreign('institute_id')->references('id')->on('institutes')->onDelete('cascade');
            $table->foreign('medicine_id')->references('id')->on('medicines')->onDelete('cascade');
            $table->index(['institute_id', 'medicine_id']);
            $table->index('old_code');
            $table->index('new_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medicine_code_history');
    }
};

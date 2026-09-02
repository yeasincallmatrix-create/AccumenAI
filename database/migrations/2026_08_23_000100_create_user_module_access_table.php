<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('user_module_access')) {
            return;
        }

        Schema::create('user_module_access', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institute_id')->constrained('institutes')->cascadeOnDelete();
            $table->string('user_type', 30); // institute_user or user
            $table->unsignedBigInteger('user_id');
            $table->string('module_key', 60);
            $table->boolean('enabled')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->string('reason', 255)->nullable();
            $table->timestamps();

            $table->unique(['institute_id', 'user_type', 'user_id', 'module_key'], 'uq_user_module_access');
            $table->index(['institute_id', 'module_key']);
            $table->index(['user_type', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_module_access');
    }
};

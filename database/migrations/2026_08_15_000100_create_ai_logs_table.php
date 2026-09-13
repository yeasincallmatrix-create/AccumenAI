<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('institute_id')->nullable()->index();
            $table->string('user_type')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('feature')->default('assistant');
            $table->text('prompt')->nullable();
            $table->json('tools')->nullable();
            $table->string('status')->default('ok');
            $table->unsignedInteger('tokens')->default(0);
            $table->text('error')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['institute_id', 'created_at']);
            $table->index(['user_type', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_logs');
    }
};

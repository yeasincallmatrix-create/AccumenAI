<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_api_keys', function (Blueprint $table) {
            $table->id();
            $table->string('provider'); // openai, anthropic, gemini, groq, custom
            $table->string('name')->nullable();
            $table->text('api_key')->nullable();
            $table->string('base_url')->nullable();
            $table->string('model')->nullable();
            $table->boolean('is_active')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_api_keys');
    }
};

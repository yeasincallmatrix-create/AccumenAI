<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('home_pages', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('slug', 60)->unique();
            $table->text('description')->nullable();
            $table->string('hero_title', 255);
            $table->text('hero_subtitle')->nullable();
            $table->string('hero_badge', 255)->nullable();
            $table->string('hero_cta_text', 100)->default('Get Started Free');
            $table->string('hero_cta_url', 500)->nullable();
            $table->string('hero_image_url', 500)->nullable();
            $table->json('sections_json')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_global')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('home_pages');
    }
};

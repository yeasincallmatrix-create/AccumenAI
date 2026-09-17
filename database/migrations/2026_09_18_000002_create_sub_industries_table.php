<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sub_industries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('industry_id');
            $table->unsignedBigInteger('country_id')->nullable();
            $table->string('name', 100);
            $table->string('slug', 60);
            $table->string('code', 30)->nullable();
            $table->string('description', 255)->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->tinyInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('industry_id');
            $table->index('country_id');

            // Scope hash for NULL-safe uniqueness: (industry_id, country_id, slug)
            $table->string('scope_hash', 120)->storedAs(
                "CONCAT(COALESCE(CAST(industry_id AS CHAR), 'G'), '-', COALESCE(CAST(country_id AS CHAR), 'G'), '-', slug)"
            );
            $table->unique('scope_hash', 'uq_sub_industry_scope_slug');

            $table->foreign('industry_id')->references('id')->on('industries')->onDelete('cascade');
            $table->foreign('country_id')->references('id')->on('countries')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sub_industries');
    }
};

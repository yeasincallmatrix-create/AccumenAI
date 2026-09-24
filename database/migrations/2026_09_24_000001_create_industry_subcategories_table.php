<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('industry_subcategories')) {
            Schema::create('industry_subcategories', function (Blueprint $table) {
                $table->id();
                $table->string('industry_key', 60);
                $table->string('subcategory_key', 60);
                $table->string('name', 100);
                $table->text('description')->nullable();
                $table->string('icon', 60)->nullable();
                $table->integer('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->unique(['industry_key', 'subcategory_key'], 'industry_subcategories_industry_key_subcategory_key_unique');
                $table->index('industry_key', 'industry_subcategories_industry_key_index');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('industry_subcategories');
    }
};

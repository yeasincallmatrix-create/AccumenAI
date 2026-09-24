<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('subcategory_default_modules')) {
            Schema::create('subcategory_default_modules', function (Blueprint $table) {
                $table->id();
                $table->foreignId('subcategory_id')
                    ->constrained('industry_subcategories')
                    ->cascadeOnDelete();
                $table->string('module_key', 60);
                $table->enum('category', ['mandatory', 'default', 'optional'])->default('default');
                $table->timestamps();

                $table->unique(['subcategory_id', 'module_key'], 'subcategory_default_modules_subcategory_id_module_key_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('subcategory_default_modules');
    }
};

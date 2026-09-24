<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('module_terminology')) {
            Schema::create('module_terminology', function (Blueprint $table) {
                $table->id();
                $table->char('country_code', 2)->nullable();
                $table->string('term_key', 100);
                $table->string('term_value', 255);
                $table->string('context', 60)->nullable();
                $table->json('metadata')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->unique(['country_code', 'term_key'], 'module_terminology_country_code_term_key_unique');
                $table->index('term_key', 'module_terminology_term_key_index');
                $table->index('country_code', 'module_terminology_country_code_index');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('module_terminology');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('country_tax_modules')) {
            Schema::create('country_tax_modules', function (Blueprint $table) {
                $table->id();
                $table->char('country_code', 2);
                $table->string('tax_module', 60);
                $table->string('tax_name', 100);
                $table->decimal('default_rate', 5, 2)->default(0);
                $table->boolean('is_active')->default(true);
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->unique(['country_code', 'tax_module'], 'country_tax_modules_country_code_tax_module_unique');
                $table->index('country_code', 'country_tax_modules_country_code_index');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('country_tax_modules');
    }
};

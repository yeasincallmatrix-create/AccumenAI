<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('module_rules')) {
            Schema::create('module_rules', function (Blueprint $table) {
                $table->id();
                $table->char('country_code', 2)->nullable();
                $table->string('module_key', 60);
                $table->string('rule_key', 100);
                $table->json('rule_value');
                $table->text('description')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->unique(['country_code', 'module_key', 'rule_key'], 'module_rules_country_code_module_key_rule_key_unique');
                $table->index('module_key', 'module_rules_module_key_index');
                $table->index('country_code', 'module_rules_country_code_index');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('module_rules');
    }
};

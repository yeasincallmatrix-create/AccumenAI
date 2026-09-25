<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('package_industry_modules')) {
            return;
        }

        Schema::create('package_industry_modules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('package_id');
            $table->string('industry_key', 60);
            $table->string('module_key', 60);
            $table->boolean('enabled')->default(true);
            $table->timestamps();

            $table->foreign('package_id')
                ->references('id')
                ->on('subscription_packages')
                ->onDelete('cascade');

            $table->unique(
                ['package_id', 'industry_key', 'module_key'],
                'uq_package_industry_module'
            );
            $table->index(['industry_key', 'package_id'], 'idx_industry_package');
            $table->index('module_key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('package_industry_modules');
    }
};

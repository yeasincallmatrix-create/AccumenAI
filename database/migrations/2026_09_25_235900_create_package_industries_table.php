<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('package_industries')) {
            return;
        }

        Schema::create('package_industries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('package_id');
            $table->string('industry_key', 60);
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->decimal('price_monthly', 10, 2)->nullable();
            $table->decimal('price_yearly', 10, 2)->nullable();
            $table->string('currency', 3)->nullable();
            $table->timestamps();

            $table->foreign('package_id')
                ->references('id')
                ->on('subscription_packages')
                ->onDelete('cascade');

            $table->unique(['package_id', 'industry_key'], 'uq_package_industry');
            $table->index('industry_key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('package_industries');
    }
};

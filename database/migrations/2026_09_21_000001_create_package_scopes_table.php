<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('package_scopes')) {
            return;
        }

        Schema::create('package_scopes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('package_id')
                ->constrained('subscription_packages')
                ->cascadeOnDelete();
            $table->unsignedBigInteger('country_id')->nullable();
            $table->unsignedBigInteger('industry_id')->nullable();
            $table->unsignedBigInteger('sub_industry_id')->nullable();
            $table->boolean('inherit_from_parent')->default(true);
            $table->decimal('price_monthly', 10, 2)->nullable();
            $table->decimal('price_yearly', 10, 2)->nullable();
            $table->string('currency', 3)->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();

            $table->unique(
                ['package_id', 'country_id', 'industry_id', 'sub_industry_id'],
                'uq_package_scope'
            );
            $table->index(['country_id', 'industry_id', 'sub_industry_id'], 'idx_scope_lookup');

            $table->foreign('country_id')
                ->references('id')->on('countries')->nullOnDelete();
            $table->foreign('industry_id')
                ->references('id')->on('industries')->nullOnDelete();
            $table->foreign('sub_industry_id')
                ->references('id')->on('sub_industries')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('package_scopes');
    }
};

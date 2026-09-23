<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('parties')) {
            return;
        }

        Schema::create('parties', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('institute_id');
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->enum('type', ['customer', 'supplier', 'both'])->default('customer');
            $table->unsignedBigInteger('customer_group_id')->nullable();
            $table->string('name', 150);
            $table->string('phone', 30)->nullable();
            $table->string('email', 150)->nullable();
            $table->text('address')->nullable();
            $table->string('tin', 50)->nullable();
            $table->unsignedBigInteger('billing_currency_id')->nullable();
            $table->decimal('credit_limit', 19, 4)->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('party_meta')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['institute_id', 'branch_id', 'type', 'phone'], 'uq_parties_phone');
            $table->index(['institute_id', 'branch_id', 'type'], 'idx_parties_scope');
            $table->index('customer_group_id', 'idx_parties_group');
            $table->index('branch_id', 'parties_branch_id_foreign');
            $table->index('billing_currency_id', 'parties_billing_currency_id_foreign');

            $table->foreign('billing_currency_id')->references('id')->on('currencies')->nullOnDelete();
            $table->foreign('branch_id')->references('id')->on('branches')->nullOnDelete();
            $table->foreign('customer_group_id')->references('id')->on('customer_groups')->nullOnDelete();
            $table->foreign('institute_id')->references('id')->on('institutes')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parties');
    }
};

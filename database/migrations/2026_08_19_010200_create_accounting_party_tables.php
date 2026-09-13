<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Global Accounting Engine — Step 1: parties (unified customer/supplier).
 *
 * AR/AP runs in derive mode: balances are computed from journal lines, so no
 * separate receivable/payable balance tables exist. A party is customer,
 * supplier, or both. Unique phone per (institute, branch, type) guards against
 * duplicate entries; validation of ownership happens in the service layer.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('parties')) {
            Schema::create('parties', function (Blueprint $table) {
                $table->id();
                $table->foreignId('institute_id')->constrained('institutes')->cascadeOnDelete();
                $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
                $table->enum('type', ['customer', 'supplier', 'both'])->default('customer');
                $table->foreignId('customer_group_id')->nullable()->constrained('customer_groups')->nullOnDelete();
                $table->string('name', 150);
                $table->string('phone', 30)->nullable();
                $table->string('email', 150)->nullable();
                $table->text('address')->nullable();
                $table->string('tin', 50)->nullable();
                $table->foreignId('billing_currency_id')->nullable()->constrained('currencies')->nullOnDelete();
                $table->decimal('credit_limit', 19, 4)->nullable();
                $table->boolean('is_active')->default(true);
                $table->json('party_meta')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->unique(['institute_id', 'branch_id', 'type', 'phone'], 'uq_parties_phone');
                $table->index('customer_group_id', 'idx_parties_group');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('parties');
    }
};

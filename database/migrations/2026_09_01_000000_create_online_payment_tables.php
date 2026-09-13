<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_gateways', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('config_schema')->nullable();
            $table->timestamps();
        });

        Schema::create('institute_payment_gateways', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institute_id')->constrained()->cascadeOnDelete();
            $table->foreignId('gateway_id')->constrained('payment_gateways')->cascadeOnDelete();
            $table->boolean('is_enabled')->default(true);
            $table->json('credentials')->nullable();
            $table->json('settings')->nullable();
            $table->timestamps();

            $table->unique(['institute_id', 'gateway_id']);
        });

        Schema::create('online_payment_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institute_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('gateway_id')->constrained('payment_gateways');
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('installment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('student_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('amount', 19, 4);
            $table->decimal('base_amount', 19, 4)->nullable();
            $table->decimal('exchange_rate', 19, 8)->nullable();
            $table->string('currency_code', 10)->nullable();
            $table->string('status', 30)->default('pending');
            $table->string('gateway_reference')->nullable()->unique();
            $table->string('idempotency_key')->nullable()->unique();
            $table->text('failure_reason')->nullable();
            $table->json('gateway_response')->nullable();
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('journal_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('institute_users')->nullOnDelete();
            $table->timestamp('initiated_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['institute_id', 'invoice_id', 'status']);
            $table->index(['institute_id', 'student_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('online_payment_attempts');
        Schema::dropIfExists('institute_payment_gateways');
        Schema::dropIfExists('payment_gateways');
    }
};

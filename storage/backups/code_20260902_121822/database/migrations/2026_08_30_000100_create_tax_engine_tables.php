<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_jurisdictions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institute_id')->constrained('institutes')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->string('name', 150);
            $table->string('code', 30);
            $table->char('country_iso2', 2);
            $table->string('state_code', 30)->nullable();
            $table->foreignId('parent_id')->nullable()->constrained('tax_jurisdictions')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['institute_id', 'branch_id', 'code'], 'uq_jurisdiction_code');
            $table->index(['institute_id', 'country_iso2']);
        });

        Schema::create('tax_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institute_id')->constrained('institutes')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('jurisdiction_id')->nullable()->constrained('tax_jurisdictions')->nullOnDelete();
            $table->foreignId('tax_group_id')->nullable()->constrained('tax_groups')->nullOnDelete();
            $table->string('name', 150);
            $table->enum('type', ['vat', 'sales_tax', 'withholding', 'excise', 'custom'])->default('vat');
            $table->enum('rate_type', ['percentage', 'fixed'])->default('percentage');
            $table->decimal('rate', 10, 4)->default(0);
            $table->boolean('is_compound')->default(false);
            $table->boolean('is_inclusive')->default(false);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['institute_id', 'jurisdiction_id', 'is_active'], 'idx_tax_rates_resolve');
            $table->index(['institute_id', 'tax_group_id'], 'idx_tax_rates_group');
        });

        Schema::create('tax_rate_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institute_id')->constrained('institutes')->cascadeOnDelete();
            $table->foreignId('tax_rate_id')->constrained('tax_rates')->cascadeOnDelete();
            $table->decimal('old_rate', 10, 4);
            $table->decimal('new_rate', 10, 4);
            $table->date('changed_at');
            $table->unsignedBigInteger('changed_by')->nullable();
            $table->timestamps();
        });

        Schema::create('tax_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institute_id')->constrained('institutes')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('jurisdiction_id')->nullable()->constrained('tax_jurisdictions')->nullOnDelete();
            $table->foreignId('tax_rate_id')->constrained('tax_rates')->cascadeOnDelete();
            $table->string('item_type', 50)->default('*');
            $table->string('product_category', 50)->default('*');
            $table->foreignId('tax_group_id')->nullable()->constrained('tax_groups')->nullOnDelete();
            $table->unsignedInteger('priority')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['institute_id', 'jurisdiction_id', 'is_active'], 'idx_tax_rules_resolve');
        });

        Schema::create('tax_return_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institute_id')->constrained('institutes')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('jurisdiction_id')->nullable()->constrained('tax_jurisdictions')->nullOnDelete();
            $table->string('name', 150);
            $table->date('period_start');
            $table->date('period_end');
            $table->date('due_date');
            $table->enum('status', ['open', 'filed', 'overdue'])->default('open');
            $table->decimal('total_sales', 19, 4)->default(0);
            $table->decimal('total_purchases', 19, 4)->default(0);
            $table->decimal('tax_collected', 19, 4)->default(0);
            $table->decimal('tax_paid', 19, 4)->default(0);
            $table->decimal('net_tax', 19, 4)->default(0);
            $table->foreignId('journal_id')->nullable()->constrained('journals')->nullOnDelete();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index(['institute_id', 'jurisdiction_id', 'status']);
        });

        Schema::create('tax_return_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institute_id')->constrained('institutes')->cascadeOnDelete();
            $table->foreignId('tax_return_id')->constrained('tax_return_periods')->cascadeOnDelete();
            $table->foreignId('tax_rate_id')->nullable()->constrained('tax_rates')->nullOnDelete();
            $table->string('description', 200);
            $table->decimal('total_sales', 19, 4)->default(0);
            $table->decimal('total_purchases', 19, 4)->default(0);
            $table->decimal('tax_collected', 19, 4)->default(0);
            $table->decimal('tax_paid', 19, 4)->default(0);
            $table->decimal('net_tax', 19, 4)->default(0);
            $table->timestamps();
        });

        Schema::create('tax_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institute_id')->constrained('institutes')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->string('event', 50);
            $table->string('actor_type', 50)->nullable();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('entity_type', 50);
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->json('old_value')->nullable();
            $table->json('new_value')->nullable();
            $table->timestamps();

            $table->index(['institute_id', 'entity_type', 'entity_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_audit_logs');
        Schema::dropIfExists('tax_return_lines');
        Schema::dropIfExists('tax_return_periods');
        Schema::dropIfExists('tax_rules');
        Schema::dropIfExists('tax_rate_history');
        Schema::dropIfExists('tax_rates');
        Schema::dropIfExists('tax_jurisdictions');
    }
};

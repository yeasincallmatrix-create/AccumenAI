<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Global Accounting Engine — Step 1: fiscal years & accounting periods.
 *
 * Posting is only allowed inside an open accounting period of the current
 * fiscal year. A year is closed once per institute; reopening is an audit
 * event. One period per (fiscal year, name).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('fiscal_years')) {
            Schema::create('fiscal_years', function (Blueprint $table) {
                $table->id();
                $table->foreignId('institute_id')->constrained('institutes')->cascadeOnDelete();
                $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
                $table->string('name', 50);
                $table->date('start_date');
                $table->date('end_date');
                $table->enum('status', ['open', 'closed', 'archived'])->default('open');
                $table->boolean('is_current')->default(false);
                $table->unsignedBigInteger('closed_by')->nullable();
                $table->timestamp('closed_at')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->unique(['institute_id', 'branch_id', 'name'], 'uq_fiscal_years_name');
            });
        }

        if (! Schema::hasTable('accounting_periods')) {
            Schema::create('accounting_periods', function (Blueprint $table) {
                $table->id();
                $table->foreignId('institute_id')->constrained('institutes')->cascadeOnDelete();
                $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
                $table->foreignId('fiscal_year_id')->constrained('fiscal_years')->cascadeOnDelete();
                $table->string('name', 50);
                $table->date('start_date');
                $table->date('end_date');
                $table->enum('status', ['open', 'closed', 'locked'])->default('open');
                $table->unsignedBigInteger('closed_by')->nullable();
                $table->timestamp('closed_at')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->unique(
                    ['institute_id', 'branch_id', 'fiscal_year_id', 'name'],
                    'uq_accounting_periods_name'
                );
                $table->index('fiscal_year_id', 'idx_accounting_periods_year');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_periods');
        Schema::dropIfExists('fiscal_years');
    }
};

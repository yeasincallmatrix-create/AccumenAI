<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Dealership Phase 3 — Targets & Commission (5 new tables).
     *
     * All tables carry institute_id NOT NULL + index (codebase tenancy
     * pattern) and per-tenant composite uniques. FKs are added separately
     * in 2026_09_29_130100_add_dealership_phase3_fks. Guarded by hasTable
     * for idempotency.
     */
    public function up(): void
    {
        if (! Schema::hasTable('dealership_sr_targets')) {
            Schema::create('dealership_sr_targets', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('institute_id')->index();
                $table->unsignedBigInteger('sales_force_id')->index();
                $table->enum('period_type', ['monthly', 'quarterly', 'yearly']);
                $table->date('period_start');
                $table->date('period_end');
                $table->decimal('target_amount', 15, 2)->default(0);
                $table->decimal('achieved_amount', 15, 2)->default(0);
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->unique(['institute_id', 'sales_force_id', 'period_type', 'period_start'], 'dst_scope_unique');
                $table->index(['institute_id', 'period_start', 'period_end']);
            });
        }

        if (! Schema::hasTable('dealership_brand_targets')) {
            Schema::create('dealership_brand_targets', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('institute_id')->index();
                $table->unsignedBigInteger('brand_id')->index();
                $table->enum('period_type', ['monthly', 'quarterly', 'yearly']);
                $table->date('period_start');
                $table->date('period_end');
                $table->decimal('target_amount', 15, 2)->default(0);
                $table->decimal('achieved_amount', 15, 2)->default(0);
                $table->timestamps();
                $table->unique(['institute_id', 'brand_id', 'period_type', 'period_start'], 'dbt_scope_unique');
            });
        }

        if (! Schema::hasTable('dealership_sr_commission')) {
            Schema::create('dealership_sr_commission', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('institute_id')->index();
                $table->unsignedBigInteger('sales_force_id')->index();
                $table->date('period_start');
                $table->date('period_end');
                $table->decimal('base_amount', 15, 2)->default(0);
                $table->decimal('commission_rate', 5, 2)->default(0);
                $table->decimal('commission_amount', 15, 2)->default(0);
                $table->enum('status', ['pending', 'approved', 'paid', 'cancelled'])->default('pending');
                $table->unsignedBigInteger('approved_by')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->unique(['institute_id', 'sales_force_id', 'period_start', 'period_end'], 'dsc_scope_unique');
                $table->index(['institute_id', 'status']);
            });
        }

        if (! Schema::hasTable('dealership_incentives')) {
            Schema::create('dealership_incentives', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('institute_id')->index();
                $table->unsignedBigInteger('sales_force_id')->index();
                $table->string('rule_name', 120);
                $table->enum('rule_type', ['flat', 'percentage', 'tiered']);
                $table->decimal('threshold_amount', 15, 2)->default(0);
                $table->decimal('incentive_amount', 15, 2)->default(0);
                $table->date('earned_on')->nullable();
                $table->enum('status', ['pending', 'approved', 'paid', 'cancelled'])->default('pending');
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->index(['institute_id', 'sales_force_id', 'earned_on'], 'di_sr_earned_idx');
                $table->index(['institute_id', 'status'], 'di_status_idx');
            });
        }

        if (! Schema::hasTable('dealership_attendance')) {
            Schema::create('dealership_attendance', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('institute_id')->index();
                $table->unsignedBigInteger('sales_force_id')->index();
                $table->date('attendance_date');
                $table->timestamp('check_in_at')->nullable();
                $table->timestamp('check_out_at')->nullable();
                $table->unsignedBigInteger('beat_id')->nullable()->index();
                $table->enum('status', ['present', 'absent', 'half_day', 'leave'])->default('present');
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->unique(['institute_id', 'sales_force_id', 'attendance_date'], 'dat_scope_unique');
                $table->index(['institute_id', 'attendance_date']);
            });
        }
    }

    public function down(): void
    {
        // NO destructive rollback per project rules.
    }
};

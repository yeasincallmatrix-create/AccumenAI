<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Dealership Phase 1 business tables (created late in Phase 2.5).
     *
     * All tables carry institute_id NOT NULL + index, following the
     * codebase tenancy pattern. Cross-table FKs are added separately in
     * 2026_09_29_120200_add_dealership_fk_constraints (after all tables
     * exist). Each create is guarded by hasTable for idempotency.
     */
    public function up(): void
    {
        if (! Schema::hasTable('dealership_brands')) {
            Schema::create('dealership_brands', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('institute_id')->index();
                $table->string('code', 50);
                $table->string('name');
                $table->string('name_bn')->nullable();
                $table->string('logo_path')->nullable();
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
                $table->unique(['institute_id', 'code']);
            });
        }

        if (! Schema::hasTable('dealership_beats')) {
            Schema::create('dealership_beats', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('institute_id')->index();
                $table->string('code', 50);
                $table->string('name');
                $table->string('name_bn')->nullable();
                $table->string('region')->nullable();
                $table->string('route')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->unique(['institute_id', 'code']);
            });
        }

        if (! Schema::hasTable('dealership_products')) {
            Schema::create('dealership_products', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('institute_id')->index();
                $table->unsignedBigInteger('brand_id')->index();
                $table->string('sku', 80);
                $table->string('name');
                $table->string('name_bn')->nullable();
                $table->string('category')->nullable();
                $table->decimal('retail_price', 15, 2)->default(0);
                $table->decimal('wholesale_price', 15, 2)->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->unique(['institute_id', 'sku']);
                $table->index(['institute_id', 'brand_id', 'is_active']);
            });
        }

        if (! Schema::hasTable('dealership_sales_force')) {
            Schema::create('dealership_sales_force', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('institute_id')->index();
                $table->string('employee_code', 50);
                $table->string('name');
                $table->string('name_bn')->nullable();
                $table->string('phone', 30)->nullable();
                $table->unsignedBigInteger('beat_id')->nullable()->index();
                $table->decimal('monthly_target', 15, 2)->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->unique(['institute_id', 'employee_code']);
            });
        }

        if (! Schema::hasTable('dealership_customers')) {
            Schema::create('dealership_customers', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('institute_id')->index();
                $table->string('code', 50);
                $table->string('name');
                $table->string('name_bn')->nullable();
                $table->string('phone', 30)->nullable();
                $table->text('address')->nullable();
                $table->enum('channel', ['retail', 'wholesale'])->default('retail');
                $table->unsignedBigInteger('beat_id')->nullable()->index();
                $table->decimal('credit_limit', 15, 2)->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->unique(['institute_id', 'code']);
                $table->index(['institute_id', 'channel', 'is_active']);
            });
        }
    }

    public function down(): void
    {
        // NO destructive rollback per project rules.
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Global Fixed Asset Engine — STEP 17: core tables.
 *
 * Fixed assets are long-term assets owned/controlled by a tenant, tracked
 * separately from inventory (short-term stock). Every table is tenant-scoped
 * (institute_id) and branch-aware (branch_id NULL = institute-wide). Money is
 * DECIMAL(19,4) — never float.
 *
 * The depreciation ledger (asset_depreciation_entries) is the immutable source
 * of truth; fixed_assets.accumulated_depreciation is a cached total that must
 * equal the sum of posted entries (rebuilt/verified by FixedAssetReconciliationService).
 *
 * Additive only. Reversible via down() (drop FK -> drop index -> drop table).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('asset_categories')) {
            Schema::create('asset_categories', function (Blueprint $table) {
                $table->id();
                $table->foreignId('institute_id')->constrained('institutes')->cascadeOnDelete();
                $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
                $table->string('name', 100);
                $table->string('code', 40)->nullable();
                $table->foreignId('asset_account_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();
                $table->foreignId('accumulated_depreciation_account_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();
                $table->foreignId('depreciation_expense_account_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();
                $table->foreignId('disposal_gain_account_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();
                $table->foreignId('disposal_loss_account_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();
                $table->foreignId('impairment_account_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();
                $table->unsignedSmallInteger('default_useful_life_months')->nullable();
                $table->string('default_depreciation_method', 40)->nullable();
                $table->boolean('is_system')->default(false);
                $table->boolean('is_active')->default(true);
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->unique(['institute_id', 'branch_id', 'name'], 'uq_asset_categories_name');
                $table->index(['institute_id', 'is_active'], 'idx_asset_categories_institute');
            });
        }

        if (! Schema::hasTable('asset_locations')) {
            Schema::create('asset_locations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('institute_id')->constrained('institutes')->cascadeOnDelete();
                $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
                $table->string('name', 100);
                $table->string('code', 40)->nullable();
                $table->boolean('is_active')->default(true);
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->unique(['institute_id', 'branch_id', 'name'], 'uq_asset_locations_name');
            });
        }

        if (! Schema::hasTable('fixed_assets')) {
            Schema::create('fixed_assets', function (Blueprint $table) {
                $table->id();
                $table->foreignId('institute_id')->constrained('institutes')->cascadeOnDelete();
                $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
                $table->foreignId('category_id')->nullable()->constrained('asset_categories')->nullOnDelete();
                $table->foreignId('location_id')->nullable()->constrained('asset_locations')->nullOnDelete();
                $table->foreignId('vendor_party_id')->nullable()->constrained('parties')->nullOnDelete();

                $table->string('asset_code', 60);
                $table->string('name', 200);
                $table->text('description')->nullable();
                $table->string('serial_number', 120)->nullable();
                $table->string('manufacturer', 120)->nullable();
                $table->string('model', 120)->nullable();

                $table->date('purchase_date')->nullable();
                $table->date('capitalization_date')->nullable();
                $table->string('purchase_document_no', 80)->nullable();
                $table->string('invoice_reference', 80)->nullable();

                $table->decimal('acquisition_cost', 19, 4)->default(0);
                $table->decimal('additional_capitalized_cost', 19, 4)->default(0);
                $table->decimal('residual_value', 19, 4)->default(0);
                $table->unsignedSmallInteger('useful_life_months')->nullable();
                $table->string('depreciation_method', 40)->default('straight_line');
                $table->string('depreciation_frequency', 20)->default('monthly');
                $table->string('depreciation_convention', 20)->default('full_month');
                $table->decimal('depreciation_rate', 10, 4)->nullable();
                $table->date('depreciation_start_date')->nullable();

                $table->decimal('accumulated_depreciation', 19, 4)->default(0);
                $table->decimal('impairment_amount', 19, 4)->default(0);
                $table->boolean('is_depreciable')->default(true);

                $table->string('unit_of_measure', 40)->nullable();
                $table->decimal('total_units', 19, 4)->nullable();

                $table->string('status', 30)->default('draft');
                $table->string('department', 80)->nullable();
                $table->string('responsible_person', 120)->nullable();

                $table->string('warranty_provider', 120)->nullable();
                $table->date('warranty_start')->nullable();
                $table->date('warranty_end')->nullable();
                $table->string('warranty_reference', 80)->nullable();
                $table->text('warranty_notes')->nullable();

                $table->text('notes')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->unique(['institute_id', 'asset_code'], 'uq_fixed_assets_code');
                $table->index(['institute_id', 'status'], 'idx_fixed_assets_status');
                $table->index(['institute_id', 'category_id'], 'idx_fixed_assets_category');
            });
        }

        if (! Schema::hasTable('asset_cost_components')) {
            Schema::create('asset_cost_components', function (Blueprint $table) {
                $table->id();
                $table->foreignId('institute_id')->constrained('institutes')->cascadeOnDelete();
                $table->foreignId('asset_id')->constrained('fixed_assets')->cascadeOnDelete();
                $table->string('component_type', 40)->default('purchase');
                $table->decimal('amount', 19, 4)->default(0);
                $table->string('description', 255)->nullable();
                $table->string('reference', 120)->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['asset_id'], 'idx_asset_cost_components_asset');
            });
        }

        if (! Schema::hasTable('asset_transfers')) {
            Schema::create('asset_transfers', function (Blueprint $table) {
                $table->id();
                $table->foreignId('institute_id')->constrained('institutes')->cascadeOnDelete();
                $table->foreignId('asset_id')->constrained('fixed_assets')->cascadeOnDelete();
                $table->foreignId('from_branch_id')->nullable()->constrained('branches')->nullOnDelete();
                $table->foreignId('to_branch_id')->nullable()->constrained('branches')->nullOnDelete();
                $table->foreignId('from_location_id')->nullable()->constrained('asset_locations')->nullOnDelete();
                $table->foreignId('to_location_id')->nullable()->constrained('asset_locations')->nullOnDelete();
                $table->string('from_department', 80)->nullable();
                $table->string('to_department', 80)->nullable();
                $table->string('from_custodian', 120)->nullable();
                $table->string('to_custodian', 120)->nullable();
                $table->date('transfer_date');
                $table->string('reason', 255)->nullable();
                $table->unsignedBigInteger('actor_id')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['asset_id'], 'idx_asset_transfers_asset');
            });
        }

        if (! Schema::hasTable('asset_disposals')) {
            Schema::create('asset_disposals', function (Blueprint $table) {
                $table->id();
                $table->foreignId('institute_id')->constrained('institutes')->cascadeOnDelete();
                $table->foreignId('asset_id')->constrained('fixed_assets')->cascadeOnDelete();
                $table->string('disposal_type', 30)->default('sale');
                $table->date('disposal_date');
                $table->decimal('sale_proceeds', 19, 4)->default(0);
                $table->decimal('gain_loss', 19, 4)->default(0);
                $table->string('reason', 255)->nullable();
                $table->foreignId('journal_id')->nullable()->constrained('journals')->nullOnDelete();
                $table->unsignedBigInteger('actor_id')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['asset_id'], 'idx_asset_disposals_asset');
            });
        }

        if (! Schema::hasTable('asset_impairments')) {
            Schema::create('asset_impairments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('institute_id')->constrained('institutes')->cascadeOnDelete();
                $table->foreignId('asset_id')->constrained('fixed_assets')->cascadeOnDelete();
                $table->date('impairment_date');
                $table->decimal('impairment_amount', 19, 4)->default(0);
                $table->decimal('recoverable_amount', 19, 4)->nullable();
                $table->string('reason', 255)->nullable();
                $table->foreignId('journal_id')->nullable()->constrained('journals')->nullOnDelete();
                $table->unsignedBigInteger('actor_id')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['asset_id'], 'idx_asset_impairments_asset');
            });
        }

        if (! Schema::hasTable('asset_revaluations')) {
            Schema::create('asset_revaluations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('institute_id')->constrained('institutes')->cascadeOnDelete();
                $table->foreignId('asset_id')->constrained('fixed_assets')->cascadeOnDelete();
                $table->date('revaluation_date');
                $table->decimal('previous_carrying_amount', 19, 4)->default(0);
                $table->decimal('new_carrying_amount', 19, 4)->default(0);
                $table->decimal('difference', 19, 4)->default(0);
                $table->string('reason', 255)->nullable();
                $table->string('status', 20)->default('requested');
                $table->unsignedBigInteger('approved_by')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->foreignId('journal_id')->nullable()->constrained('journals')->nullOnDelete();
                $table->unsignedBigInteger('actor_id')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['asset_id'], 'idx_asset_revaluations_asset');
            });
        }

        if (! Schema::hasTable('asset_depreciation_runs')) {
            Schema::create('asset_depreciation_runs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('institute_id')->constrained('institutes')->cascadeOnDelete();
                $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
                $table->date('period_start');
                $table->date('period_end');
                $table->string('status', 20)->default('posted');
                $table->foreignId('journal_id')->nullable()->constrained('journals')->nullOnDelete();
                $table->unsignedBigInteger('actor_id')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->unique(['institute_id', 'branch_id', 'period_start'], 'uq_asset_depr_runs_period');
            });
        }

        if (! Schema::hasTable('asset_depreciation_entries')) {
            Schema::create('asset_depreciation_entries', function (Blueprint $table) {
                $table->id();
                $table->foreignId('institute_id')->constrained('institutes')->cascadeOnDelete();
                $table->foreignId('asset_id')->constrained('fixed_assets')->cascadeOnDelete();
                $table->foreignId('run_id')->nullable()->constrained('asset_depreciation_runs')->nullOnDelete();
                $table->date('period_start');
                $table->date('period_end');
                $table->decimal('opening_nbv', 19, 4)->default(0);
                $table->decimal('depreciation_amount', 19, 4)->default(0);
                $table->decimal('accumulated_depreciation', 19, 4)->default(0);
                $table->decimal('closing_nbv', 19, 4)->default(0);
                $table->string('method', 40)->nullable();
                $table->decimal('rate', 10, 4)->nullable();
                $table->decimal('units', 19, 4)->nullable();
                $table->unsignedBigInteger('actor_id')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->unique(['asset_id', 'period_start'], 'uq_asset_depr_entries_asset_period');
                $table->index(['asset_id'], 'idx_asset_depr_entries_asset');
            });
        }

        if (! Schema::hasTable('asset_method_changes')) {
            Schema::create('asset_method_changes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('institute_id')->constrained('institutes')->cascadeOnDelete();
                $table->foreignId('asset_id')->constrained('fixed_assets')->cascadeOnDelete();
                $table->string('old_method', 40)->nullable();
                $table->string('new_method', 40);
                $table->unsignedSmallInteger('old_useful_life_months')->nullable();
                $table->unsignedSmallInteger('new_useful_life_months')->nullable();
                $table->decimal('old_residual_value', 19, 4)->nullable();
                $table->decimal('new_residual_value', 19, 4)->nullable();
                $table->string('reason', 255)->nullable();
                $table->string('status', 20)->default('requested');
                $table->date('effective_date')->nullable();
                $table->unsignedBigInteger('approved_by')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->unsignedBigInteger('actor_id')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['asset_id'], 'idx_asset_method_changes_asset');
            });
        }

        if (! Schema::hasTable('asset_audit_logs')) {
            Schema::create('asset_audit_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('institute_id')->constrained('institutes')->cascadeOnDelete();
                $table->foreignId('asset_id')->nullable()->constrained('fixed_assets')->cascadeOnDelete();
                $table->string('event', 60);
                $table->unsignedBigInteger('actor_id')->nullable();
                $table->json('old_value')->nullable();
                $table->json('new_value')->nullable();
                $table->string('reason', 255)->nullable();
                $table->timestamp('created_at')->nullable();

                $table->index(['institute_id', 'asset_id'], 'idx_asset_audit_logs_asset');
            });
        }

        if (! Schema::hasTable('asset_qr_codes')) {
            Schema::create('asset_qr_codes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('institute_id')->constrained('institutes')->cascadeOnDelete();
                $table->foreignId('asset_id')->constrained('fixed_assets')->cascadeOnDelete();
                $table->string('token', 64)->unique('uq_asset_qr_codes_token');
                $table->boolean('is_active')->default(true);
                $table->timestamp('generated_at')->nullable();
                $table->timestamp('revoked_at')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['asset_id'], 'idx_asset_qr_codes_asset');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_qr_codes');
        Schema::dropIfExists('asset_audit_logs');
        Schema::dropIfExists('asset_method_changes');
        Schema::dropIfExists('asset_depreciation_entries');
        Schema::dropIfExists('asset_depreciation_runs');
        Schema::dropIfExists('asset_revaluations');
        Schema::dropIfExists('asset_impairments');
        Schema::dropIfExists('asset_disposals');
        Schema::dropIfExists('asset_transfers');
        Schema::dropIfExists('asset_cost_components');
        Schema::dropIfExists('fixed_assets');
        Schema::dropIfExists('asset_locations');
        Schema::dropIfExists('asset_categories');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('institute_module_entitlements')) {
            return;
        }

        Schema::create('institute_module_entitlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institute_id')->constrained('institutes')->cascadeOnDelete();
            // module_key references module_registry.key — no FK to avoid circular/historical issues,
            // consistent with institute_module_overrides (string 60, no FK). Indexed for lookups.
            $table->string('module_key', 60);
            $table->enum('status', ['active', 'expired', 'revoked', 'trialing', 'pending'])->default('active');
            $table->boolean('is_grant')->default(true)->comment('true=grant, false=explicit deny');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamp('trial_starts_at')->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->decimal('monthly_price', 10, 2)->nullable();
            $table->decimal('yearly_price', 10, 2)->nullable();
            $table->enum('billing_cycle', ['monthly', 'yearly', 'one_time'])->nullable();
            $table->boolean('auto_renew')->default(false);
            $table->decimal('discount_percent', 5, 2)->nullable();
            // purchased_by → users.id (global account) — nullable SET NULL, consistent with institute_module_overrides.overridden_by
            if (Schema::hasTable('users')) {
                $table->foreignId('purchased_by')->nullable()->constrained('users')->nullOnDelete();
            } else {
                $table->unsignedBigInteger('purchased_by')->nullable();
            }
            // granted_by → platform_admins.id — platform_admins exists since 2026_08_13_000200
            if (Schema::hasTable('platform_admins')) {
                $table->foreignId('granted_by')->nullable()->constrained('platform_admins')->nullOnDelete();
            } else {
                $table->unsignedBigInteger('granted_by')->nullable();
            }
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Lean indexes — no MySQL partial unique (WHERE deleted_at IS NULL) to stay compatible
            // Duplicate active prevention is enforced at application layer (see ModuleAccessService future).
            // Historical expired/revoked rows remain queryable.
            $table->index(['institute_id', 'module_key'], 'idx_ime_inst_module');
            $table->index(['institute_id', 'status', 'ends_at'], 'idx_ime_inst_status_ends');
            $table->index(['trial_ends_at'], 'idx_ime_trial_ends');
            $table->index(['starts_at'], 'idx_ime_starts');
            $table->index(['module_key'], 'idx_ime_module');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('institute_module_entitlements');
    }
};

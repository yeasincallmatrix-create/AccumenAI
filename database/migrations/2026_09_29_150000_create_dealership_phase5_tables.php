<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Dealership Phase 5 — API infrastructure (3 tables, backend only).
     *
     * Token model complements Sanctum (per-SR device tokens; SR rows are
     * not Authenticatable users). All tables carry institute_id NOT NULL
     * + index and per-tenant composite uniques. Index names kept ≤60
     * chars (MySQL limit). Guarded by hasTable for idempotency.
     */
    public function up(): void
    {
        if (! Schema::hasTable('dealership_api_tokens')) {
            Schema::create('dealership_api_tokens', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('institute_id')->index();
                $table->unsignedBigInteger('sales_force_id')->index();
                $table->string('token_hash', 64);
                $table->string('name', 120);
                $table->json('abilities')->nullable();
                $table->timestamp('last_used_at')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamp('revoked_at')->nullable();
                $table->timestamps();
                $table->unique(['institute_id', 'token_hash'], 'dat_token_unique');
                $table->index(['institute_id', 'sales_force_id'], 'dat_sf_idx');
                $table->index(['institute_id', 'revoked_at'], 'dat_revoke_idx');
            });
        }

        if (! Schema::hasTable('dealership_api_endpoints')) {
            Schema::create('dealership_api_endpoints', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('institute_id')->index();
                $table->string('endpoint_key', 80);
                $table->enum('http_method', ['GET', 'POST', 'PUT', 'PATCH', 'DELETE']);
                $table->string('uri', 160);
                $table->string('description', 255)->nullable();
                $table->string('required_permission', 80)->nullable();
                $table->boolean('is_enabled')->default(true);
                $table->string('version', 20)->default('v1');
                $table->timestamps();
                $table->unique(['institute_id', 'endpoint_key', 'http_method'], 'dae_key_unique');
                $table->index(['institute_id', 'is_enabled'], 'dae_enabled_idx');
            });
        }

        if (! Schema::hasTable('dealership_push_notifications')) {
            Schema::create('dealership_push_notifications', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('institute_id')->index();
                $table->unsignedBigInteger('recipient_sales_force_id')->index();
                $table->string('title', 160);
                $table->text('body');
                $table->json('data')->nullable();
                $table->enum('status', ['queued', 'sent', 'failed', 'cancelled'])->default('queued');
                $table->timestamp('scheduled_at')->nullable();
                $table->timestamp('sent_at')->nullable();
                $table->string('failure_reason', 255)->nullable();
                $table->timestamps();
                $table->index(['institute_id', 'status', 'scheduled_at'], 'dpn_queue_idx');
                $table->index(['institute_id', 'recipient_sales_force_id'], 'dpn_recipient_idx');
            });
        }
    }

    public function down(): void
    {
        // NO destructive rollback per project rules.
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_deletion_requests', function (Blueprint $table) {
            $table->id();
            $table->string('deletable_type', 100);
            $table->unsignedBigInteger('deletable_id');
            $table->unsignedBigInteger('institute_id')->nullable();
            $table->unsignedBigInteger('requested_by');
            $table->string('requested_by_type', 50)->default('user');
            $table->string('reason', 500)->nullable();
            $table->string('confirmation_token', 64)->unique();
            $table->enum('status', ['pending', 'confirmed', 'cancelled', 'expired'])->default('pending');
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['deletable_type', 'deletable_id']);
            $table->index(['institute_id', 'status']);
            $table->index('expires_at');
        });

        // Recovery soft-delete archive for institutes (protects against accidental cascade)
        Schema::create('tenant_recovery_archives', function (Blueprint $table) {
            $table->id();
            $table->string('archivable_type', 100);
            $table->unsignedBigInteger('archivable_id');
            $table->unsignedBigInteger('institute_id')->nullable();
            $table->json('snapshot');
            $table->unsignedBigInteger('archived_by')->nullable();
            $table->timestamp('archived_at')->useCurrent();
            $table->timestamps();

            $table->index(['archivable_type', 'archivable_id']);
            $table->index('institute_id');
        });

        // Add soft-delete + protection columns to institutes if not exists
        if (Schema::hasTable('institutes')) {
            Schema::table('institutes', function (Blueprint $table) {
                if (! Schema::hasColumn('institutes', 'deleted_at')) {
                    $table->softDeletes();
                }
                if (! Schema::hasColumn('institutes', 'deletion_requested_at')) {
                    $table->timestamp('deletion_requested_at')->nullable();
                }
                if (! Schema::hasColumn('institutes', 'deletion_requested_by')) {
                    $table->unsignedBigInteger('deletion_requested_by')->nullable();
                }
            });
        }

        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                if (! Schema::hasColumn('users', 'deleted_at')) {
                    $table->softDeletes();
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('institutes')) {
            Schema::table('institutes', function (Blueprint $table) {
                if (Schema::hasColumn('institutes', 'deleted_at')) {
                    $table->dropSoftDeletes();
                }
                if (Schema::hasColumn('institutes', 'deletion_requested_at')) {
                    $table->dropColumn('deletion_requested_at');
                }
                if (Schema::hasColumn('institutes', 'deletion_requested_by')) {
                    $table->dropColumn('deletion_requested_by');
                }
            });
        }
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                if (Schema::hasColumn('users', 'deleted_at')) {
                    $table->dropSoftDeletes();
                }
            });
        }
        Schema::dropIfExists('tenant_recovery_archives');
        Schema::dropIfExists('tenant_deletion_requests');
    }
};

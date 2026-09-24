<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('super_admin_overrides')) {
            Schema::create('super_admin_overrides', function (Blueprint $table) {
                $table->id();
                $table->foreignId('institute_id')
                    ->constrained('institutes')
                    ->cascadeOnDelete();
                $table->string('module_key', 60);
                $table->enum('override_layer', ['industry', 'country']);
                $table->text('reason');
                $table->foreignId('approved_by')
                    ->nullable()
                    ->constrained('platform_admins')
                    ->nullOnDelete();
                $table->boolean('two_factor_verified')->default(false);
                $table->text('email_sent_to')->nullable();
                $table->timestamp('started_at')->useCurrent();
                $table->timestamp('expires_at')->nullable();
                $table->timestamps();

                $table->unique(
                    ['institute_id', 'module_key', 'override_layer'],
                    'super_admin_overrides_unique'
                );
                $table->index('expires_at', 'super_admin_overrides_expires_at_index');
            });

            return;
        }

        // Repair path: a prior run created the table but failed while adding
        // an over-long unique index name (MySQL 64-char identifier limit).
        if (! Schema::hasIndex('super_admin_overrides', ['institute_id', 'module_key', 'override_layer'], 'unique')) {
            Schema::table('super_admin_overrides', function (Blueprint $table) {
                $table->unique(
                    ['institute_id', 'module_key', 'override_layer'],
                    'super_admin_overrides_unique'
                );
            });
        }

        if (! Schema::hasIndex('super_admin_overrides', ['expires_at'])) {
            Schema::table('super_admin_overrides', function (Blueprint $table) {
                $table->index('expires_at', 'super_admin_overrides_expires_at_index');
            });
        }

        // started_at was implicitly created with ON UPDATE CURRENT_TIMESTAMP;
        // a start timestamp must never auto-update on row changes.
        DB::statement(
            'ALTER TABLE super_admin_overrides MODIFY started_at timestamp NOT NULL DEFAULT current_timestamp()'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('super_admin_overrides');
    }
};

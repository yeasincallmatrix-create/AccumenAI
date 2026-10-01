<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Reference table: which manifests reference which chunks.
        // drive_file_id denormalized for the Phase 2B GC rule:
        // a Drive file is orphaned only when 0 LIVE manifests reference it.
        Schema::create('backup_chunk_references', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('manifest_id');
            $table->unsignedBigInteger('chunk_id');
            $table->char('content_sha256', 64);   // denormalized for fast queries
            $table->string('drive_file_id', 100); // denormalized — GC unit
            $table->timestamps();

            $table->index('tenant_id');
            $table->index('manifest_id');
            $table->index('chunk_id');
            $table->index('drive_file_id');
            $table->index(['tenant_id', 'content_sha256'], 'bc_ref_tenant_hash_idx');
            $table->index(['tenant_id', 'drive_file_id'], 'bc_ref_tenant_file_idx');
            $table->unique(['manifest_id', 'chunk_id'], 'bc_ref_manifest_chunk_unique');
        });

        // Row-level refcount (informational; GC uses drive_file_id rule)
        Schema::table('backup_chunks', function (Blueprint $table) {
            if (!Schema::hasColumn('backup_chunks', 'reference_count')) {
                $table->unsignedInteger('reference_count')->default(0)->after('size_bytes');
            }
        });
    }

    public function down(): void
    {
        Schema::table('backup_chunks', function (Blueprint $table) {
            if (Schema::hasColumn('backup_chunks', 'reference_count')) {
                $table->dropColumn('reference_count');
            }
        });
        Schema::dropIfExists('backup_chunk_references');
    }
};

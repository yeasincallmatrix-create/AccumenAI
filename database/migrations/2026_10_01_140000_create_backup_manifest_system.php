<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Manifests (per backup)
        Schema::create('backup_manifests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('backup_id')->unique();
            $table->unsignedBigInteger('tenant_id');
            $table->string('drive_file_id', 100)->nullable();      // current.json.enc
            $table->string('checksum_file_id', 100)->nullable();   // current.json.sha256
            $table->mediumText('manifest_json');                   // F7: mediumText (64KB TEXT limit unsafe)
            $table->char('manifest_sha256', 64);
            $table->char('file_hmac', 64)->nullable();              // HMAC of encrypted manifest file (decryptFile gate)
            $table->integer('total_chunks')->default(0);
            $table->bigInteger('total_size_bytes')->default(0);
            $table->timestamps();

            $table->index('tenant_id');
        });

        // 2. Chunks (content-addressed)
        Schema::create('backup_chunks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('manifest_id');
            $table->unsignedBigInteger('tenant_id');
            $table->char('content_sha256', 64);                    // dedup key (plaintext hash)
            $table->char('file_hmac', 64)->nullable();              // per-chunk file HMAC (decryptFile gate)
            $table->string('drive_file_id', 100);
            $table->string('source_table', 100)->nullable();       // which table
            $table->integer('chunk_index')->default(1);            // if split
            $table->integer('total_chunks')->default(1);
            $table->bigInteger('size_bytes');
            $table->timestamps();

            $table->index('manifest_id');
            $table->index(['tenant_id', 'content_sha256']);
            $table->unique(['manifest_id', 'content_sha256']);
        });

        // 3. Trash (for GC safety)
        Schema::create('backup_chunk_trash', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('drive_file_id', 100);
            $table->char('content_sha256', 64);
            $table->bigInteger('size_bytes')->default(0);
            $table->timestamp('trashed_at')->useCurrent();
            $table->timestamp('expires_at')->nullable();  // 7 days (set on insert)
            $table->timestamps();

            $table->index('tenant_id');
            $table->index('expires_at');
        });

        // 4. Add columns to tenant_drive_connections (multi-folder structure)
        Schema::table('tenant_drive_connections', function (Blueprint $table) {
            if (!Schema::hasColumn('tenant_drive_connections', 'app_folder_id')) {
                $table->string('app_folder_id', 100)->nullable();
            }
            if (!Schema::hasColumn('tenant_drive_connections', 'chunks_folder_id')) {
                $table->string('chunks_folder_id', 100)->nullable();
            }
            if (!Schema::hasColumn('tenant_drive_connections', 'manifests_folder_id')) {
                $table->string('manifests_folder_id', 100)->nullable();
            }
            if (!Schema::hasColumn('tenant_drive_connections', 'trash_folder_id')) {
                $table->string('trash_folder_id', 100)->nullable();
            }
        });

        // 5. F1: chunked-backup bookkeeping on backups
        Schema::table('backups', function (Blueprint $table) {
            if (!Schema::hasColumn('backups', 'is_chunked')) {
                $table->boolean('is_chunked')->default(false)->after('destination');
            }
            if (!Schema::hasColumn('backups', 'drive_folder_id')) {
                $table->string('drive_folder_id', 100)->nullable()->after('is_chunked');
            }
        });
    }

    public function down(): void
    {
        Schema::table('backups', function (Blueprint $table) {
            $table->dropColumn(['is_chunked', 'drive_folder_id']);
        });
        Schema::table('tenant_drive_connections', function (Blueprint $table) {
            $table->dropColumn(['app_folder_id', 'chunks_folder_id', 'manifests_folder_id', 'trash_folder_id']);
        });
        Schema::dropIfExists('backup_chunk_trash');
        Schema::dropIfExists('backup_chunks');
        Schema::dropIfExists('backup_manifests');
    }
};

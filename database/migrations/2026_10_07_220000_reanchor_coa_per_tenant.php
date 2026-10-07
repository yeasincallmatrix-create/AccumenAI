<?php

use App\Services\Accounting\CoaReanchorService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * F-005 / TASK C1 — re-anchor every tenant's CoA into a self-contained
 * three-level tree (tenant category roots -> tenant anchors -> leaves) and
 * sever all 665 tenant -> global parent links.
 *
 * up():   DDL (backup tables) BEFORE the data transaction — MySQL commits
 *         implicitly on DDL, so creating them mid-transaction would break
 *         rollback atomicity. reanchorAll() is all-DML inside one txn.
 * down(): restore() (DML in a transaction: parents back, created rows
 *         deleted, backup cleared) then drop the backup tables.
 */
return new class extends Migration
{
    public function up(): void
    {
        $service = app(CoaReanchorService::class);

        $service->ensureBackupTables();

        DB::transaction(fn () => $service->reanchorAll());
    }

    public function down(): void
    {
        $service = app(CoaReanchorService::class);

        $service->ensureBackupTables();
        $service->restore();
        $service->dropBackupTables();
    }
};

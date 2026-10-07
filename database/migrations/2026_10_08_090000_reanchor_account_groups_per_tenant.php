<?php

use App\Services\Accounting\CoaReanchorService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * F-014 / TASK C2 — give every tenant its own copy of the 5 shared
 * account_groups (Assets, Liabilities, Equity, Income, Expenses) and
 * re-point every tenant chart_of_accounts.account_group_id at the clone,
 * so no tenant row references a shared global group anymore.
 *
 * up():   DDL (group backup tables) BEFORE the data transaction — MySQL
 *         commits implicitly on DDL, so creating them mid-transaction
 *         would break rollback atomicity. reanchorGroupsAll() is all-DML
 *         inside one txn.
 * down(): restoreGroups() (DML in a transaction: account_group_id back,
 *         created clones deleted, backup cleared) then drop the tables.
 */
return new class extends Migration
{
    public function up(): void
    {
        $service = app(CoaReanchorService::class);

        $service->ensureGroupBackupTables();

        DB::transaction(fn () => $service->reanchorGroupsAll());
    }

    public function down(): void
    {
        $service = app(CoaReanchorService::class);

        $service->ensureGroupBackupTables();
        $service->restoreGroups();
        $service->dropGroupBackupTables();
    }
};

<?php

namespace App\Services\Accounting;

use App\Models\AccountGroup;
use App\Models\Institute;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * F-005 / TASK C1 — re-anchors every tenant's chart of accounts into a
 * self-contained three-level tree (tenant category roots -> tenant anchors
 * -> leaves) and severs the last links from tenant rows to the shared
 * global template.
 *
 * F-014 / TASK C2 — additionally clones the 5 shared global account_groups
 * per tenant and re-points every tenant chart_of_accounts.account_group_id
 * at its tenant-owned clone, so mutating a group can never cross tenants.
 *
 * Design notes:
 *  - Existing rows are never deleted and never change id: only parent_id
 *    moves, plus additive tenant-owned roots/anchors are created (C1);
 *    only account_group_id moves, plus additive tenant-owned group clones
 *    are created (C2).
 *  - Soft-deleted institutes are included: institutes are enumerated from
 *    chart_of_accounts itself, never from the institutes table.
 *  - DDL (backup tables) must happen BEFORE any transaction: MySQL commits
 *    implicitly on DDL, which would break rollback atomicity — hence
 *    ensureBackupTables()/ensureGroupBackupTables() refuse to create tables
 *    while a transaction is open.
 *  - restore()/restoreGroups() only perform DML so they stay safe inside
 *    test transactions; the migration's down() drops the tables itself
 *    afterwards.
 *  - The service is stateless per call site (new instance from the container),
 *    memoising only within one instance's lifetime.
 */
class CoaReanchorService
{
    /** Category root code per account type (tenant-owned clones of globals). */
    public const CATEGORY_ROOTS = [
        'asset' => '1',
        'liability' => '2',
        'equity' => '3',
        'income' => '4',
        'expense' => '5',
    ];

    public const BACKUP_TABLE = 'coa_reanchor_backup';

    public const CREATED_TABLE = 'coa_reanchor_created';

    /** F-014 / TASK C2 — group re-anchor backup tables. */
    public const GROUP_BACKUP_TABLE = 'coa_group_reanchor_backup';

    public const GROUP_CREATED_TABLE = 'coa_group_reanchor_created';

    /** @var array<int, array<string, int>> instituteId => type => root row id */
    private array $rootIds = [];

    /** @var array<int, array<string, int>> instituteId => anchor code => row id */
    private array $anchorIds = [];

    private int $rootsCreated = 0;

    private int $anchorsCreated = 0;

    private ?bool $createdTableAvailable = null;

    /**
     * Create the backup tables. Must run OUTSIDE any transaction: MySQL
     * commits implicitly on DDL, so creating them mid-transaction would
     * silently commit whatever came before.
     */
    public function ensureBackupTables(): void
    {
        if (Schema::hasTable(self::BACKUP_TABLE) && Schema::hasTable(self::CREATED_TABLE)) {
            $this->createdTableAvailable = true;

            return;
        }

        if (DB::transactionLevel() > 0) {
            throw new RuntimeException(
                'CoaReanchorService backup tables are missing and cannot be created inside a transaction '
                .'(MySQL commits implicitly on DDL). Call ensureBackupTables() before opening the transaction.'
            );
        }

        if (! Schema::hasTable(self::BACKUP_TABLE)) {
            Schema::create(self::BACKUP_TABLE, function (Blueprint $table): void {
                $table->unsignedBigInteger('coa_id')->primary();
                $table->unsignedBigInteger('institute_id');
                $table->unsignedBigInteger('parent_id')->nullable();
                $table->timestamp('updated_at')->nullable();
                $table->index('institute_id', 'coa_reanchor_backup_institute_idx');
            });
        }

        if (! Schema::hasTable(self::CREATED_TABLE)) {
            Schema::create(self::CREATED_TABLE, function (Blueprint $table): void {
                $table->unsignedBigInteger('coa_id')->primary();
            });
        }

        $this->createdTableAvailable = true;
    }

    /**
     * Record the pre-change state of every row of one institute. INSERT
     * IGNORE keeps the FIRST snapshot: a second run never overwrites the
     * original parent ids a rollback needs to restore.
     */
    public function snapshot(int $instituteId): void
    {
        $this->ensureBackupTables();

        DB::statement(
            'INSERT IGNORE INTO '.self::BACKUP_TABLE.' (coa_id, institute_id, parent_id, updated_at)
             SELECT id, institute_id, parent_id, updated_at
             FROM chart_of_accounts
             WHERE institute_id = ?',
            [$instituteId]
        );
    }

    /**
     * Re-anchor every institute that still holds cross-tenant links.
     *
     * @return array{institutes: int, roots_created: int, anchors_created: int, leaves_repointed: int, anomalies_fixed: int, cross_tenant_leaves: int}
     */
    public function reanchorAll(): array
    {
        $totals = $this->emptyStats();

        $institutes = DB::table('chart_of_accounts')
            ->whereNotNull('institute_id')
            ->distinct()
            ->orderBy('institute_id')
            ->pluck('institute_id');

        foreach ($institutes as $instituteId) {
            $stats = $this->reanchorInstitute((int) $instituteId);

            if ($stats['cross_tenant_leaves'] > 0) {
                $totals['institutes']++;
            }

            foreach (['roots_created', 'anchors_created', 'leaves_repointed', 'anomalies_fixed', 'cross_tenant_leaves'] as $key) {
                $totals[$key] += $stats[$key];
            }
        }

        return $totals;
    }

    /**
     * Re-anchor one institute:
     *  1. snapshot every tenant row (first run only — INSERT IGNORE);
     *  2. re-point each leaf whose parent is a global row to a tenant-owned
     *     anchor (cloned from that global anchor under a tenant category root);
     *  3. anomalies — a leaf parented straight to a category root (D2) — move
     *     to the tenant anchor derived from the leaf's own code (4002 -> 4000).
     *
     * An institute with zero cross-tenant leaves is a no-op (idempotency).
     *
     * @return array{institutes: int, roots_created: int, anchors_created: int, leaves_repointed: int, anomalies_fixed: int, cross_tenant_leaves: int}
     */
    public function reanchorInstitute(int $instituteId): array
    {
        $stats = $this->emptyStats();

        $leaves = DB::table('chart_of_accounts as c')
            ->join('chart_of_accounts as p', 'p.id', '=', 'c.parent_id')
            ->where('c.institute_id', $instituteId)
            ->whereNull('p.institute_id')
            ->select('c.id as leaf_id', 'c.code as leaf_code', 'p.id as global_id', 'p.code as global_code', 'p.is_header as global_header')
            ->get();

        $stats['cross_tenant_leaves'] = $leaves->count();

        if ($leaves->isEmpty()) {
            return $stats;
        }

        $this->snapshot($instituteId);

        $rootsBefore = $this->rootsCreated;
        $anchorsBefore = $this->anchorsCreated;

        foreach ($leaves as $leaf) {
            $isRootAnomaly = in_array($leaf->global_code, array_values(self::CATEGORY_ROOTS), true);

            if ($isRootAnomaly) {
                // D2: a leaf hanging directly off a category root moves under
                // the tenant anchor derived from its own code (4002 -> 4000).
                $targetCode = (string) (intdiv((int) $leaf->leaf_code, 100) * 100);
            } elseif ((int) $leaf->global_header === 1) {
                $targetCode = (string) $leaf->global_code;
            } else {
                throw new RuntimeException(
                    "CoA re-anchor: institute {$instituteId} leaf id {$leaf->leaf_id} (code {$leaf->leaf_code}) is "
                    ."parented to global non-header id {$leaf->global_id} (code {$leaf->global_code}); refusing to guess."
                );
            }

            $anchorId = $this->ensureAnchor($instituteId, $targetCode);

            DB::table('chart_of_accounts')
                ->where('id', $leaf->leaf_id)
                ->update(['parent_id' => $anchorId, 'updated_at' => now()]);

            $stats['leaves_repointed']++;

            if ($isRootAnomaly) {
                $stats['anomalies_fixed']++;
            }
        }

        $stats['roots_created'] = $this->rootsCreated - $rootsBefore;
        $stats['anchors_created'] = $this->anchorsCreated - $anchorsBefore;

        return $stats;
    }

    /**
     * Return (or create) the tenant-owned anchor row for a template anchor
     * code, creating the tenant category root on the way. Also used by
     * TenantCoaSeederService so new institutes grow the same self-contained
     * structure instead of hanging leaves off the shared global rows (F-005).
     */
    public function ensureAnchor(int $instituteId, string $anchorCode): int
    {
        if (isset($this->anchorIds[$instituteId][$anchorCode])) {
            return $this->anchorIds[$instituteId][$anchorCode];
        }

        $rootType = array_flip(self::CATEGORY_ROOTS)[$anchorCode] ?? null;

        if ($rootType !== null) {
            return $this->ensureRoot($instituteId, $rootType);
        }

        $existing = DB::table('chart_of_accounts')
            ->where('institute_id', $instituteId)
            ->whereNull('branch_id')
            ->where('code', $anchorCode)
            ->first();

        if ($existing !== null) {
            return $this->anchorIds[$instituteId][$anchorCode] = (int) $existing->id;
        }

        $global = DB::table('chart_of_accounts')
            ->whereNull('institute_id')
            ->where('code', $anchorCode)
            ->first();

        if ($global === null || ! (int) $global->is_header) {
            throw new RuntimeException(
                "CoA re-anchor: no global header row with code '{$anchorCode}' exists; cannot create a tenant anchor."
            );
        }

        $rootId = $this->ensureRoot($instituteId, (string) $global->type);
        $id = $this->insertHeader($global, $instituteId, $rootId);
        $this->anchorsCreated++;

        return $this->anchorIds[$instituteId][$anchorCode] = $id;
    }

    /**
     * Undo the re-anchor: put every snapshotted row's parent_id back and
     * delete the rows this service created. DML only (transactional) — the
     * caller drops the tables afterwards if it wants to.
     */
    public function restore(): void
    {
        if (! Schema::hasTable(self::BACKUP_TABLE) || ! Schema::hasTable(self::CREATED_TABLE)) {
            return;
        }

        DB::transaction(function (): void {
            if (DB::table(self::BACKUP_TABLE)->exists()) {
                // Parents first: created rows must lose their children before
                // they can be deleted (children written after the migration
                // simply fall back to NULL via the FK's ON DELETE SET NULL).
                DB::statement(
                    'UPDATE chart_of_accounts c
                     JOIN '.self::BACKUP_TABLE.' b ON b.coa_id = c.id
                     SET c.parent_id = b.parent_id, c.updated_at = b.updated_at
                     WHERE NOT (c.parent_id <=> b.parent_id)'
                );
            }

            DB::statement(
                'DELETE c FROM chart_of_accounts c
                 JOIN '.self::CREATED_TABLE.' cr ON cr.coa_id = c.id'
            );

            DB::table(self::BACKUP_TABLE)->delete();
            DB::table(self::CREATED_TABLE)->delete();
        });
    }

    public function dropBackupTables(): void
    {
        if (Schema::hasTable(self::CREATED_TABLE)) {
            Schema::drop(self::CREATED_TABLE);
        }

        if (Schema::hasTable(self::BACKUP_TABLE)) {
            Schema::drop(self::BACKUP_TABLE);
        }

        $this->createdTableAvailable = false;
    }

    // ------------------------------------------------------------------
    // F-014 / TASK C2 — account_groups re-anchor
    // ------------------------------------------------------------------

    /**
     * Create the group backup tables. Same rules as ensureBackupTables():
     * DDL must run OUTSIDE any transaction (MySQL commits implicitly on it).
     */
    public function ensureGroupBackupTables(): void
    {
        if (Schema::hasTable(self::GROUP_BACKUP_TABLE) && Schema::hasTable(self::GROUP_CREATED_TABLE)) {
            return;
        }

        if (DB::transactionLevel() > 0) {
            throw new RuntimeException(
                'CoaReanchorService group backup tables are missing and cannot be created inside a transaction '
                .'(MySQL commits implicitly on DDL). Call ensureGroupBackupTables() before opening the transaction.'
            );
        }

        if (! Schema::hasTable(self::GROUP_BACKUP_TABLE)) {
            Schema::create(self::GROUP_BACKUP_TABLE, function (Blueprint $table): void {
                $table->unsignedBigInteger('coa_row_id')->primary();
                $table->unsignedBigInteger('original_group_id');
                $table->timestamp('created_at')->useCurrent();
            });
        }

        if (! Schema::hasTable(self::GROUP_CREATED_TABLE)) {
            Schema::create(self::GROUP_CREATED_TABLE, function (Blueprint $table): void {
                $table->unsignedBigInteger('group_id')->primary();
                $table->timestamp('created_at')->useCurrent();
            });
        }
    }

    /**
     * Record the pre-change account_group_id of every tenant row of one
     * institute that still points at a shared global group. INSERT OR IGNORE
     * keeps the FIRST snapshot: a second run never overwrites the original
     * values a rollback needs to restore.
     */
    public function snapshotGroups(int $instituteId): void
    {
        $this->ensureGroupBackupTables();

        $globalIds = DB::table('account_groups')
            ->whereNull('institute_id')
            ->where('is_system', 1)
            ->pluck('id')
            ->all();

        if ($globalIds === []) {
            return;
        }

        $rows = DB::table('chart_of_accounts')
            ->where('institute_id', $instituteId)
            ->whereIn('account_group_id', $globalIds)
            ->get(['id', 'account_group_id']);

        foreach ($rows as $row) {
            DB::table(self::GROUP_BACKUP_TABLE)->insertOrIgnore([
                'coa_row_id' => $row->id,
                'original_group_id' => $row->account_group_id,
            ]);
        }
    }

    /**
     * Re-anchor one institute's account_group links (F-014):
     *  1. snapshot every tenant row still pointing at a global group;
     *  2. clone the 5 global groups for the tenant (reusing any that already
     *     exist — the unique key is (institute, branch, code), so a tenant
     *     clone of code '1' never collides with the global row);
     *  3. re-point the tenant rows to the tenant-owned clones.
     *
     * Assumes the caller's transaction is already open — the migration wraps
     * reanchorGroupsAll() in one; tests run inside DatabaseTransactions.
     *
     * @return array{groups_created: int, coa_rows_repointed: int}
     */
    public function reanchorGroupsForInstitute(int $instituteId): array
    {
        $this->snapshotGroups($instituteId);

        $globalGroups = DB::table('account_groups')
            ->whereNull('institute_id')
            ->where('is_system', 1)
            ->get();

        $groupMap = [];
        $groupsCreated = 0;

        foreach ($globalGroups as $global) {
            $existing = DB::table('account_groups')
                ->where('institute_id', $instituteId)
                ->whereNull('branch_id')
                ->where('code', $global->code)
                ->whereNull('deleted_at')
                ->first();

            if ($existing !== null) {
                $groupMap[$global->id] = (int) $existing->id;

                continue;
            }

            $clone = AccountGroup::create([
                'institute_id' => $instituteId,
                'code' => $global->code,
                'name' => $global->name,
                'category' => $global->category,
                'sort_order' => $global->sort_order,
            ]);

            DB::table(self::GROUP_CREATED_TABLE)->insert(['group_id' => $clone->id]);
            $groupMap[$global->id] = (int) $clone->id;
            $groupsCreated++;
        }

        $tenantRows = DB::table('chart_of_accounts')
            ->where('institute_id', $instituteId)
            ->whereIn('account_group_id', array_keys($groupMap))
            ->get(['id', 'account_group_id']);

        foreach ($tenantRows as $row) {
            $newGroupId = $groupMap[$row->account_group_id] ?? null;

            if ($newGroupId === null) {
                throw new RuntimeException(
                    "CoA group re-anchor: institute {$instituteId} row {$row->id} "
                    ."references unknown group {$row->account_group_id}; refusing to guess."
                );
            }

            DB::table('chart_of_accounts')
                ->where('id', $row->id)
                ->update(['account_group_id' => $newGroupId]);
        }

        return [
            'groups_created' => $groupsCreated,
            'coa_rows_repointed' => $tenantRows->count(),
        ];
    }

    /**
     * Re-anchor every institute that still holds CoA rows (institutes are
     * enumerated from chart_of_accounts itself, like reanchorAll()).
     *
     * @return array{institutes_processed: int}
     */
    public function reanchorGroupsAll(): array
    {
        $institutes = Institute::withTrashed()
            ->whereExists(fn ($q) => $q->select(DB::raw(1))
                ->from('chart_of_accounts')
                ->whereColumn('chart_of_accounts.institute_id', 'institutes.id'))
            ->pluck('id');

        foreach ($institutes as $instituteId) {
            $this->reanchorGroupsForInstitute((int) $instituteId);
        }

        return ['institutes_processed' => $institutes->count()];
    }

    /**
     * Undo the group re-anchor: put every snapshotted account_group_id back
     * and delete the group clones this service created (originals first —
     * the FK on chart_of_accounts.account_group_id is RESTRICT). DML only
     * (transactional); the migration's down() drops the tables afterwards.
     */
    public function restoreGroups(): void
    {
        if (! Schema::hasTable(self::GROUP_BACKUP_TABLE) || ! Schema::hasTable(self::GROUP_CREATED_TABLE)) {
            return;
        }

        DB::transaction(function (): void {
            if (DB::table(self::GROUP_BACKUP_TABLE)->exists()) {
                DB::statement(
                    'UPDATE chart_of_accounts a
                     JOIN '.self::GROUP_BACKUP_TABLE.' b ON b.coa_row_id = a.id
                     SET a.account_group_id = b.original_group_id
                     WHERE NOT (a.account_group_id <=> b.original_group_id)'
                );
            }

            DB::statement(
                'DELETE g FROM account_groups g
                 JOIN '.self::GROUP_CREATED_TABLE.' c ON c.group_id = g.id'
            );

            DB::table(self::GROUP_BACKUP_TABLE)->delete();
            DB::table(self::GROUP_CREATED_TABLE)->delete();
        });
    }

    public function dropGroupBackupTables(): void
    {
        if (Schema::hasTable(self::GROUP_CREATED_TABLE)) {
            Schema::drop(self::GROUP_CREATED_TABLE);
        }

        if (Schema::hasTable(self::GROUP_BACKUP_TABLE)) {
            Schema::drop(self::GROUP_BACKUP_TABLE);
        }
    }

    /**
     * @return array{institutes: int, roots_created: int, anchors_created: int, leaves_repointed: int, anomalies_fixed: int, cross_tenant_leaves: int}
     */
    private function emptyStats(): array
    {
        return [
            'institutes' => 0,
            'roots_created' => 0,
            'anchors_created' => 0,
            'leaves_repointed' => 0,
            'anomalies_fixed' => 0,
            'cross_tenant_leaves' => 0,
        ];
    }

    private function ensureRoot(int $instituteId, string $type): int
    {
        if (isset($this->rootIds[$instituteId][$type])) {
            return $this->rootIds[$instituteId][$type];
        }

        $rootCode = self::CATEGORY_ROOTS[$type] ?? null;

        if ($rootCode === null) {
            throw new RuntimeException("CoA re-anchor: unknown account type '{$type}'.");
        }

        $existing = DB::table('chart_of_accounts')
            ->where('institute_id', $instituteId)
            ->whereNull('branch_id')
            ->where('code', $rootCode)
            ->first();

        if ($existing !== null) {
            return $this->rootIds[$instituteId][$type] = (int) $existing->id;
        }

        $global = DB::table('chart_of_accounts')
            ->whereNull('institute_id')
            ->where('code', $rootCode)
            ->where('is_header', 1)
            ->first();

        if ($global === null) {
            throw new RuntimeException("CoA re-anchor: no global category root with code '{$rootCode}' exists.");
        }

        $id = $this->insertHeader($global, $instituteId, null);
        $this->rootsCreated++;

        return $this->rootIds[$instituteId][$type] = $id;
    }

    /**
     * Clone a global header row into a tenant-owned header row (roots and
     * anchors share this). Raw insert: no Eloquent events, no generated
     * columns, is_system always 0.
     */
    private function insertHeader(object $global, int $instituteId, ?int $parentId): int
    {
        $now = now();

        $id = DB::table('chart_of_accounts')->insertGetId([
            'institute_id' => $instituteId,
            'branch_id' => null,
            'account_group_id' => $global->account_group_id,
            'parent_id' => $parentId,
            'code' => $global->code,
            'name' => $global->name,
            'type' => $global->type,
            'cash_flow_category' => null,
            'is_cash' => 0,
            'is_bank' => 0,
            'is_receivable' => 0,
            'is_payable' => 0,
            'is_active' => 1,
            'is_postable' => 0,
            'is_header' => 1,
            'is_system' => 0,
            'industries' => $global->industries,
            'currency_id' => null,
            'legacy_head_id' => null,
            'created_by' => null,
            'updated_by' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        if ($this->recordsCreated()) {
            DB::table(self::CREATED_TABLE)->insert(['coa_id' => $id]);
        }

        return $id;
    }

    /**
     * Rows created outside the migration (the onboarding seeder) must not be
     * recorded as "created by the re-anchor": a later rollback only undoes
     * what the migration itself wrote. Recording requires the table; it is
     * never created here (that would be DDL inside the provisioning
     * transaction).
     */
    private function recordsCreated(): bool
    {
        if ($this->createdTableAvailable === null) {
            $this->createdTableAvailable = Schema::hasTable(self::CREATED_TABLE);
        }

        return $this->createdTableAvailable;
    }
}

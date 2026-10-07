<?php

namespace Tests\Feature\Accounting;

use App\Models\Institute;
use App\Services\Accounting\CoaReanchorService;
use App\Support\BranchContext;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * F-014 / TASK C2 — CoaReanchorService group methods.
 *
 * Fixture: MAWA ACADEMY (post-C1 it holds 0 tenant CoA rows and 0 tenant
 * account groups) gets the pre-C2 shape back — 5 tenant CoA rows pointing
 * at the shared global account groups (codes 1-5) — then the service is
 * expected to clone the groups for the tenant and re-point the rows.
 *
 * Precondition: the reanchor_account_groups_per_tenant migration must have
 * run against the test database first (php artisan migrate with
 * APP_ENV=testing). The tests never create DDL — the backup tables must
 * exist, and MySQL's implicit commit on DDL would break
 * DatabaseTransactions.
 */
class CoaGroupReanchorTest extends TestCase
{
    use DatabaseTransactions;

    private CoaReanchorService $service;

    protected function setUp(): void
    {
        parent::setUp();

        TenantContext::clear();
        BranchContext::clear();

        if (! Schema::hasTable(CoaReanchorService::GROUP_BACKUP_TABLE) || ! Schema::hasTable(CoaReanchorService::GROUP_CREATED_TABLE)) {
            $this->fail('coa_group_reanchor backup tables are missing — run php artisan migrate on the test database first.');
        }

        $this->service = app(CoaReanchorService::class);
    }

    // ------------------------------------------------------------ fixtures

    private function mawa(): Institute
    {
        return Institute::where('name', 'MAWA ACADEMY')->firstOrFail();
    }

    private function globalGroupId(string $type): int
    {
        $id = DB::table('account_groups')
            ->whereNull('institute_id')
            ->where('is_system', 1)
            ->where('category', $type)
            ->value('id');

        $this->assertNotNull($id, "Global account group for {$type} must be seeded.");

        return (int) $id;
    }

    /**
     * Pre-C2 shape: one tenant CoA row per account category, every row
     * pointing at the shared global group (the F-014 condition).
     *
     * @return array<int, int> row id by code
     */
    private function preC2Rows(int $instituteId): array
    {
        $plan = [
            ['8201', 'Mawa Cash', 'asset'],
            ['8202', 'Mawa Loan', 'liability'],
            ['8203', 'Mawa Capital', 'equity'],
            ['8204', 'Mawa Sales', 'income'],
            ['8205', 'Mawa Purchases', 'expense'],
        ];

        $rowIds = [];

        foreach ($plan as [$code, $name, $type]) {
            $rowIds[$code] = DB::table('chart_of_accounts')->insertGetId([
                'institute_id' => $instituteId,
                'branch_id' => null,
                'account_group_id' => $this->globalGroupId($type),
                'parent_id' => null,
                'code' => $code,
                'name' => $name,
                'type' => $type,
                'is_active' => 1,
                'is_postable' => 1,
                'is_header' => 0,
                'is_system' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return $rowIds;
    }

    private function tenantGroups(int $instituteId)
    {
        return DB::table('account_groups')
            ->where('institute_id', $instituteId)
            ->orderBy('code')
            ->get();
    }

    private function globalGroupsSnapshot()
    {
        return DB::table('account_groups')
            ->whereNull('institute_id')
            ->orderBy('id')
            ->get(['id', 'code', 'name', 'category']);
    }

    // ------------------------------------------------------------ tests

    public function test_tenant_gets_own_account_groups(): void
    {
        $institute = $this->mawa();
        $this->preC2Rows($institute->id);

        $stats = $this->service->reanchorGroupsForInstitute($institute->id);

        $this->assertSame(5, $stats['groups_created'], 'One clone per global group');
        $this->assertSame(5, $stats['coa_rows_repointed'], 'One row per category');

        $groups = $this->tenantGroups($institute->id);

        $this->assertCount(5, $groups, 'The tenant must own all 5 groups.');
        $this->assertSame(['1', '2', '3', '4', '5'], $groups->pluck('code')->all());

        foreach ($groups as $clone) {
            $global = DB::table('account_groups')
                ->whereNull('institute_id')
                ->where('code', $clone->code)
                ->first();

            $this->assertNotNull($global, "Global source group {$clone->code} must exist.");
            $this->assertSame($institute->id, (int) $clone->institute_id, 'Clone must belong to the tenant.');
            $this->assertSame($global->name, $clone->name, 'Clone keeps the global name.');
            $this->assertSame($global->category, $clone->category, 'Clone keeps the global category.');
            $this->assertSame(0, (int) $clone->is_system, 'Tenant clones must never be is_system.');
            $this->assertNull($clone->branch_id, 'Groups are institute-wide.');
        }
    }

    public function test_tenant_coa_rows_point_to_tenant_groups(): void
    {
        $institute = $this->mawa();
        $rowIds = $this->preC2Rows($institute->id);

        $this->service->reanchorGroupsForInstitute($institute->id);

        foreach ($rowIds as $code => $rowId) {
            $row = DB::table('chart_of_accounts')->find($rowId);
            $group = DB::table('account_groups')->find($row->account_group_id);

            $this->assertNotNull($group, "Row {$code} must still resolve to a group.");
            $this->assertSame(
                $institute->id,
                (int) $group->institute_id,
                "Row {$code} must reference a group owned by the same tenant."
            );
        }

        $stillGlobal = DB::table('chart_of_accounts as a')
            ->join('account_groups as g', 'g.id', '=', 'a.account_group_id')
            ->where('a.institute_id', $institute->id)
            ->whereNull('g.institute_id')
            ->count();

        $this->assertSame(0, $stillGlobal, 'No tenant row may reference a shared global group (F-014).');
    }

    public function test_global_groups_unchanged(): void
    {
        $before = $this->globalGroupsSnapshot();

        $this->assertCount(5, $before, 'The 5 global groups are the source of truth.');

        $institute = $this->mawa();
        $this->preC2Rows($institute->id);
        $this->service->reanchorGroupsForInstitute($institute->id);

        $after = $this->globalGroupsSnapshot();

        $this->assertSame(
            $before->toJson(),
            $after->toJson(),
            'Global group rows must keep their ids, codes, names and count.'
        );
    }

    public function test_reanchor_groups_is_idempotent(): void
    {
        $institute = $this->mawa();
        $this->preC2Rows($institute->id);

        $first = $this->service->reanchorGroupsForInstitute($institute->id);

        $this->assertSame(5, $first['groups_created']);
        $this->assertSame(5, $first['coa_rows_repointed']);

        $groupsAfterFirst = $this->tenantGroups($institute->id)->pluck('id')->all();
        $rowsAfterFirst = DB::table('chart_of_accounts')
            ->where('institute_id', $institute->id)
            ->orderBy('id')
            ->pluck('account_group_id', 'id')
            ->all();

        // Fresh instance: defeats any in-memory state, so idempotency must
        // come from the data (existence checks), not from memoisation.
        $second = app(CoaReanchorService::class)->reanchorGroupsForInstitute($institute->id);

        $this->assertSame(0, $second['groups_created'], 'Second run must not create groups.');
        $this->assertSame(0, $second['coa_rows_repointed'], 'Second run must not re-point rows.');

        $this->assertSame(
            $groupsAfterFirst,
            $this->tenantGroups($institute->id)->pluck('id')->all(),
            'A second run must neither add nor reorder tenant groups.'
        );

        $this->assertSame(
            $rowsAfterFirst,
            DB::table('chart_of_accounts')
                ->where('institute_id', $institute->id)
                ->orderBy('id')
                ->pluck('account_group_id', 'id')
                ->all(),
            'A second run must leave account_group_id untouched.'
        );
    }

    public function test_restore_groups_undoes_reanchor(): void
    {
        $institute = $this->mawa();
        $rowIds = $this->preC2Rows($institute->id);

        $originalGroups = [];
        foreach ($rowIds as $rowId) {
            $originalGroups[$rowId] = (int) DB::table('chart_of_accounts')->find($rowId)->account_group_id;
        }

        $this->service->reanchorGroupsForInstitute($institute->id);

        foreach ($originalGroups as $rowId => $groupId) {
            $this->assertNotSame(
                $groupId,
                (int) DB::table('chart_of_accounts')->find($rowId)->account_group_id,
                'Re-anchor must move the row off its original global group first.'
            );
        }

        // Fresh instance, exactly what the migration's down() does (minus
        // the table drop — DDL stays out of the test transaction).
        app(CoaReanchorService::class)->restoreGroups();

        foreach ($originalGroups as $rowId => $groupId) {
            $this->assertSame(
                $groupId,
                (int) DB::table('chart_of_accounts')->find($rowId)->account_group_id,
                "Row {$rowId} must be back on its original global group."
            );
        }

        $this->assertSame(
            0,
            $this->tenantGroups($institute->id)->count(),
            'Groups created by the re-anchor must be deleted.'
        );
    }
}

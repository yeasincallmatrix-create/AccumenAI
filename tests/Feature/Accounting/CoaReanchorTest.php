<?php

namespace Tests\Feature\Accounting;

use App\Models\AccountGroup;
use App\Models\Industry;
use App\Models\Institute;
use App\Services\Accounting\CoaReanchorService;
use App\Support\BranchContext;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * F-005 / TASK C1 — CoaReanchorService.
 *
 * The fixture rebuilds the pre-C1 state for a fresh institute: one leaf per
 * account type parented to the shared global anchors, plus the institute-189
 * anomaly (leaf 4002 parented straight to the global Income category root).
 *
 * Precondition: the coa_reanchor migration must have run against the test
 * database first (php artisan migrate with APP_ENV=testing). The tests never
 * create DDL — snapshot()/restore() depend on the backup tables, and MySQL's
 * implicit commit on DDL would break DatabaseTransactions.
 */
class CoaReanchorTest extends TestCase
{
    use DatabaseTransactions;

    private CoaReanchorService $service;

    protected function setUp(): void
    {
        parent::setUp();

        TenantContext::clear();
        BranchContext::clear();

        if (! Schema::hasTable(CoaReanchorService::BACKUP_TABLE) || ! Schema::hasTable(CoaReanchorService::CREATED_TABLE)) {
            $this->fail('coa_reanchor backup tables are missing — run php artisan migrate on the test database first.');
        }

        $this->service = app(CoaReanchorService::class);
    }

    // ------------------------------------------------------------ fixtures

    private function tenant(): Institute
    {
        $industry = Industry::where('slug', 'education')->firstOrFail();

        return Institute::create([
            'name' => 'Reanchor '.Str::random(8),
            'slug' => 'reanchor-'.Str::lower(Str::random(8)),
            'status' => 'active',
            'country' => 'Bangladesh',
            'industry' => 'education',
            'industry_id' => $industry->id,
        ]);
    }

    private function globalHeader(string $code): object
    {
        $row = DB::table('chart_of_accounts')
            ->whereNull('institute_id')
            ->where('code', $code)
            ->where('is_header', 1)
            ->first();

        $this->assertNotNull($row, "Global template header {$code} must be seeded in the test database.");

        return $row;
    }

    private function globalGroupId(string $type): int
    {
        $id = AccountGroup::withoutGlobalScope('institute')
            ->whereNull('institute_id')
            ->where('is_system', 1)
            ->where('category', $type)
            ->value('id');

        $this->assertNotNull($id, "Global account group for {$type} must be seeded.");

        return (int) $id;
    }

    private function leaf(int $instituteId, string $code, string $name, string $type, int $parentId): int
    {
        return DB::table('chart_of_accounts')->insertGetId([
            'institute_id' => $instituteId,
            'branch_id' => null,
            'account_group_id' => $this->globalGroupId($type),
            'parent_id' => $parentId,
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

    /**
     * Fresh institute in the pre-C1 shape: one leaf per type under its global
     * anchor, plus the 4002 anomaly under the global Income root (code 4).
     *
     * @return array{0: Institute, 1: array<string, int>} leaf id by code
     */
    private function preC1Tenant(): array
    {
        $institute = $this->tenant();
        $leafIds = [];

        $plan = [
            ['1000.91', 'Reanchor Cash', 'asset', '1000'],
            ['2000.91', 'Reanchor Payable', 'liability', '2000'],
            ['3100.91', 'Reanchor Capital', 'equity', '3100'],
            ['4000.91', 'Reanchor Sales', 'income', '4000'],
            ['5000.91', 'Reanchor Purchases', 'expense', '5000'],
        ];

        foreach ($plan as [$code, $name, $type, $anchorCode]) {
            $anchor = $this->globalHeader($anchorCode);
            $leafIds[$code] = $this->leaf($institute->id, $code, $name, $type, (int) $anchor->id);
        }

        $rootIncome = $this->globalHeader('4');
        $leafIds['4002'] = $this->leaf($institute->id, '4002', 'Other Income (anomaly)', 'income', (int) $rootIncome->id);

        return [$institute, $leafIds];
    }

    private function tenantRow(int $instituteId, string $code): ?object
    {
        return DB::table('chart_of_accounts')
            ->where('institute_id', $instituteId)
            ->whereNull('branch_id')
            ->where('code', $code)
            ->first();
    }

    private function crossTenantLinks(int $instituteId): int
    {
        return DB::table('chart_of_accounts as c')
            ->join('chart_of_accounts as p', 'p.id', '=', 'c.parent_id')
            ->where('c.institute_id', $instituteId)
            ->whereNull('p.institute_id')
            ->count();
    }

    // ------------------------------------------------------------ tests

    public function test_category_roots_are_created_owned_by_the_tenant(): void
    {
        [$institute] = $this->preC1Tenant();

        $globalsBefore = DB::table('chart_of_accounts')
            ->whereNull('institute_id')
            ->orderBy('id')
            ->pluck('id');

        $stats = $this->service->reanchorInstitute($institute->id);

        $this->assertSame(5, $stats['roots_created'], 'One category root per account type');

        foreach (CoaReanchorService::CATEGORY_ROOTS as $type => $code) {
            $root = $this->tenantRow($institute->id, $code);

            $this->assertNotNull($root, "Tenant category root {$code} must exist.");
            $this->assertSame($institute->id, (int) $root->institute_id, 'Root must belong to the tenant.');
            $this->assertNull($root->parent_id, "Root {$code} must sit at level 1.");
            $this->assertSame(1, (int) $root->is_header);
            $this->assertSame(0, (int) $root->is_postable);
            $this->assertSame(0, (int) $root->is_system, 'Tenant rows must never be is_system.');
            $this->assertSame($type, $root->type);
        }

        $globalsAfter = DB::table('chart_of_accounts')
            ->whereNull('institute_id')
            ->orderBy('id')
            ->pluck('id');

        $this->assertSame(
            $globalsBefore->toArray(),
            $globalsAfter->toArray(),
            'Global template rows must keep their ids and count (D4).'
        );
    }

    public function test_anchors_are_created_under_tenant_roots(): void
    {
        [$institute] = $this->preC1Tenant();

        $stats = $this->service->reanchorInstitute($institute->id);

        $this->assertSame(5, $stats['anchors_created'], 'One anchor per used template anchor (4002 shares the 4000 anchor)');

        $map = ['1000' => '1', '2000' => '2', '3100' => '3', '4000' => '4', '5000' => '5'];

        foreach ($map as $anchorCode => $rootCode) {
            $anchor = $this->tenantRow($institute->id, $anchorCode);
            $root = $this->tenantRow($institute->id, $rootCode);

            $this->assertNotNull($anchor, "Tenant anchor {$anchorCode} must exist.");
            $this->assertSame($institute->id, (int) $anchor->institute_id, 'Anchor must belong to the tenant.');
            $this->assertSame((int) $root->id, (int) $anchor->parent_id, "Anchor {$anchorCode} must sit under the tenant root.");
            $this->assertSame(1, (int) $anchor->is_header);
            $this->assertSame(0, (int) $anchor->is_postable);
            $this->assertSame(0, (int) $anchor->is_system);
            $this->assertSame($root->type, $anchor->type, 'Anchor type must match its root.');
        }
    }

    public function test_leaves_are_repointed_to_tenant_anchors_including_the_4002_anomaly(): void
    {
        [$institute, $leafIds] = $this->preC1Tenant();

        $stats = $this->service->reanchorInstitute($institute->id);

        $this->assertSame(6, $stats['cross_tenant_leaves']);
        $this->assertSame(6, $stats['leaves_repointed']);
        $this->assertSame(1, $stats['anomalies_fixed'], 'Leaf 4002 parented to the global Income root is the anomaly (D2)');
        $this->assertSame(0, $this->crossTenantLinks($institute->id), 'No tenant row may remain linked to the global template.');

        $expectedParents = [
            '1000.91' => '1000',
            '2000.91' => '2000',
            '3100.91' => '3100',
            '4000.91' => '4000',
            '5000.91' => '5000',
            '4002' => '4000', // D2: anomaly moves under the tenant-owned 4000 anchor
        ];

        foreach ($expectedParents as $leafCode => $anchorCode) {
            $leaf = DB::table('chart_of_accounts')->find($leafIds[$leafCode]);
            $anchor = $this->tenantRow($institute->id, $anchorCode);

            $this->assertNotNull($leaf, "Leaf {$leafCode} must still exist (no row deleted, D4).");
            $this->assertSame((int) $anchor->id, (int) $leaf->parent_id, "Leaf {$leafCode} must hang off the tenant anchor.");
        }

        $this->assertSame(
            5,
            (int) DB::table('chart_of_accounts')->where('institute_id', $institute->id)->whereNull('parent_id')->count(),
            'Only the 5 category roots are level 1 — every leaf sits under an anchor.'
        );

        $this->assertSame(
            6,
            (int) DB::table('chart_of_accounts')->where('institute_id', $institute->id)->whereIn('id', array_values($leafIds))->count(),
            'Every pre-existing leaf id kept its row (D4: nothing deleted, nothing re-keyed).'
        );
    }

    public function test_second_run_is_a_no_op(): void
    {
        [$institute] = $this->preC1Tenant();

        $first = $this->service->reanchorInstitute($institute->id);
        $this->assertSame(6, $first['leaves_repointed']);

        $rowsAfterFirst = DB::table('chart_of_accounts')
            ->where('institute_id', $institute->id)
            ->orderBy('id')
            ->pluck('id', 'code');

        // Fresh instance: defeats the in-memory memo, so idempotency must
        // come from the data (existence checks), not from state.
        $second = app(CoaReanchorService::class)->reanchorInstitute($institute->id);

        $this->assertSame(0, $second['cross_tenant_leaves']);
        $this->assertSame(0, $second['roots_created']);
        $this->assertSame(0, $second['anchors_created']);
        $this->assertSame(0, $second['leaves_repointed']);
        $this->assertSame(0, $second['anomalies_fixed']);

        $rowsAfterSecond = DB::table('chart_of_accounts')
            ->where('institute_id', $institute->id)
            ->orderBy('id')
            ->pluck('id', 'code');

        $this->assertSame(
            $rowsAfterFirst->toArray(),
            $rowsAfterSecond->toArray(),
            'A second run must neither add nor change rows.'
        );
    }

    public function test_restore_puts_parents_back_and_removes_created_rows(): void
    {
        [$institute, $leafIds] = $this->preC1Tenant();

        $originalParents = [];
        foreach ($leafIds as $code => $leafId) {
            $originalParents[$code] = (int) DB::table('chart_of_accounts')->where('id', $leafId)->value('parent_id');
        }
        $rowsBefore = (int) DB::table('chart_of_accounts')->where('institute_id', $institute->id)->count();

        $this->service->reanchorInstitute($institute->id);

        $this->assertGreaterThan(
            $rowsBefore,
            (int) DB::table('chart_of_accounts')->where('institute_id', $institute->id)->count(),
            'Re-anchoring must add the roots and anchors before restore undoes them.'
        );

        // Fresh instance, exactly what the migration's down() does (minus the
        // table drop — DDL stays out of the test transaction).
        app(CoaReanchorService::class)->restore();

        foreach ($originalParents as $code => $parentId) {
            $restored = (int) DB::table('chart_of_accounts')->where('id', $leafIds[$code])->value('parent_id');
            $this->assertSame($parentId, $restored, "Leaf {$code} must be back on its original global parent.");
        }

        $this->assertSame(
            $rowsBefore,
            (int) DB::table('chart_of_accounts')->where('institute_id', $institute->id)->count(),
            'Roots and anchors created by the re-anchor must be deleted.'
        );

        $this->assertSame($rowsBefore, $this->crossTenantLinks($institute->id), 'Pre-C1 cross-tenant links are back.');
    }
}

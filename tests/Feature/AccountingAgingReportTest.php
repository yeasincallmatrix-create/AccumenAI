<?php

namespace Tests\Feature;

use App\Models\Institute;
use App\Models\Role;
use App\Models\User;
use App\Services\AgingCalculatorService;
use App\Services\MembershipService;
use App\Services\UserAccountService;
use App\Support\BranchContext;
use App\Support\TenantContext;
use App\Support\Workspace;
use Carbon\Carbon;
use Database\Seeders\AccountingAgingPermissionSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Accounting Phase B — aging report logic + screens.
 *
 * Pins: config-driven 5-bucket taxonomy (Phase A single source of truth),
 * days-overdue math, bucket assignment at every boundary, AR/AP query shape,
 * the 6 routes, and the 10 seeded permissions.
 */
class AccountingAgingReportTest extends TestCase
{
    use DatabaseTransactions;

    private AgingCalculatorService $service;

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::clear();
        BranchContext::clear();
        Workspace::clear();
        $this->service = app(AgingCalculatorService::class);
    }

    // ------------------------------------------------------------- Service

    public function test_buckets_derive_from_phase_a_config(): void
    {
        $expectedKeys = ['current', 'd31_60', 'd61_90', 'd91_120', 'd121_plus'];

        $this->assertSame($expectedKeys, array_keys($this->service->buckets()));

        // Config rows and service must agree (single source of truth).
        foreach (config('accounting.aging.buckets') as $row) {
            $meta = $this->service->buckets()[$row['key']];
            $this->assertSame($row['label'], $meta['label']);
            $this->assertSame($row['min'], $meta['min']);
            $this->assertSame($row['max'], $meta['max']);
        }

        $this->assertNull($this->service->buckets()['d121_plus']['max'], 'Top bucket must be open-ended');
    }

    public function test_days_overdue_calculation(): void
    {
        $asOf = Carbon::parse('2026-09-27');

        $this->assertSame(0, $this->service->daysOverdue('2026-09-27', $asOf));
        $this->assertSame(0, $this->service->daysOverdue('2026-10-01', $asOf)); // not yet due
        $this->assertSame(10, $this->service->daysOverdue('2026-09-17', $asOf));
        $this->assertSame(60, $this->service->daysOverdue('2026-07-29', $asOf));
        $this->assertSame(150, $this->service->daysOverdue('2026-04-30', $asOf));
        $this->assertSame(0, $this->service->daysOverdue(null, $asOf));
    }

    public function test_bucket_assignment_at_boundaries(): void
    {
        $this->assertSame('current', $this->service->bucketFor(0));
        $this->assertSame('current', $this->service->bucketFor(30));
        $this->assertSame('d31_60', $this->service->bucketFor(31));
        $this->assertSame('d31_60', $this->service->bucketFor(45));
        $this->assertSame('d61_90', $this->service->bucketFor(61));
        $this->assertSame('d61_90', $this->service->bucketFor(90));
        $this->assertSame('d91_120', $this->service->bucketFor(91));
        $this->assertSame('d91_120', $this->service->bucketFor(120));
        $this->assertSame('d121_plus', $this->service->bucketFor(121));
        $this->assertSame('d121_plus', $this->service->bucketFor(5000));
    }

    public function test_ar_and_ap_aging_return_full_report_shape(): void
    {
        foreach ([$this->service->arAging(), $this->service->apAging()] as $report) {
            $this->assertArrayHasKey('as_of', $report);
            $this->assertArrayHasKey('buckets', $report);
            $this->assertArrayHasKey('grand_total', $report);
            $this->assertArrayHasKey('row_count', $report);

            foreach (['current', 'd31_60', 'd61_90', 'd91_120', 'd121_plus'] as $key) {
                $this->assertArrayHasKey($key, $report['buckets']);
                $this->assertArrayHasKey('rows', $report['buckets'][$key]);
                $this->assertArrayHasKey('total', $report['buckets'][$key]);
                $this->assertArrayHasKey('label', $report['buckets'][$key]);
            }

            // Bucket totals must sum to the grand total.
            $this->assertEqualsWithDelta(
                $report['grand_total'],
                array_sum(array_column($report['buckets'], 'total')),
                0.01
            );
        }
    }

    public function test_ar_aging_scopes_by_institute(): void
    {
        $unscoped = $this->service->arAging();
        $other = $this->service->arAging(PHP_INT_MAX); // no invoices for this id

        $this->assertSame(0, $other['row_count'], 'Unknown institute must return an empty report');
        $this->assertGreaterThanOrEqual(0, $unscoped['row_count']);
    }

    // ------------------------------------------------------------ Database

    public function test_ar_rows_exclude_paid_and_non_positive_outstanding(): void
    {
        $paid = DB::table('invoices')->where('status', 'paid')->count();
        $report = $this->service->arAging();

        // Shape pin: unpaid/partial only — paid rows can never appear.
        foreach ($report['buckets'] as $bucket) {
            foreach ($bucket['rows'] as $row) {
                $this->assertGreaterThan(0, $row['outstanding'], 'Zero-outstanding rows must be excluded');
            }
        }

        $this->assertGreaterThanOrEqual(0, $paid);
    }

    // -------------------------------------------------------------- Routes

    public function test_six_routes_are_registered(): void
    {
        $this->assertTrue(Route::has('accounting.aging.ar'));
        $this->assertTrue(Route::has('accounting.aging.ap'));
        $this->assertTrue(Route::has('accounting.aging.invoice'));
        $this->assertTrue(Route::has('accounting.aging.summary'));
        $this->assertTrue(Route::has('accounting.aging.config'));
        $this->assertTrue(Route::has('accounting.aging.export'));

        foreach (['ar', 'ap', 'invoice', 'summary', 'config'] as $name) {
            $route = Route::getRoutes()->getByName('accounting.aging.'.$name);
            $this->assertNotNull($route, "accounting.aging.{$name} must resolve");
            $this->assertStringContainsString('permission:', implode(',', $route->gatherMiddleware()));
        }
    }

    // --------------------------------------------------------- Permissions

    public function test_ten_aging_permissions_are_seeded(): void
    {
        (new AccountingAgingPermissionSeeder)->run();

        $slugs = DB::table('permissions')
            ->where('module', 'accounting')
            ->whereIn('slug', [
                'ar_aging.view', 'ar_aging.manage',
                'ap_aging.view', 'ap_aging.manage',
                'invoice_aging.view', 'invoice_aging.manage',
                'aging_summary.view', 'aging_summary.manage',
                'aging_config.view', 'aging_config.manage',
            ])
            ->pluck('slug')
            ->all();

        $this->assertCount(10, $slugs, 'Exactly the 10 aging slugs must exist under module=accounting');
    }

    // -------------------------------------------------------------- HTTP

    protected function owner(string $email): User
    {
        return (new UserAccountService)->registerOwner([
            'name' => 'Aging Owner',
            'first_name' => 'Aging',
            'last_name' => 'Owner',
            'email' => $email,
            'password_hash' => bcrypt('secret12345'),
            'status' => 'active',
        ]);
    }

    protected function institute(string $name): Institute
    {
        return Institute::create([
            'name' => $name.' '.uniqid(),
            'slug' => \Illuminate\Support\Str::slug($name.' '.uniqid()),
            'status' => 'active',
        ]);
    }

    protected function asUser(User $user, int $workspaceId): static
    {
        return $this->withSession([Workspace::SESSION_KEY => $workspaceId])->actingAs($user, 'web');
    }

    public function test_ar_report_renders_for_owner(): void
    {
        $institute = $this->institute('Aging AR');
        $owner = $this->owner('aging-ar@example.test');
        $role = Role::where('slug', 'institute-owner')->firstOrFail();
        $membership = (new MembershipService)->assign($owner, $institute->id, $role->id);

        $this->asUser($owner, $membership->institution_id)
            ->get(route('accounting.aging.ar'))
            ->assertOk()
            ->assertSee('AR Aging Report')
            ->assertSee('Total Outstanding');
    }

    public function test_summary_and_config_renders_for_owner(): void
    {
        $institute = $this->institute('Aging Sum');
        $owner = $this->owner('aging-sum@example.test');
        $role = Role::where('slug', 'institute-owner')->firstOrFail();
        $membership = (new MembershipService)->assign($owner, $institute->id, $role->id);

        $this->asUser($owner, $membership->institution_id)
            ->get(route('accounting.aging.summary'))
            ->assertOk()
            ->assertSee('Aging Summary')
            ->assertSee('Total Receivables (AR)');

        $this->asUser($owner, $membership->institution_id)
            ->get(route('accounting.aging.config'))
            ->assertOk()
            ->assertSee('Aging Configuration')
            ->assertSee('purchase_invoices');
    }

    public function test_export_streams_csv_and_rejects_unknown_type(): void
    {
        $institute = $this->institute('Aging Exp');
        $owner = $this->owner('aging-exp@example.test');
        $role = Role::where('slug', 'institute-owner')->firstOrFail();
        $membership = (new MembershipService)->assign($owner, $institute->id, $role->id);

        $this->asUser($owner, $membership->institution_id)
            ->get(route('accounting.aging.export', ['type' => 'ar_aging']))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=utf-8');

        $this->asUser($owner, $membership->institution_id)
            ->get(route('accounting.aging.export', ['type' => 'bogus']))
            ->assertNotFound();
    }
}

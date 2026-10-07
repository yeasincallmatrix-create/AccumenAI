<?php

namespace Tests\Feature\Accounting;

use App\Http\Controllers\Auth\RegistrationFlowController;
use App\Models\Industry;
use App\Models\Institute;
use App\Models\PendingRegistration;
use App\Services\Accounting\AccountingPeriodService;
use App\Services\Accounting\TenantCoaSeederService;
use App\Support\BranchContext;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * F-002 / N-8 — onboarding provisioning (seedFullProvisioning) + coa:backfill.
 *
 * CoA assertions use the 72 lower bound, not the 83 template total: the
 * industry filter in TenantCoaSeederService::seedForTenant() caps reachable
 * leaves per industry (education 76, training_center/retail 74, healthcare 72).
 */
class OnboardingProvisioningTest extends TestCase
{
    use DatabaseTransactions;

    protected function fixtureInstitute(string $industry = 'healthcare'): Institute
    {
        $model = Industry::where('slug', $industry)->firstOrFail();

        return Institute::create([
            'name' => 'Provision '.Str::random(8),
            'slug' => 'provision-'.Str::lower(Str::random(8)),
            'status' => 'active',
            'country' => 'Bangladesh',
            'industry' => $industry,
            'industry_id' => $model->id,
        ]);
    }

    /**
     * Raw table counts: immune to TenantContext/branch scopes that an HTTP
     * step (Workspace::set) may have left enabled in this process.
     *
     * @return array{coa: int, fy: int, periods: int, pm: int, settings: int}
     */
    protected function counts(int $instituteId): array
    {
        $count = fn (string $table): int => DB::table($table)
            ->where('institute_id', $instituteId)
            ->count();

        return [
            'coa' => $count('chart_of_accounts'),
            'fy' => $count('fiscal_years'),
            'periods' => $count('accounting_periods'),
            'pm' => $count('payment_methods'),
            'settings' => $count('accounting_settings'),
        ];
    }

    public function test_new_institute_registration_provisions_full_stack(): void
    {
        $email = 'provision-'.Str::random(8).'@example.test';

        $pending = PendingRegistration::create([
            'email' => $email,
            'password_hash' => Hash::make('Secret123!'),
            'otp_hash' => Hash::make('123456'),
            'otp_expires_at' => now()->addMinutes(10),
            'expires_at' => now()->addHours(24),
        ]);
        $pending->update([
            'verified_at' => now(),
            'organization_data' => [
                'country' => 'Bangladesh',
                'industry' => 'education',
                'sub_industry' => 'school',
                'organization_name' => 'Provision Org',
                'first_name' => 'Pro',
                'last_name' => 'Vision',
                'phone' => '017'.rand(10000000, 99999999),
            ],
        ]);

        $this->withSession([
            RegistrationFlowController::PENDING_ID => $pending->id,
            RegistrationFlowController::SESSION_KEY => ['email' => $email, 'verified' => true],
        ])
            ->post('/register/address', ['address' => 'Dhaka'])
            ->assertRedirect(route('register.package'));

        // Workspace::set() inside the request pins the tenant scope.
        TenantContext::clear();
        BranchContext::clear();

        $institute = Institute::where('name', 'Provision Org')->firstOrFail();
        $counts = $this->counts($institute->id);

        $this->assertGreaterThanOrEqual(72, $counts['coa'], 'CoA children must be seeded (industry floor 72)');
        $this->assertSame(1, $counts['fy'], 'Exactly one current fiscal year');
        $this->assertSame(12, $counts['periods'], 'Twelve monthly accounting periods');
        $this->assertGreaterThanOrEqual(4, $counts['pm'], 'Default payment methods');
        $this->assertGreaterThanOrEqual(10, $counts['settings'], 'Default accounting settings');
    }

    public function test_partial_failure_rolls_back_entire_provisioning(): void
    {
        $institute = $this->fixtureInstitute('healthcare');
        $id = $institute->id;

        $this->partialMock(AccountingPeriodService::class, function ($mock): void {
            $mock->shouldReceive('createMonthlyPeriods')
                ->once()
                ->andThrow(new \RuntimeException('period generation failed'));
        });

        $caught = null;
        try {
            app(TenantCoaSeederService::class)->seedFullProvisioning($id);
        } catch (\Throwable $e) {
            $caught = $e;
        }

        $this->assertNotNull($caught, 'seedFullProvisioning must propagate the failure');
        $this->assertSame('period generation failed', $caught->getMessage());

        $counts = $this->counts($id);
        $this->assertSame(0, $counts['coa'], 'CoA children must roll back');
        $this->assertSame(0, $counts['fy'], 'Fiscal year must roll back');
        $this->assertSame(0, $counts['periods'], 'Periods must roll back');
        $this->assertSame(0, $counts['pm'], 'Payment methods must roll back');
        $this->assertSame(0, $counts['settings'], 'Settings must roll back');
    }

    public function test_backfill_command_is_idempotent(): void
    {
        $institute = $this->fixtureInstitute('training_center');
        $id = $institute->id;

        $this->artisan('coa:backfill', ['--institute' => $id])->assertExitCode(0);
        $first = $this->counts($id);

        $this->assertGreaterThanOrEqual(72, $first['coa']);
        $this->assertSame(1, $first['fy']);
        $this->assertSame(12, $first['periods']);
        $this->assertSame(4, $first['pm']);
        $this->assertGreaterThanOrEqual(10, $first['settings']);

        $this->artisan('coa:backfill', ['--institute' => $id])->assertExitCode(0);
        $second = $this->counts($id);

        $this->assertSame($first, $second, 'Second run must be a no-op');
    }

    public function test_backfill_does_not_touch_unrelated_institutes(): void
    {
        $primary = $this->fixtureInstitute('retail');
        $otherA = $this->fixtureInstitute('healthcare');
        $otherB = $this->fixtureInstitute('education');

        $beforeA = $this->counts($otherA->id);
        $beforeB = $this->counts($otherB->id);

        $this->assertSame(0, $beforeA['fy']);
        $this->assertSame(0, $beforeB['fy']);

        $this->artisan('coa:backfill', ['--institute' => $primary->id])->assertExitCode(0);

        $this->assertSame($beforeA, $this->counts($otherA->id), 'Unrelated institute A must stay untouched');
        $this->assertSame($beforeB, $this->counts($otherB->id), 'Unrelated institute B must stay untouched');
        $this->assertGreaterThanOrEqual(72, $this->counts($primary->id)['coa']);
    }
}

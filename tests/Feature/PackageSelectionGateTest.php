<?php

namespace Tests\Feature;

use App\Http\Controllers\Auth\RegistrationFlowController;
use App\Models\Institute;
use App\Models\PendingRegistration;
use App\Models\SubscriptionPackage;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * A new organization must pick a package during registration, and the
 * dashboard stays locked until it does.
 */
class PackageSelectionGateTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        // Organizations created by this test must start with NO package, which
        // is exactly what happens in production (auto-premium is a test aid).
        config(['testing.auto_premium' => false]);
    }

    protected function freePackageId(): int
    {
        return (int) SubscriptionPackage::query()
            ->whereRaw('LOWER(slug) = ?', ['free'])
            ->value('id');
    }

    /**
     * Drives the registration flow up to the point where the organization
     * exists and the owner is logged in, but no package is chosen yet.
     */
    protected function finishRegistration(string $email, string $industry = 'education', string $sub = 'school'): Institute
    {
        $pending = PendingRegistration::create([
            'email' => $email,
            'password_hash' => Hash::make('Secret123!'),
            'otp_hash' => Hash::make('123456'),
            'otp_expires_at' => now()->addMinutes(10),
            'expires_at' => now()->addHours(24),
        ]);
        $pending->update(['verified_at' => now()]);

        $session = [
            RegistrationFlowController::PENDING_ID => $pending->id,
            RegistrationFlowController::SESSION_KEY => ['email' => $pending->email, 'verified' => true, 'step' => 3],
        ];

        $this->withSession($session)
            ->post('/register/organization', [
                'organization_name' => 'Package Gate Org',
                'first_name' => 'Pkg',
                'last_name' => 'Gate',
                'phone' => '017'.substr(preg_replace('/\D/', '', md5($email)), 0, 8),
                'country' => 'Bangladesh',
                'industry' => $industry,
                'sub_industry' => $sub,
            ])
            ->assertRedirect(route('register.address'));

        $this->withSession($session)
            ->post('/register/address', ['address' => 'Dhaka'])
            ->assertRedirect(route('register.package'));

        return Institute::query()->where('name', 'Package Gate Org')->firstOrFail();
    }

    public function test_registration_lands_on_the_package_selection_page(): void
    {
        $institute = $this->finishRegistration('pkg-flow@example.test');

        $this->assertAuthenticated('web');
        $this->assertNull($institute->fresh()->package_id);

        $this->get(route('register.package'))
            ->assertOk()
            ->assertSee('name="package_id"', false)
            ->assertSee('Choose your package', false);
    }

    public function test_dashboard_stays_locked_until_a_package_is_selected(): void
    {
        $this->finishRegistration('pkg-lock@example.test');

        $this->get(route('dashboard'))->assertRedirect(route('register.package'));
        $this->get('/')->assertRedirect(route('register.package'));
        $this->get(route('academic-dashboard'))->assertRedirect(route('register.package'));

        // Tenant modules are locked too, not just the dashboard.
        $this->get(route('students.index'))->assertRedirect(route('register.package'));
    }

    public function test_selecting_a_package_unlocks_the_workspace(): void
    {
        $institute = $this->finishRegistration('pkg-open@example.test');

        $this->post(route('register.package.submit'), ['package_id' => $this->freePackageId()])
            ->assertRedirect(route('register.education.placeholder'));

        $this->assertNotNull($institute->fresh()->package_id);

        $this->get(route('dashboard'))->assertOk();

        $students = $this->get(route('students.index'));
        $this->assertNotSame(
            url(route('register.package')),
            (string) $students->headers->get('Location')
        );
    }

    public function test_package_selection_requires_a_package(): void
    {
        $this->finishRegistration('pkg-required@example.test');

        $this->post(route('register.package.submit'), [])
            ->assertSessionHasErrors('package_id');

        $this->post(route('register.package.submit'), ['package_id' => 99999999])
            ->assertSessionHasErrors('package_id');
    }

    public function test_guests_are_sent_to_login_instead_of_the_package_page(): void
    {
        $this->get(route('register.package'))->assertRedirect(route('login'));
        $this->post(route('register.package.submit'), ['package_id' => 1])->assertRedirect(route('login'));
    }
}

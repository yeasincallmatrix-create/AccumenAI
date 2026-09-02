<?php

namespace Tests\Feature;

use App\Models\Institute;
use App\Models\Membership;
use App\Models\PlatformAdmin;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class E27SuperAdminAllAccountsListTest extends TestCase
{
    use DatabaseTransactions;

    protected string $adminPass = 'SuperSecret123!';
    protected PlatformAdmin $admin;
    protected Role $ownerRole;
    protected Role $staffRole;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = PlatformAdmin::firstOrReuseForTests([
            'email' => 'e27list-admin-' . uniqid() . '@example.test',
            'password_hash' => bcrypt($this->adminPass),
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
        $this->ownerRole = Role::where('slug', 'institute-owner')->first()
            ?? Role::create(['name' => 'Institute Owner', 'slug' => 'institute-owner', 'is_system' => true]);
        $this->staffRole = Role::where('slug', 'institute-staff')->first()
            ?? Role::create(['name' => 'Institute Staff', 'slug' => 'institute-staff', 'is_system' => true]);
    }

    private function makeInstitute(): Institute
    {
        return Institute::create([
            'name' => 'E27L Inst ' . uniqid(),
            'slug' => 'e27l-' . uniqid(),
            'status' => 'active',
        ]);
    }

    private function makeUser(array $overrides = []): User
    {
        return User::create(array_merge([
            'name' => 'User ' . uniqid(),
            'email' => 'e27l-' . uniqid() . '@example.test',
            'phone' => '+8801' . str_pad((string) random_int(100000000, 999999999), 9, '0', STR_PAD_LEFT),
            'password_hash' => bcrypt('password'),
            'account_type' => 'owner',
            'status' => 'active',
            'email_verified_at' => now(),
        ], $overrides));
    }

    private function attach(User $u, Institute $i, ?Role $role = null): Membership
    {
        return Membership::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'user_id' => $u->id,
            'institution_id' => $i->id,
            'role_id' => ($role ?? $this->ownerRole)->id,
            'status' => 'active',
        ]);
    }

    // ── Access Control ──

    public function test_platform_admin_can_access_index(): void
    {
        $this->actingAs($this->admin, 'platform_admin');
        $this->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee('All Accounts')
            ->assertSee('Manage all global user accounts');
    }

    public function test_guest_is_blocked(): void
    {
        $this->get(route('admin.users.index'))->assertRedirect();
    }

    public function test_institute_user_is_blocked(): void
    {
        $inst = $this->makeInstitute();
        $iu = \App\Models\InstituteUser::create([
            'institute_id' => $inst->id,
            'role_id' => $this->ownerRole->id,
            'first_name' => 'I',
            'last_name' => 'U',
            'email' => 'iu-' . uniqid() . '@example.test',
            'phone' => '+8801' . str_pad((string) random_int(100000000, 999999999), 9, '0', STR_PAD_LEFT),
            'password_hash' => bcrypt('password'),
            'status' => 'active',
        ]);
        $this->actingAs($iu, 'institute_user');
        $this->get(route('admin.users.index'))->assertStatus(302);
    }

    public function test_unverified_admin_is_blocked(): void
    {
        $unv = PlatformAdmin::firstOrReuseForTests([
            'email' => 'unv-' . uniqid() . '@example.test',
            'password_hash' => bcrypt($this->adminPass),
            'status' => 'active',
            'email_verified_at' => null,
        ]);
        $this->actingAs($unv, 'platform_admin');
        $this->get(route('admin.users.index'))->assertRedirect(route('verification.notice'));
    }

    // ── User Listing ──

    public function test_users_are_returned_in_list(): void
    {
        $u = $this->makeUser();
        $this->actingAs($this->admin, 'platform_admin');
        $this->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee($u->name)
            ->assertSee($u->email);
    }

    public function test_user_with_no_business_appears(): void
    {
        $u = $this->makeUser();
        $this->actingAs($this->admin, 'platform_admin');
        $this->get(route('admin.users.index'))
            ->assertSee($u->name)
            ->assertSee('0 Businesses');
    }

    public function test_user_with_one_business_appears(): void
    {
        $u = $this->makeUser();
        $this->attach($u, $this->makeInstitute());
        $this->actingAs($this->admin, 'platform_admin');
        $this->get(route('admin.users.index'))
            ->assertSee($u->name)
            ->assertSee('1 Business');
    }

    public function test_user_with_multiple_businesses_appears(): void
    {
        $u = $this->makeUser();
        $this->attach($u, $this->makeInstitute());
        $this->attach($u, $this->makeInstitute());
        $this->attach($u, $this->makeInstitute());
        $this->actingAs($this->admin, 'platform_admin');
        $this->get(route('admin.users.index'))
            ->assertSee($u->name)
            ->assertSee('3 Businesses')
            ->assertSee('Multiple businesses');
    }

    // ── Soft-Deleted Users ──

    public function test_deleted_user_excluded_from_active_list(): void
    {
        $u = $this->makeUser();
        $this->actingAs($this->admin, 'platform_admin');
        $this->delete(route('admin.users.destroy', $u), ['password' => $this->adminPass]);
        $this->actingAs($this->admin, 'platform_admin');
        $this->get(route('admin.users.index'))
            ->assertOk()
            ->assertDontSee($u->email);
    }

    public function test_deleted_user_appears_in_bin(): void
    {
        $u = $this->makeUser();
        $this->actingAs($this->admin, 'platform_admin');
        $this->delete(route('admin.users.destroy', $u), ['password' => $this->adminPass]);
        $this->actingAs($this->admin, 'platform_admin');
        $this->get(route('admin.users.bin'))
            ->assertOk()
            ->assertSee($u->name)
            ->assertSee($u->email);
    }

    // ── Search ──

    public function test_search_by_name_works(): void
    {
        $u = $this->makeUser(['name' => 'Alice Searchable']);
        $other = $this->makeUser(['name' => 'Bob Other']);
        $this->actingAs($this->admin, 'platform_admin');
        $this->get(route('admin.users.index', ['q' => 'Alice']))
            ->assertSee('Alice Searchable')
            ->assertDontSee('Bob Other');
    }

    public function test_search_by_email_works(): void
    {
        $u = $this->makeUser();
        $this->actingAs($this->admin, 'platform_admin');
        $this->get(route('admin.users.index', ['q' => $u->email]))
            ->assertSee($u->email);
    }

    public function test_search_by_phone_works(): void
    {
        $u = $this->makeUser();
        $this->actingAs($this->admin, 'platform_admin');
        $this->get(route('admin.users.index', ['q' => substr($u->phone, -4)]))
            ->assertSee($u->name);
    }

    public function test_search_by_business_name_works(): void
    {
        $u = $this->makeUser();
        $inst = $this->makeInstitute();
        $inst->update(['name' => 'UniqueBusinessXYZ']);
        $this->attach($u, $inst);
        $this->actingAs($this->admin, 'platform_admin');
        $this->get(route('admin.users.index', ['q' => 'UniqueBusinessXYZ']))
            ->assertSee($u->name);
    }

    // ── Filters ──

    public function test_status_active_filter(): void
    {
        $active = $this->makeUser(['status' => 'active']);
        $banned = $this->makeUser(['status' => 'inactive']);
        $this->actingAs($this->admin, 'platform_admin');
        $this->get(route('admin.users.index', ['status' => 'active']))
            ->assertSee($active->name)
            ->assertDontSee($banned->name);
    }

    public function test_status_inactive_filter(): void
    {
        $active = $this->makeUser(['status' => 'active']);
        $banned = $this->makeUser(['status' => 'inactive']);
        $this->actingAs($this->admin, 'platform_admin');
        $this->get(route('admin.users.index', ['status' => 'inactive']))
            ->assertSee($banned->name)
            ->assertDontSee($active->name);
    }

    public function test_verification_filter(): void
    {
        $v = $this->makeUser(['email_verified_at' => now()]);
        $uv = $this->makeUser(['email_verified_at' => null]);
        $this->actingAs($this->admin, 'platform_admin');
        $this->get(route('admin.users.index', ['verification' => 'verified']))
            ->assertSee($v->email)
            ->assertDontSee($uv->email);
        $this->get(route('admin.users.index', ['verification' => 'unverified']))
            ->assertSee($uv->email)
            ->assertDontSee($v->email);
    }

    public function test_business_has_business_filter(): void
    {
        $with = $this->makeUser();
        $this->attach($with, $this->makeInstitute());
        $without = $this->makeUser();
        $this->actingAs($this->admin, 'platform_admin');
        $this->get(route('admin.users.index', ['business' => 'has_business']))
            ->assertSee($with->name)
            ->assertDontSee($without->name);
    }

    public function test_business_no_business_filter(): void
    {
        $with = $this->makeUser();
        $this->attach($with, $this->makeInstitute());
        $without = $this->makeUser();
        $this->actingAs($this->admin, 'platform_admin');
        $this->get(route('admin.users.index', ['business' => 'no_business']))
            ->assertSee($without->name)
            ->assertDontSee($with->name);
    }

    public function test_business_multiple_filter(): void
    {
        $single = $this->makeUser();
        $this->attach($single, $this->makeInstitute());
        $multi = $this->makeUser();
        $this->attach($multi, $this->makeInstitute());
        $this->attach($multi, $this->makeInstitute());
        $this->actingAs($this->admin, 'platform_admin');
        $this->get(route('admin.users.index', ['business' => 'multiple']))
            ->assertSee($multi->name)
            ->assertDontSee($single->name);
    }

    // ── Pagination ──

    public function test_pagination_works(): void
    {
        for ($i = 0; $i < 30; $i++) {
            $this->makeUser();
        }
        $this->actingAs($this->admin, 'platform_admin');
        $this->get(route('admin.users.index', ['per_page' => 25]))->assertOk();
        $this->get(route('admin.users.index', ['per_page' => 50]))->assertOk();
    }

    public function test_pagination_preserves_filters(): void
    {
        for ($i = 0; $i < 30; $i++) {
            $this->makeUser();
        }
        $this->actingAs($this->admin, 'platform_admin');
        $this->get(route('admin.users.index', ['q' => 'e27l', 'per_page' => 25]))->assertOk();
        $this->get(route('admin.users.index', ['status' => 'active', 'per_page' => 50]))->assertOk();
    }

    // ── Sidebar Verification ──

    public function test_sidebar_contains_all_accounts_link(): void
    {
        $this->actingAs($this->admin, 'platform_admin');
        $this->get(route('admin.users.index'))
            ->assertSee('All Accounts')
            ->assertSee(route('admin.users.index'));
    }

    public function test_sidebar_security_section_visible(): void
    {
        $this->actingAs($this->admin, 'platform_admin');
        $this->get(route('admin.users.index'))
            ->assertSee('SECURITY')
            ->assertSee('All Accounts')
            ->assertSee('Platform Audit')
            ->assertSee('Recycle Bin');
    }

    // ── Summary Cards ──

    public function test_summary_cards_present(): void
    {
        $this->actingAs($this->admin, 'platform_admin');
        $this->get(route('admin.users.index'))
            ->assertSee('Total Accounts')
            ->assertSee('Active')
            ->assertSee('Banned / Suspended')
            ->assertSee('Deleted')
            ->assertSee('Unverified');
    }

    // ── Empty State ──

    public function test_empty_state_shows_message(): void
    {
        $this->actingAs($this->admin, 'platform_admin');
        $this->get(route('admin.users.index', ['q' => '__nonexistent_xyz__']))
            ->assertSee('No accounts found');
    }

    // ── Tenant Isolation Preserved ──

    public function test_tenant_isolation_preserved(): void
    {
        $inst = $this->makeInstitute();
        $iu = \App\Models\InstituteUser::create([
            'institute_id' => $inst->id,
            'role_id' => $this->ownerRole->id,
            'first_name' => 'Tenant',
            'last_name' => 'User',
            'email' => 'tenant-' . uniqid() . '@example.test',
            'phone' => '+8801' . str_pad((string) random_int(100000000, 999999999), 9, '0', STR_PAD_LEFT),
            'password_hash' => bcrypt('password'),
            'status' => 'active',
        ]);

        $u = $this->makeUser();
        $this->actingAs($this->admin, 'platform_admin');

        $resp = $this->get(route('admin.users.index'));
        $resp->assertSee($u->name);
        // institute_users should NOT appear in the global users list
        $resp->assertDontSee('tenant-');
    }

    // ── No Unnecessary full-DB fetch ──

    public function test_index_does_not_fetch_all_users(): void
    {
        for ($i = 0; $i < 30; $i++) {
            $this->makeUser();
        }
        $this->actingAs($this->admin, 'platform_admin');
        $resp = $this->get(route('admin.users.index', ['per_page' => 25]));
        $resp->assertOk();
        // Page 2 should also work
        $resp2 = $this->get(route('admin.users.index', ['per_page' => 25, 'page' => 2]));
        $resp2->assertOk();
    }
}

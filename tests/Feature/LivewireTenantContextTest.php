<?php

namespace Tests\Feature;

use App\Livewire\ChartOfAccountList;
use App\Models\AccountGroup;
use App\Models\ChartOfAccount;
use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\PlatformAdmin;
use App\Models\Role;
use App\Models\User;
use App\Services\MembershipService;
use App\Services\UserAccountService;
use App\Support\TenantContext;
use App\Support\Workspace;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Livewire's update/upload/preview endpoints are registered by the package with
 * only the `web` group - no `auth`, no `tenant` - so every component re-render
 * ran with TenantContext unbound and the TenantScoped global scope disabled,
 * returning ALL tenants' rows. These tests pin the endpoint contract:
 * guests are refused, sessions that cannot resolve a tenant are refused, and
 * component re-renders stay tenant scoped.
 */
class LivewireTenantContextTest extends TestCase
{
    use DatabaseTransactions;

    protected function owner(string $email): User
    {
        return (new UserAccountService)->registerOwner([
            'name' => 'Live Owner',
            'first_name' => 'Live',
            'last_name' => 'Owner',
            'email' => $email,
            'password_hash' => bcrypt('password'),
            'status' => 'active',
        ]);
    }

    protected function assign(User $user, Institute $institute, string $role = 'institute-owner'): void
    {
        $roleId = Role::withoutGlobalScopes()->where('slug', $role)->firstOrFail()->id;
        (new MembershipService)->assign($user, $institute->id, $roleId);
    }

    protected function asUser(User $user, int $workspaceId): static
    {
        return $this->withSession([Workspace::SESSION_KEY => $workspaceId])->actingAs($user, 'web');
    }

    protected function institutes(): array
    {
        return [
            Institute::where('name', 'MAWA ACADEMY')->firstOrFail(),
            Institute::where('name', 'Tutu Center')->firstOrFail(),
        ];
    }

    protected function createTenantAccount(int $instituteId, array $overrides = []): ChartOfAccount
    {
        $type = $overrides['type'] ?? 'asset';
        $groupId = AccountGroup::withoutGlobalScope('institute')
            ->where('institute_id', $instituteId)
            ->where('category', $type)
            ->value('id')
            ?? AccountGroup::withoutGlobalScope('institute')
                ->whereNull('institute_id')
                ->where('is_system', 1)
                ->where('category', $type)
                ->value('id');

        return ChartOfAccount::withoutGlobalScope('institute')->create(array_merge([
            'institute_id' => $instituteId,
            'branch_id' => null,
            'account_group_id' => $groupId,
            'code' => '9101',
            'name' => 'Test Account',
            'type' => $type,
            'is_system' => 0,
            'is_active' => 1,
        ], $overrides));
    }

    /**
     * Pull the initial wire:snapshot for a component out of a rendered page.
     */
    protected function extractSnapshot(string $html, string $needle): ?string
    {
        preg_match_all('/wire:snapshot="([^"]+)"/', $html, $matches);

        foreach ($matches[1] as $raw) {
            $decoded = html_entity_decode($raw, ENT_QUOTES | ENT_HTML5);
            if (str_contains($decoded, $needle)) {
                return $decoded;
            }
        }

        return null;
    }

    // ------------------------------------------------------------ Endpoints

    public function test_guest_is_hidden_from_livewire_update_endpoint(): void
    {
        $this->post(route('default-livewire.update'), ['components' => []])
            ->assertStatus(404);
    }

    public function test_guest_payload_is_refused_on_livewire_update_endpoint(): void
    {
        $this->withHeaders(['X-Livewire' => 'true'])
            ->postJson(route('default-livewire.update'), ['components' => []])
            ->assertStatus(403);
    }

    public function test_guest_cannot_reach_livewire_preview_endpoint(): void
    {
        $this->get(route('livewire.preview-file', ['filename' => 'secret.png']))
            ->assertStatus(404);
    }

    public function test_session_without_tenant_context_is_refused(): void
    {
        // A platform admin is authenticated but has no institute to scope to,
        // so the component must never execute for them on these endpoints.
        $admin = PlatformAdmin::firstOrReuseForTests();

        $this->actingAs($admin, 'platform_admin')
            ->withHeaders(['X-Livewire' => 'true'])
            ->postJson(route('default-livewire.update'), ['components' => []])
            ->assertStatus(403);
    }

    // ------------------------------------------------------------ Re-render scoping

    public function test_component_rerender_stays_tenant_scoped(): void
    {
        [$a, $b] = $this->institutes();

        $own = $this->createTenantAccount($a->id, ['code' => '9797', 'name' => 'AlphaOwnVisibleAccount']);
        $foreign = $this->createTenantAccount($b->id, ['code' => '9797', 'name' => 'AlphaForeignHiddenAccount']);

        $owner = $this->owner('livewire-rerender@example.test');
        $this->assign($owner, $a);
        $this->asUser($owner, $a->id);

        $page = $this->get('/finance/chart-of-accounts');
        $page->assertOk();

        $snapshot = $this->extractSnapshot($page->getContent(), 'chart-of-account-list');
        $this->assertNotNull($snapshot, 'Chart of accounts component snapshot missing from page.');

        // This is the exact path that leaked before: a Livewire update request
        // carries only the `web` middleware group, so TenantContext must be
        // bound by EnsureLivewireContext or the query runs unscoped.
        $response = $this->withHeaders(['X-Livewire' => 'true'])
            ->postJson(route('default-livewire.update'), [
                'components' => [[
                    'snapshot' => $snapshot,
                    'updates' => ['search' => '9797'],
                    'calls' => [],
                ]],
            ]);

        $response->assertOk();

        $html = (string) $response->json('components.0.effects.html');
        $this->assertNotSame('', $html, 'Expected a rendered component in the update response.');

        $this->assertStringContainsString('AlphaOwnVisibleAccount', $html);
        $this->assertStringNotContainsString('AlphaForeignHiddenAccount', $html);
        $this->assertDatabaseHas('chart_of_accounts', ['id' => $foreign->id]);
        $this->assertDatabaseHas('chart_of_accounts', ['id' => $own->id]);
    }

    // ------------------------------------------------------------ Component fail-closed

    public function test_component_refuses_to_render_without_tenant_context(): void
    {
        $previous = TenantContext::id();

        try {
            TenantContext::clear();

            // HttpExceptions are rendered (not rethrown) by Livewire's test
            // broker, so assert on the response the component produced.
            Livewire::test(ChartOfAccountList::class)->assertStatus(403);
        } finally {
            if ($previous !== null) {
                TenantContext::set($previous);
            }
        }
    }

    public function test_toggle_never_touches_another_tenants_account(): void
    {
        [$a, $b] = $this->institutes();

        $own = $this->createTenantAccount($a->id, ['code' => '9601', 'name' => 'Own Toggle', 'is_active' => 1]);
        $foreign = $this->createTenantAccount($b->id, ['code' => '9602', 'name' => 'Foreign Toggle', 'is_active' => 1]);

        $ownerRoleId = Role::withoutGlobalScopes()->where('slug', 'institute-owner')->firstOrFail()->id;
        $instituteUser = InstituteUser::create([
            'institute_id' => $a->id,
            'role_id' => $ownerRoleId,
            'first_name' => 'Live',
            'last_name' => 'Wire',
            'email' => 'livewire-toggle-'.uniqid().'@example.test',
            'phone' => '01700'.mt_rand(100000, 999999),
            'password_hash' => bcrypt('secret12345'),
            'status' => 'active',
        ]);

        $this->actingAs($instituteUser, 'institute_user');
        TenantContext::set($a->id);

        // Prove management actions are available at all, otherwise the
        // foreign assertion below could pass for the wrong reason.
        Livewire::test(ChartOfAccountList::class)->call('toggle', $own->id);
        $this->assertFalse(
            (bool) $own->fresh()->is_active,
            'Owner must be able to toggle their own account.'
        );

        // A foreign id must not even resolve (404), not just fail the policy.
        Livewire::test(ChartOfAccountList::class)->call('toggle', $foreign->id)->assertStatus(404);

        $this->assertTrue(
            (bool) $foreign->fresh()->is_active,
            'Another tenant\'s account must stay untouched.'
        );
    }
}

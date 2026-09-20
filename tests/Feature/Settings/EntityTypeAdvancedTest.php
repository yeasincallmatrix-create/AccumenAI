<?php

namespace Tests\Feature\Settings;

use App\Enums\BusinessEntityType;
use App\Models\Institute;
use App\Models\User;
use App\Services\Accounting\BusinessEntityService;
use App\Services\MembershipService;
use App\Services\UserAccountService;
use Tests\TestCase;

class EntityTypeAdvancedTest extends TestCase
{
    protected function tenantOwner(string $email, bool $advanced = false, string $entity = 'single_entity'): array
    {
        $unique = strtolower(\Illuminate\Support\Str::random(10));
        $email = str_replace('@', "+{$unique}@", $email);
        $institute = Institute::create([
            'name' => 'Entity Co '.$unique,
            'slug' => 'entity-co-'.$unique,
            'status' => 'active',
            'advanced_accounting_enabled' => $advanced,
            'business_entity_type' => $entity,
        ]);
        $owner = (new UserAccountService)->registerOwner([
            'name' => 'Entity Owner',
            'first_name' => 'Entity',
            'last_name' => 'Owner',
            'email' => $email,
            'password_hash' => bcrypt('password'),
            'status' => 'active',
        ]);
        $roleId = \App\Models\Role::where('slug', 'institute-owner')->firstOrFail()->id;
        (new MembershipService)->assign($owner, $institute->id, $roleId);

        return [$institute, $owner];
    }

    protected function asUser(User $user, int $workspaceId): static
    {
        return $this->withSession([\App\Support\Workspace::SESSION_KEY => $workspaceId])
            ->actingAs($user, 'web');
    }

    public function test_default_is_single_entity(): void
    {
        $institute = Institute::create([
            'name' => 'Default Ent '.\Illuminate\Support\Str::random(6),
            'slug' => 'default-ent-'.\Illuminate\Support\Str::random(6),
            'status' => 'active',
        ]);

        $this->assertEquals(
            BusinessEntityType::SINGLE_ENTITY,
            app(BusinessEntityService::class)->getType($institute->id)
        );
    }

    public function test_advanced_off_hides_entity_dropdown(): void
    {
        [$institute, $owner] = $this->tenantOwner('eta-off@example.test', false);

        $this->asUser($owner, $institute->id)
            ->get(route('settings.advanced-accounting'))
            ->assertStatus(200)
            ->assertDontSee('Business Entity Type', false);
    }

    public function test_advanced_on_shows_entity_dropdown(): void
    {
        [$institute, $owner] = $this->tenantOwner('eta-on@example.test', true);

        $this->asUser($owner, $institute->id)
            ->get(route('settings.advanced-accounting'))
            ->assertStatus(200)
            ->assertSee('Business Entity Type')
            ->assertSee('Sole Proprietorship')
            ->assertSee('Partnership')
            ->assertSee('Private Limited');
    }

    public function test_advanced_off_resets_to_single_entity(): void
    {
        [$institute, $owner] = $this->tenantOwner('eta-reset@example.test', true, 'partnership');

        $this->asUser($owner, $institute->id)
            ->post(route('settings.advanced-accounting.toggle'));

        $this->assertEquals(
            BusinessEntityType::SINGLE_ENTITY,
            app(BusinessEntityService::class)->getType($institute->id)
        );
    }

    public function test_cannot_update_entity_type_when_advanced_off(): void
    {
        [$institute, $owner] = $this->tenantOwner('eta-403@example.test', false);

        $this->asUser($owner, $institute->id)
            ->put(route('settings.advanced-accounting.entity-type.update'), [
                'business_entity_type' => 'partnership',
            ])->assertStatus(403);
    }

    public function test_can_update_entity_type_when_advanced_on(): void
    {
        [$institute, $owner] = $this->tenantOwner('eta-ok@example.test', true);

        $this->asUser($owner, $institute->id)
            ->put(route('settings.advanced-accounting.entity-type.update'), [
                'business_entity_type' => 'partnership',
            ])->assertRedirect();

        $this->assertEquals(
            BusinessEntityType::PARTNERSHIP,
            app(BusinessEntityService::class)->getType($institute->id)
        );
    }

    public function test_business_entity_old_route_redirects(): void
    {
        [$institute, $owner] = $this->tenantOwner('eta-redir@example.test', true);

        $this->asUser($owner, $institute->id)
            ->get(route('settings.business-entity'))
            ->assertRedirect(route('settings.advanced-accounting'));
    }

    public function test_partner_module_blocks_when_not_partnership(): void
    {
        [$institute, $owner] = $this->tenantOwner('eta-guard@example.test', true, 'private_limited');

        $this->asUser($owner, $institute->id)
            ->get(route('settings.business-entity.partnership.index'))
            ->assertStatus(404);
    }
}

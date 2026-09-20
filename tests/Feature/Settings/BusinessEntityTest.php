<?php

namespace Tests\Feature\Settings;

use App\Models\Institute;
use App\Models\User;
use App\Services\MembershipService;
use App\Services\UserAccountService;
use Tests\TestCase;

class BusinessEntityTest extends TestCase
{
    protected function tenantOwner(string $email): array
    {
        $unique = strtolower(\Illuminate\Support\Str::random(10));
        $email = str_replace('@', "+{$unique}@", $email);
        $institute = Institute::where('name', 'MAWA ACADEMY')->firstOrFail();
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

    public function test_selector_page_loads(): void
    {
        [$institute, $owner] = $this->tenantOwner('entity-sel@example.test');

        $this->asUser($owner, $institute->id)
            ->get(route('settings.business-entity'))
            ->assertStatus(200)
            ->assertSee('Business Entity Type')
            ->assertSee('Sole Proprietorship')
            ->assertSee('Partnership')
            ->assertSee('Private Limited');
    }

    public function test_setting_entity_type_redirects_to_specific_page(): void
    {
        [$institute, $owner] = $this->tenantOwner('entity-save@example.test');

        $response = $this->asUser($owner, $institute->id)
            ->put(route('settings.business-entity.update'), [
                'business_entity_type' => 'partnership',
            ]);

        $response->assertRedirect(route('settings.entity.partnership'));
        $this->assertEquals('partnership', $institute->fresh()->business_entity_type);
    }

    public function test_sole_proprietorship_page_loads(): void
    {
        [$institute, $owner] = $this->tenantOwner('entity-sole@example.test');
        $institute->update(['business_entity_type' => 'sole_proprietorship']);

        $this->asUser($owner, $institute->id)
            ->get(route('settings.entity.sole-proprietorship'))
            ->assertStatus(200)
            ->assertSee('Sole Proprietorship');
    }

    public function test_partnership_page_loads(): void
    {
        [$institute, $owner] = $this->tenantOwner('entity-part@example.test');
        $institute->update(['business_entity_type' => 'partnership']);

        $this->asUser($owner, $institute->id)
            ->get(route('settings.entity.partnership'))
            ->assertStatus(200)
            ->assertSee('Partnership')
            ->assertSee('Partners');
    }

    public function test_private_limited_page_loads(): void
    {
        [$institute, $owner] = $this->tenantOwner('entity-pvt@example.test');
        $institute->update(['business_entity_type' => 'private_limited']);

        $this->asUser($owner, $institute->id)
            ->get(route('settings.entity.private-limited'))
            ->assertStatus(200)
            ->assertSee('Private Limited');
    }

    public function test_cannot_access_wrong_entity_page(): void
    {
        [$institute, $owner] = $this->tenantOwner('entity-wrong@example.test');
        $institute->update(['business_entity_type' => 'partnership']);

        $this->asUser($owner, $institute->id)
            ->get(route('settings.entity.sole-proprietorship'))
            ->assertStatus(404);
    }
}

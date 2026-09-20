<?php

namespace Tests\Feature\Settings;

use App\Models\Institute;
use App\Models\Partner;
use App\Models\User;
use App\Services\MembershipService;
use App\Services\UserAccountService;
use Tests\TestCase;

class PartnerCrudTest extends TestCase
{
    protected function tenantOwner(string $email): array
    {
        $unique = strtolower(\Illuminate\Support\Str::random(10));
        $email = str_replace('@', "+{$unique}@", $email);
        // Fresh institute per test: shared MAWA accumulates rows (no rollback).
        $institute = Institute::create([
            'name' => 'Partner Co '.$unique,
            'slug' => 'partner-co-'.$unique,
            'status' => 'active',
        ]);
        $owner = (new UserAccountService)->registerOwner([
            'name' => 'Partner Owner',
            'first_name' => 'Partner',
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

    public function test_index_loads(): void
    {
        [$institute, $owner] = $this->tenantOwner('pc-idx@example.test');

        $this->asUser($owner, $institute->id)
            ->get(route('settings.business-entity.partnership.index'))
            ->assertStatus(200)
            ->assertSee('Partners');
    }

    public function test_create_form_loads(): void
    {
        [$institute, $owner] = $this->tenantOwner('pc-form@example.test');

        $this->asUser($owner, $institute->id)
            ->get(route('settings.business-entity.partnership.create'))
            ->assertStatus(200)
            ->assertSee('Add Partner');
    }

    public function test_partner_created_with_coa_accounts(): void
    {
        [$institute, $owner] = $this->tenantOwner('pc-create@example.test');

        $this->asUser($owner, $institute->id)
            ->post(route('settings.business-entity.partnership.store'), [
                'name' => 'Partner A', 'capital' => 100000,
                'share_percent' => 40, 'is_active' => true,
            ])->assertRedirect();

        $partner = Partner::where('institute_id', $institute->id)->first();
        $this->assertNotNull($partner);
        $this->assertNotNull($partner->capital_account_id);
        $this->assertNotNull($partner->drawing_account_id);
        $this->assertDatabaseHas('chart_of_accounts', [
            'id' => $partner->capital_account_id,
            'institute_id' => $institute->id,
        ]);
    }

    public function test_share_percent_cannot_exceed_100(): void
    {
        [$institute, $owner] = $this->tenantOwner('pc-share@example.test');
        Partner::create([
            'institute_id' => $institute->id,
            'name' => 'P1', 'capital' => 0, 'share_percent' => 80,
        ]);

        $this->asUser($owner, $institute->id)
            ->post(route('settings.business-entity.partnership.store'), [
                'name' => 'P2', 'capital' => 0, 'share_percent' => 30,
            ])->assertSessionHasErrors('share_percent');
    }

    public function test_partner_updated(): void
    {
        [$institute, $owner] = $this->tenantOwner('pc-upd@example.test');
        $p = Partner::create([
            'institute_id' => $institute->id,
            'name' => 'Old', 'capital' => 0, 'share_percent' => 10,
        ]);

        $this->asUser($owner, $institute->id)
            ->put(route('settings.business-entity.partnership.update', $p), [
                'name' => 'New', 'capital' => 500, 'share_percent' => 20,
            ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertEquals('New', $p->fresh()->name);
    }

    public function test_partner_deleted(): void
    {
        [$institute, $owner] = $this->tenantOwner('pc-del@example.test');
        $p = Partner::create([
            'institute_id' => $institute->id,
            'name' => 'X', 'capital' => 0, 'share_percent' => 5,
        ]);

        $this->asUser($owner, $institute->id)
            ->delete(route('settings.business-entity.partnership.destroy', $p))
            ->assertRedirect();
        $this->assertDatabaseMissing('partners', ['id' => $p->id]);
    }

    public function test_cross_tenant_partner_blocked(): void
    {
        [$institute, $owner] = $this->tenantOwner('pc-cross@example.test');
        [$other] = $this->tenantOwner('pc-other@example.test');
        $foreign = Partner::withoutGlobalScope('institute')->create([
            'institute_id' => $other->id,
            'name' => 'Other', 'capital' => 0, 'share_percent' => 0,
        ]);

        $this->asUser($owner, $institute->id)
            ->get(route('settings.business-entity.partnership.edit', $foreign))
            ->assertStatus(404);
    }
}

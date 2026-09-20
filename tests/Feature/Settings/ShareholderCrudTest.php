<?php

namespace Tests\Feature\Settings;

use App\Models\Institute;
use App\Models\Shareholder;
use App\Models\User;
use App\Services\Accounting\ShareholderService;
use App\Services\MembershipService;
use App\Services\UserAccountService;
use Tests\TestCase;

class ShareholderCrudTest extends TestCase
{
    protected function tenantOwner(string $email): array
    {
        $unique = strtolower(\Illuminate\Support\Str::random(10));
        $email = str_replace('@', "+{$unique}@", $email);
        // Fresh institute per test: shared rows accumulate (no rollback).
        $institute = Institute::create([
            'name' => 'SH Co '.$unique,
            'slug' => 'sh-co-'.$unique,
            'status' => 'active',
            'business_entity_type' => 'private_limited',
        ]);
        $owner = (new UserAccountService)->registerOwner([
            'name' => 'Shareholder Owner',
            'first_name' => 'Share',
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
        [$institute, $owner] = $this->tenantOwner('sc-idx@example.test');

        $this->asUser($owner, $institute->id)
            ->get(route('settings.business-entity.private-limited.index'))
            ->assertStatus(200)
            ->assertSee('Shareholders');
    }

    public function test_create_form_loads(): void
    {
        [$institute, $owner] = $this->tenantOwner('sc-form@example.test');

        $this->asUser($owner, $institute->id)
            ->get(route('settings.business-entity.private-limited.create'))
            ->assertStatus(200)
            ->assertSee('Add Shareholder');
    }

    public function test_shareholder_created(): void
    {
        [$institute, $owner] = $this->tenantOwner('sc-create@example.test');

        $this->asUser($owner, $institute->id)
            ->post(route('settings.business-entity.private-limited.store'), [
                'name' => 'SH1', 'shares' => 100, 'face_value' => 10,
                'share_percent' => 50, 'is_director' => true,
                'director_designation' => 'Chairman',
            ])->assertRedirect();

        $sh = Shareholder::where('institute_id', $institute->id)->first();
        $this->assertNotNull($sh);
        $this->assertTrue($sh->is_director);
    }

    public function test_share_percent_cannot_exceed_100(): void
    {
        [$institute, $owner] = $this->tenantOwner('sc-share@example.test');
        Shareholder::create([
            'institute_id' => $institute->id,
            'name' => 'S1', 'shares' => 80, 'face_value' => 10, 'share_percent' => 80,
        ]);

        $this->asUser($owner, $institute->id)
            ->post(route('settings.business-entity.private-limited.store'), [
                'name' => 'S2', 'shares' => 30, 'face_value' => 10, 'share_percent' => 30,
            ])->assertSessionHasErrors('share_percent');
    }

    public function test_shareholder_updated(): void
    {
        [$institute, $owner] = $this->tenantOwner('sc-upd@example.test');
        $sh = Shareholder::create([
            'institute_id' => $institute->id,
            'name' => 'Old', 'shares' => 10, 'face_value' => 10, 'share_percent' => 10,
        ]);

        $this->asUser($owner, $institute->id)
            ->put(route('settings.business-entity.private-limited.update', $sh), [
                'name' => 'New', 'shares' => 20, 'face_value' => 10, 'share_percent' => 20,
            ])->assertRedirect();

        $this->assertEquals('New', $sh->fresh()->name);
    }

    public function test_shareholder_deleted(): void
    {
        [$institute, $owner] = $this->tenantOwner('sc-del@example.test');
        $sh = Shareholder::create([
            'institute_id' => $institute->id,
            'name' => 'X', 'shares' => 5, 'face_value' => 10, 'share_percent' => 5,
        ]);

        $this->asUser($owner, $institute->id)
            ->delete(route('settings.business-entity.private-limited.destroy', $sh))
            ->assertRedirect();
        $this->assertDatabaseMissing('shareholders', ['id' => $sh->id]);
    }

    public function test_cross_tenant_shareholder_blocked(): void
    {
        [$institute, $owner] = $this->tenantOwner('sc-cross@example.test');
        [$other] = $this->tenantOwner('sc-other@example.test');
        $foreign = Shareholder::withoutGlobalScope('institute')->create([
            'institute_id' => $other->id,
            'name' => 'Other', 'shares' => 1, 'face_value' => 10, 'share_percent' => 0,
        ]);

        $this->asUser($owner, $institute->id)
            ->get(route('settings.business-entity.private-limited.edit', $foreign))
            ->assertStatus(404);
    }

    public function test_director_flag_and_totals(): void
    {
        [$institute] = $this->tenantOwner('sc-tot@example.test');
        $svc = app(ShareholderService::class);
        $svc->create($institute->id, [
            'name' => 'D1', 'shares' => 60, 'face_value' => 10,
            'share_percent' => 60, 'is_director' => true, 'director_designation' => 'MD',
        ]);
        $svc->create($institute->id, [
            'name' => 'S2', 'shares' => 40, 'face_value' => 10, 'share_percent' => 40,
        ]);

        $this->assertEquals(100, $svc->totalShares($institute->id));
        $this->assertEquals(1000.0, $svc->totalPaidUp($institute->id));
        $this->assertEquals(1, Shareholder::where('institute_id', $institute->id)->directors()->count());
    }
}

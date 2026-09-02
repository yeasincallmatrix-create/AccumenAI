<?php

namespace Tests\Feature;

use App\Models\Institute;
use App\Models\User;
use App\Services\System\TenantProtectionService;
use App\Services\UserAccountService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class TenantProtectionTest extends TestCase
{
    use DatabaseTransactions;

    public function test_request_deletion_creates_confirmation_workflow(): void
    {
        $owner = (new UserAccountService)->registerOwner([
            'name' => 'Protect Owner',
            'email' => 'protect-'.uniqid().'@example.test',
            'password_hash' => bcrypt('secret12345'),
            'status' => 'active',
        ]);

        $institute = Institute::create([
            'name' => 'Protect Institute ' . uniqid(),
            'slug' => 'protect-'.uniqid(),
            'industry' => 'education',
            'sub_industry' => 'school',
            'country' => 'Bangladesh',
            'status' => 'active',
        ]);

        $service = app(TenantProtectionService::class);
        $request = $service->requestDeletion('institute', $institute->id, $owner->id, 'test reason');

        $this->assertDatabaseHas('tenant_deletion_requests', [
            'id' => $request->id,
            'deletable_id' => $institute->id,
            'status' => 'pending',
        ]);
        $this->assertDatabaseHas('tenant_recovery_archives', [
            'archivable_id' => $institute->id,
            'archivable_type' => Institute::class,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'module' => 'tenant_protection',
            'action' => 'deletion_requested',
        ]);

        $this->assertNotNull($request->raw_token);
        $this->assertEquals('pending', $request->status);
    }

    public function test_confirm_deletion_soft_deletes_and_audits(): void
    {
        $owner = (new UserAccountService)->registerOwner([
            'name' => 'Confirm Owner',
            'email' => 'confirm-'.uniqid().'@example.test',
            'password_hash' => bcrypt('secret12345'),
            'status' => 'active',
        ]);

        $institute = Institute::create([
            'name' => 'Confirm Institute ' . uniqid(),
            'slug' => 'confirm-'.uniqid(),
            'industry' => 'education',
            'country' => 'Bangladesh',
            'status' => 'active',
        ]);

        $service = app(TenantProtectionService::class);
        $request = $service->requestDeletion('institute', $institute->id, $owner->id);

        $confirmed = $service->confirmDeletion($request->raw_token, $owner->id);

        $this->assertEquals('confirmed', $confirmed->status);
        $this->assertDatabaseHas('audit_logs', [
            'module' => 'tenant_protection',
            'action' => 'deletion_confirmed',
        ]);

        // Institute should be soft deleted
        $this->assertSoftDeleted('institutes', ['id' => $institute->id]);
    }

    public function test_cancel_deletion(): void
    {
        $owner = (new UserAccountService)->registerOwner([
            'name' => 'Cancel Owner',
            'email' => 'cancel-'.uniqid().'@example.test',
            'password_hash' => bcrypt('secret12345'),
            'status' => 'active',
        ]);

        $institute = Institute::create([
            'name' => 'Cancel Institute ' . uniqid(),
            'slug' => 'cancel-'.uniqid(),
            'industry' => 'education',
            'country' => 'Bangladesh',
            'status' => 'active',
        ]);

        $service = app(TenantProtectionService::class);
        $request = $service->requestDeletion('institute', $institute->id, $owner->id);

        $cancelled = $service->cancelDeletion($request->id, $owner->id);

        $this->assertEquals('cancelled', $cancelled->status);
        $this->assertDatabaseHas('audit_logs', [
            'module' => 'tenant_protection',
            'action' => 'deletion_cancelled',
        ]);
    }

    public function test_recovery_restores_soft_deleted_institute(): void
    {
        $owner = (new UserAccountService)->registerOwner([
            'name' => 'Recover Owner',
            'email' => 'recover-'.uniqid().'@example.test',
            'password_hash' => bcrypt('secret12345'),
            'status' => 'active',
        ]);

        $institute = Institute::create([
            'name' => 'Recover Institute ' . uniqid(),
            'slug' => 'recover-'.uniqid(),
            'industry' => 'education',
            'country' => 'Bangladesh',
            'status' => 'active',
        ]);

        $service = app(TenantProtectionService::class);
        $request = $service->requestDeletion('institute', $institute->id, $owner->id);
        $service->confirmDeletion($request->raw_token, $owner->id);

        $this->assertSoftDeleted('institutes', ['id' => $institute->id]);

        $recovered = $service->recover(Institute::class, $institute->id, $owner->id);

        $this->assertTrue($recovered);
        $this->assertDatabaseHas('institutes', ['id' => $institute->id]);
        $this->assertDatabaseHas('audit_logs', [
            'module' => 'tenant_protection',
            'action' => 'recovery_restored',
        ]);
    }

    public function test_guard_cascade_delete_blocks_with_data(): void
    {
        $institute = Institute::create([
            'name' => 'Cascade Institute ' . uniqid(),
            'slug' => 'cascade-'.uniqid(),
            'industry' => 'education',
            'country' => 'Bangladesh',
            'status' => 'active',
        ]);

        // Create dependent student
        \App\Models\Student::create([
            'institute_id' => $institute->id,
            'student_id_number' => 'CASC'.uniqid(),
            'first_name' => 'Test',
            'last_name' => 'Student',
            'status' => 'active',
            'admission_date' => now(),
        ]);

        $service = app(TenantProtectionService::class);
        $canDelete = $service->guardCascadeDelete('institute', $institute->id);

        $this->assertFalse($canDelete, 'Should block cascade delete when dependent data exists');
    }

    public function test_owner_deletion_is_protected(): void
    {
        $owner = (new UserAccountService)->registerOwner([
            'name' => 'Owner Delete',
            'email' => 'owner-del-'.uniqid().'@example.test',
            'password_hash' => bcrypt('secret12345'),
            'status' => 'active',
        ]);

        $service = app(TenantProtectionService::class);
        $request = $service->requestDeletion('user', $owner->id, $owner->id, 'owner deletion test');

        $this->assertDatabaseHas('tenant_deletion_requests', [
            'deletable_id' => $owner->id,
            'deletable_type' => User::class,
        ]);
        $this->assertDatabaseHas('tenant_recovery_archives', [
            'archivable_id' => $owner->id,
        ]);
    }

    public function test_expired_token_is_rejected(): void
    {
        $owner = (new UserAccountService)->registerOwner([
            'name' => 'Expire Owner',
            'email' => 'expire-'.uniqid().'@example.test',
            'password_hash' => bcrypt('secret12345'),
            'status' => 'active',
        ]);

        $institute = Institute::create([
            'name' => 'Expire Institute ' . uniqid(),
            'slug' => 'expire-'.uniqid(),
            'industry' => 'education',
            'country' => 'Bangladesh',
            'status' => 'active',
        ]);

        $service = app(TenantProtectionService::class);
        $request = $service->requestDeletion('institute', $institute->id, $owner->id);
        $raw = $request->getAttribute('raw_token');

        // Manually expire (use fresh instance to avoid dirty raw_token)
        $request->fresh()->update(['expires_at' => now()->subHour()]);

        $this->expectException(\Exception::class);
        $service->confirmDeletion($raw ?? 'invalid', $owner->id);
    }
}

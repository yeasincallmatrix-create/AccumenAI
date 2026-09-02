<?php

namespace Tests\Feature;

use App\Models\CashMemo;
use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\OfflineSyncQueue;
use App\Models\Role;
use App\Models\Student;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class OfflineSyncTest extends TestCase
{
    use DatabaseTransactions;

    protected string $password = 'secret12345';

    private Institute $institute;

    private InstituteUser $owner;

    protected function setUp(): void
    {
        parent::setUp();

        TenantContext::clear();

        $this->institute = Institute::where('name', 'MAWA ACADEMY')->firstOrFail();
        $this->owner = $this->makeStaff('institute-owner', 'sync-owner@example.test');
    }

    protected function makeStaff(string $roleSlug, string $email): InstituteUser
    {
        $role = Role::where('slug', $roleSlug)->firstOrFail();

        return InstituteUser::create([
            'institute_id' => $this->institute->id,
            'role_id' => $role->id,
            'email' => $email,
            'phone' => '0170000'.substr(md5($email), 0, 4),
            'password_hash' => bcrypt($this->password),
            'status' => 'active',
        ]);
    }

    private function validRecord(array $overrides = []): array
    {
        return array_merge([
            'client_uuid' => Str::uuid()->toString(),
            'entity_type' => 'cash_memo',
            'created_offline_at' => '2026-08-12 10:30:00',
            'payload' => [
                'amount' => 1500.00,
                'description' => 'Tuition fee',
                'payment_method' => 'cash',
            ],
        ], $overrides);
    }

    public function test_guest_is_redirected_from_sync_index(): void
    {
        $this->get('/sync')->assertRedirect('/admin/login');
    }

    public function test_upload_queues_records_for_review(): void
    {
        $student = DB::table('students')->where('institute_id', $this->institute->id)->first();

        $response = $this->actingAs($this->owner, 'institute_user')
            ->post('/sync/upload', [
                'records' => [
                    $this->validRecord(['payload' => ['amount' => 1500, 'student_id' => $student->id]]),
                    $this->validRecord(['payload' => ['amount' => 800, 'payment_method' => 'bkash']]),
                ],
            ]);

        $response->assertRedirect(route('sync.index', ['status' => 'pending_review']));

        $queued = OfflineSyncQueue::query()
            ->where('created_by', $this->owner->id)
            ->orderBy('id')
            ->get();

        $this->assertCount(2, $queued);
        $this->assertSame('pending_review', $queued->first()->status);
        $this->assertSame($this->institute->id, (int) $queued->first()->institute_id);
        $this->assertSame('cash_memo', $queued->first()->entity_type);
        $this->assertSame(1500.0, (float) $queued->first()->payload['amount']);
    }

    public function test_upload_is_idempotent_by_client_uuid(): void
    {
        $uuid = Str::uuid()->toString();

        $this->actingAs($this->owner, 'institute_user')
            ->post('/sync/upload', ['records' => [$this->validRecord(['client_uuid' => $uuid])]])
            ->assertRedirect();

        $this->actingAs($this->owner, 'institute_user')
            ->post('/sync/upload', ['records' => [$this->validRecord(['client_uuid' => $uuid])]])
            ->assertRedirect();

        $this->assertSame(1, OfflineSyncQueue::query()->where('client_uuid', $uuid)->count());
    }

    public function test_upload_validates_records(): void
    {
        $this->actingAs($this->owner, 'institute_user')
            ->post('/sync/upload', ['records' => [$this->validRecord(['payload' => ['amount' => 0]])]])
            ->assertSessionHasErrors('records.0.payload.amount');
    }

    public function test_index_shows_only_own_institutes_records(): void
    {
        $this->actingAs($this->owner, 'institute_user')
            ->post('/sync/upload', ['records' => [$this->validRecord()]])
            ->assertRedirect();

        $this->get('/sync')->assertOk()->assertSee('Tuition fee');
    }

    public function test_approve_materializes_cash_memo(): void
    {
        $student = Student::query()->where('institute_id', $this->institute->id)->firstOrFail();
        $uuid = Str::uuid()->toString();

        $this->actingAs($this->owner, 'institute_user')
            ->post('/sync/upload', ['records' => [$this->validRecord([
                'client_uuid' => $uuid,
                'payload' => ['amount' => 2500, 'student_id' => $student->id, 'payment_method' => 'bank', 'description' => 'Admission'],
            ])]])
            ->assertRedirect();

        $queue = OfflineSyncQueue::query()->where('client_uuid', $uuid)->firstOrFail();

        $this->actingAs($this->owner, 'institute_user')
            ->post('/sync/'.$queue->id.'/approve')
            ->assertRedirect();

        $queue->refresh();
        $this->assertSame('approved', $queue->status);
        $this->assertSame($this->owner->id, (int) $queue->reviewed_by);
        $this->assertNotNull($queue->synced_at);

        $memo = CashMemo::query()->find($queue->materialized_id);
        $this->assertNotNull($memo);
        $this->assertSame((int) $queue->id, (int) $memo->offline_origin_id);
        $this->assertSame((int) $student->id, (int) $memo->student_id);
        $this->assertSame(2500.0, (float) $memo->amount);
        $this->assertSame('bank', $memo->payment_method);
        $this->assertSame('CM-', substr($memo->memo_number, 0, 3));
        $this->assertSame((int) $this->owner->id, (int) $memo->created_by);
    }

    public function test_approve_rejects_student_of_another_institute(): void
    {
        $other = Institute::where('name', 'Halumoni Computer training center')->firstOrFail();
        $foreignStudent = DB::table('students')->where('institute_id', $other->id)->first();
        $uuid = Str::uuid()->toString();

        $this->actingAs($this->owner, 'institute_user')
            ->post('/sync/upload', ['records' => [$this->validRecord([
                'client_uuid' => $uuid,
                'payload' => ['amount' => 500, 'student_id' => $foreignStudent->id],
            ])]])
            ->assertRedirect();

        $queue = OfflineSyncQueue::query()->where('client_uuid', $uuid)->firstOrFail();

        $this->actingAs($this->owner, 'institute_user')
            ->post('/sync/'.$queue->id.'/approve')
            ->assertSessionHasErrors('student_id');

        $queue->refresh();
        $this->assertSame('pending_review', $queue->status);
        $this->assertNull(CashMemo::query()->where('offline_origin_id', $queue->id)->first());
    }

    public function test_approved_record_cannot_be_reviewed_again(): void
    {
        $uuid = Str::uuid()->toString();

        $this->actingAs($this->owner, 'institute_user')
            ->post('/sync/upload', ['records' => [$this->validRecord(['client_uuid' => $uuid])]])
            ->assertRedirect();

        $queue = OfflineSyncQueue::query()->where('client_uuid', $uuid)->firstOrFail();

        $this->actingAs($this->owner, 'institute_user')
            ->post('/sync/'.$queue->id.'/approve')
            ->assertRedirect();

        $response = $this->actingAs($this->owner, 'institute_user')
            ->post('/sync/'.$queue->id.'/approve');

        $response->assertRedirect()->assertSessionHas('error');

        $this->assertSame(1, CashMemo::query()->where('offline_origin_id', $queue->id)->count());
    }

    public function test_reject_marks_record_with_reason(): void
    {
        $uuid = Str::uuid()->toString();

        $this->actingAs($this->owner, 'institute_user')
            ->post('/sync/upload', ['records' => [$this->validRecord(['client_uuid' => $uuid])]])
            ->assertRedirect();

        $queue = OfflineSyncQueue::query()->where('client_uuid', $uuid)->firstOrFail();

        $this->actingAs($this->owner, 'institute_user')
            ->post('/sync/'.$queue->id.'/reject', ['reject_reason' => 'Invalid amount'])
            ->assertRedirect();

        $queue->refresh();
        $this->assertSame('rejected', $queue->status);
        $this->assertSame('Invalid amount', $queue->reject_reason);
        $this->assertSame($this->owner->id, (int) $queue->reviewed_by);
        $this->assertNull(CashMemo::query()->where('offline_origin_id', $queue->id)->first());
    }

    public function test_reject_requires_reason(): void
    {
        $uuid = Str::uuid()->toString();

        $this->actingAs($this->owner, 'institute_user')
            ->post('/sync/upload', ['records' => [$this->validRecord(['client_uuid' => $uuid])]])
            ->assertRedirect();

        $queue = OfflineSyncQueue::query()->where('client_uuid', $uuid)->firstOrFail();

        $this->actingAs($this->owner, 'institute_user')
            ->post('/sync/'.$queue->id.'/reject', ['reject_reason' => ''])
            ->assertSessionHasErrors('reject_reason');
    }

    public function test_foreign_institute_record_is_404(): void
    {
        $other = Institute::where('name', 'Halumoni Computer training center')->firstOrFail();
        $foreignUser = InstituteUser::where('institute_id', $other->id)->firstOrFail();

        $foreignQueue = OfflineSyncQueue::query()->withoutGlobalScopes()->create([
            'client_uuid' => Str::uuid()->toString(),
            'entity_type' => 'cash_memo',
            'institute_id' => $other->id,
            'created_by' => $foreignUser->id,
            'payload' => ['amount' => 100],
            'created_offline_at' => now(),
        ]);

        $this->actingAs($this->owner, 'institute_user')
            ->post('/sync/'.$foreignQueue->id.'/approve')
            ->assertNotFound();

        $this->actingAs($this->owner, 'institute_user')
            ->get('/sync')
            ->assertOk()
            ->assertDontSee($foreignQueue->client_uuid);
    }

    public function test_teacher_cannot_review_but_owner_can(): void
    {
        $teacher = $this->makeStaff('teacher', 'sync-teacher@example.test');

        $this->actingAs($teacher, 'institute_user')->get('/sync')->assertForbidden();

        $this->actingAs($this->owner, 'institute_user')->get('/sync')->assertOk();
    }

    public function test_accountant_can_review(): void
    {
        $accountant = $this->makeStaff('accountant', 'sync-accountant@example.test');

        $this->actingAs($accountant, 'institute_user')->get('/sync')->assertOk();
    }

    public function test_teacher_cannot_upload_without_finance_view(): void
    {
        $teacher = $this->makeStaff('teacher', 'sync-upload-teacher@example.test');

        $this->actingAs($teacher, 'institute_user')
            ->post('/sync/upload', ['records' => [$this->validRecord()]])
            ->assertForbidden();
    }

    public function test_accountant_can_upload_with_finance_view(): void
    {
        $accountant = $this->makeStaff('accountant', 'sync-upload-accountant@example.test');

        $this->actingAs($accountant, 'institute_user')
            ->post('/sync/upload', ['records' => [$this->validRecord()]])
            ->assertRedirect(route('sync.index', ['status' => 'pending_review']));
    }
}

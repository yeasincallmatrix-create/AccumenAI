<?php

namespace Tests\Feature;

use App\Models\AccountHead;
use App\Models\Attendance;
use App\Models\Batch;
use App\Models\Branch;
use App\Models\Course;
use App\Models\Institute;
use App\Models\InstituteSetting;
use App\Models\InstituteUser;
use App\Models\Role;
use App\Models\Student;
use App\Models\Transaction;
use App\Services\Ai\AiContext;
use App\Services\Ai\Tools\Core\IncomeExpenseTool;
use App\Services\Ai\Tools\Education\AttendanceTool;
use App\Services\Ai\Tools\Education\BatchesTool;
use App\Services\Ai\Tools\Education\StudentsTool;
use App\Support\BranchContext;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class BranchAuthorizationTest extends TestCase
{
    use DatabaseTransactions;

    protected string $password = 'secret12345';

    protected Institute $institute;

    protected Branch $branchA;

    protected Branch $branchB;

    protected Course $course;

    protected function setUp(): void
    {
        parent::setUp();

        $this->institute = Institute::create([
            'name' => 'Branch Test Institute '.mt_rand(1000, 9999),
            'slug' => 'branch-test-'.mt_rand(1000, 9999),
            'industry' => 'education',
            'status' => 'active',
        ]);

        $this->branchA = Branch::create(['institute_id' => $this->institute->id, 'name' => 'Branch A', 'status' => 'active']);
        $this->branchB = Branch::create(['institute_id' => $this->institute->id, 'name' => 'Branch B', 'status' => 'active']);

        $this->course = Course::create(['course_code' => 'BR'.mt_rand(1000, 9999), 'name' => 'Branch Course']);

        InstituteSetting::withoutGlobalScopes()->create([
            'institute_id' => $this->institute->id,
            'ai_config' => [
                'enabled' => true,
                'features' => ['assistant'],
                'daily_limit' => 0,
                'monthly_limit' => 0,
            ],
        ]);
    }

    protected function user(string $roleSlug, string $email, ?int $branchId = null): InstituteUser
    {
        $role = Role::where('slug', $roleSlug)->firstOrFail();

        return InstituteUser::create([
            'institute_id' => $this->institute->id,
            'role_id' => $role->id,
            'branch_id' => $branchId,
            'email' => $email,
            'phone' => '0170000'.substr(md5($email), 0, 4),
            'password_hash' => bcrypt($this->password),
            'status' => 'active',
        ]);
    }

    protected function student(string $name, Branch $branch): Student
    {
        return Student::create([
            'institute_id' => $this->institute->id,
            'branch_id' => $branch->id,
            'student_id_number' => 'SID'.mt_rand(10000, 99999),
            'first_name' => $name,
            'last_name' => 'Student',
            'status' => 'active',
            'admission_date' => now(),
        ]);
    }

    protected function batch(Branch $branch): Batch
    {
        return Batch::create([
            'institute_id' => $this->institute->id,
            'branch_id' => $branch->id,
            'course_id' => $this->course->id,
            'name' => 'Batch of '.$branch->name,
            'batch_code' => 'BB'.mt_rand(1000, 9999),
            'start_date' => now(),
        ]);
    }

    public function test_branch_scope_filters_direct_models(): void
    {
        $this->student('Alpha', $this->branchA);
        $this->student('Beta', $this->branchB);
        $this->student('Gamma', $this->branchA);

        TenantContext::set($this->institute->id);
        BranchContext::set($this->branchA->id);

        $this->assertSame(2, Student::count());

        BranchContext::clear();
        $this->assertSame(3, Student::count());
    }

    public function test_branch_scope_ignored_when_no_context(): void
    {
        $this->student('Alpha', $this->branchA);
        $this->student('Beta', $this->branchB);

        TenantContext::set($this->institute->id);

        $this->assertSame(2, Student::count());
    }

    public function test_branch_scope_filters_batches_and_transactions(): void
    {
        $this->batch($this->branchA);
        $this->batch($this->branchB);

        $head = AccountHead::create(['institute_id' => $this->institute->id, 'name' => 'Sales', 'type' => 'income', 'status' => 'active']);
        Transaction::create([
            'institute_id' => $this->institute->id,
            'branch_id' => $this->branchA->id,
            'account_head_id' => $head->id,
            'type' => 'income',
            'amount' => 100,
            'transaction_date' => Carbon::today()->toDateString(),
        ]);
        Transaction::create([
            'institute_id' => $this->institute->id,
            'branch_id' => $this->branchB->id,
            'account_head_id' => $head->id,
            'type' => 'income',
            'amount' => 200,
            'transaction_date' => Carbon::today()->toDateString(),
        ]);

        TenantContext::set($this->institute->id);
        BranchContext::set($this->branchA->id);

        $this->assertSame(1, Batch::count());
        $this->assertSame(100.0, (float) Transaction::sum('amount'));
    }

    public function test_branch_manager_sees_own_branch_only_via_web(): void
    {
        $studentA = $this->student('Alpha Only', $this->branchA);
        $studentB = $this->student('Beta Hidden', $this->branchB);

        $manager = $this->user('branch-manager', 'manager@example.test', $this->branchA->id);

        $this->actingAs($manager, 'institute_user')
            ->get('/students')
            ->assertOk()
            ->assertSee($studentA->full_name)
            ->assertDontSee($studentB->full_name);
    }

    public function test_owner_sees_all_branches_via_web(): void
    {
        $studentA = $this->student('Alpha All', $this->branchA);
        $studentB = $this->student('Beta All', $this->branchB);

        $owner = $this->user('institute-owner', 'owner@example.test');

        $this->actingAs($owner, 'institute_user')
            ->get('/students')
            ->assertOk()
            ->assertSee($studentA->full_name)
            ->assertSee($studentB->full_name);
    }

    public function test_ai_context_carries_actor_branch(): void
    {
        $manager = $this->user('branch-manager', 'ai-branch@example.test', $this->branchA->id);

        $ctx = AiContext::resolve($manager, $this->institute);

        $this->assertSame($this->branchA->id, $ctx->branchId);

        $owner = $this->user('institute-owner', 'ai-branch-owner@example.test');
        $this->assertNull(AiContext::resolve($owner, $this->institute)->branchId);
    }

    public function test_students_tool_respects_branch_via_context(): void
    {
        $studentA = $this->student('Alpha Tool', $this->branchA);
        $this->student('Beta Tool', $this->branchB);

        $manager = $this->user('branch-manager', 'ai-branch-tool@example.test', $this->branchA->id);
        $ctx = AiContext::resolve($manager, $this->institute);
        TenantContext::set($this->institute->id);

        $result = (new StudentsTool)->handle([], $ctx);

        $this->assertSame(1, $result['total']);
        $this->assertStringContainsString($studentA->full_name, json_encode($result['rows']));

        $owner = $this->user('institute-owner', 'ai-branch-all@example.test');
        $ownerCtx = AiContext::resolve($owner, $this->institute);
        $this->assertSame(2, (new StudentsTool)->handle([], $ownerCtx)['total']);
    }

    public function test_batches_tool_respects_branch_via_context(): void
    {
        $this->batch($this->branchA);
        $this->batch($this->branchB);

        $manager = $this->user('branch-manager', 'ai-batch-branch@example.test', $this->branchA->id);
        $ctx = AiContext::resolve($manager, $this->institute);
        TenantContext::set($this->institute->id);

        $this->assertSame(1, (new BatchesTool)->handle([], $ctx)['total_batches']);
    }

    public function test_income_expense_tool_respects_branch_via_context(): void
    {
        $head = AccountHead::create(['institute_id' => $this->institute->id, 'name' => 'Sales', 'type' => 'income', 'status' => 'active']);
        Transaction::create([
            'institute_id' => $this->institute->id,
            'branch_id' => $this->branchA->id,
            'account_head_id' => $head->id,
            'type' => 'income',
            'amount' => 100,
            'transaction_date' => Carbon::today()->toDateString(),
        ]);
        Transaction::create([
            'institute_id' => $this->institute->id,
            'branch_id' => $this->branchB->id,
            'account_head_id' => $head->id,
            'type' => 'income',
            'amount' => 900,
            'transaction_date' => Carbon::today()->toDateString(),
        ]);

        $manager = $this->user('branch-manager', 'ai-fin-branch@example.test', $this->branchA->id);
        $ctx = AiContext::resolve($manager, $this->institute);
        TenantContext::set($this->institute->id);

        $result = (new IncomeExpenseTool)->handle([], $ctx);

        $this->assertSame(100.0, $result['total_income']);
        $this->assertSame(1, $result['total_transactions']);
    }

    public function test_attendance_tool_respects_branch_via_student(): void
    {
        $studentA = $this->student('Alpha Att', $this->branchA);
        $studentB = $this->student('Beta Att', $this->branchB);
        $batchA = $this->batch($this->branchA);
        $batchB = $this->batch($this->branchB);

        foreach ([[$studentA, $batchA], [$studentB, $batchB]] as [$student, $batch]) {
            Attendance::create([
                'institute_id' => $this->institute->id,
                'batch_id' => $batch->id,
                'student_id' => $student->id,
                'class_date' => Carbon::today(),
                'status' => 'present',
            ]);
        }

        $manager = $this->user('branch-manager', 'ai-att-branch@example.test', $this->branchA->id);
        $ctx = AiContext::resolve($manager, $this->institute);
        TenantContext::set($this->institute->id);

        $result = (new AttendanceTool)->handle([], $ctx);

        $this->assertSame(1, $result['total_records']);
    }
}

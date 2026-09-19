<?php

namespace Database\Seeders;

use App\Models\ApprovalWorkflow;
use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

/**
 * Seeds a default expense approval workflow for one institute.
 * created_by resolves from the institute's first user to satisfy
 * the approval_workflows_created_by FK (NULL when no user exists).
 * Idempotent (firstOrCreate on institute+name). No-op without institute.
 */
class ApprovalWorkflowSeeder extends Seeder
{
    public function __construct(protected ?int $instituteId = null) {}

    public function run(): void
    {
        if ($this->instituteId === null) {
            return;
        }

        $institute = Institute::find($this->instituteId);
        if ($institute === null) {
            return;
        }

        $creatorId = InstituteUser::where('institute_id', $institute->id)->min('id');

        $workflow = ApprovalWorkflow::firstOrCreate(
            ['institute_id' => $institute->id, 'name' => 'Default Expense Workflow'],
            [
                'module' => 'expense',
                'amount_from' => 0,
                'amount_to' => 999999,
                'is_active' => true,
                'created_by' => $creatorId,
            ]
        );

        if (Schema::hasTable('approval_steps') && $workflow->steps()->count() === 0) {
            $managerId = Role::where('slug', 'branch-manager')->whereNull('institute_id')->value('id');
            $adminId = Role::where('slug', 'institute-admin')->whereNull('institute_id')->value('id');
            $workflow->steps()->createMany([
                ['institute_id' => $institute->id, 'step_order' => 1, 'approver_role_id' => $managerId],
                ['institute_id' => $institute->id, 'step_order' => 2, 'approver_role_id' => $adminId],
            ]);
        }
    }
}

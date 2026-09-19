<?php

namespace Database\Factories;

use App\Models\ApprovalWorkflow;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ApprovalWorkflow>
 */
class ApprovalWorkflowFactory extends Factory
{
    protected $model = ApprovalWorkflow::class;

    public function definition(): array
    {
        return [
            'institute_id' => 1,
            'name' => 'Workflow ' . $this->faker->unique()->words(2, true),
            'module' => $this->faker->randomElement(['expense', 'purchase', 'payment', 'journal_adjustment']),
            'amount_from' => 0,
            'amount_to' => 999999,
            'is_active' => true,
            // created_by is a nullable FK to institute_users: pass an
            // institute user id explicitly, or resolve it like
            // TestCase::seedApprovalWorkflows() does.
            'created_by' => null,
        ];
    }

    public function createdBy(int $instituteUserId): static
    {
        return $this->state(fn () => ['created_by' => $instituteUserId]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}

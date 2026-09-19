<?php

namespace Database\Factories;

use App\Models\FiscalYear;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FiscalYear>
 */
class FiscalYearFactory extends Factory
{
    protected $model = FiscalYear::class;

    public function definition(): array
    {
        $year = (int) date('Y');

        return [
            'institute_id' => 1,
            'branch_id' => null,
            'name' => 'FY ' . $year . '-' . strtoupper($this->faker->unique()->lexify('???')),
            'start_date' => sprintf('%d-01-01', $year),
            'end_date' => sprintf('%d-12-31', $year),
            'status' => 'open',
            'is_current' => false,
            'closed_by' => null,
            'closed_at' => null,
            'created_by' => null,
            'updated_by' => null,
        ];
    }

    public function current(): static
    {
        return $this->state(fn () => ['is_current' => true, 'status' => 'open']);
    }

    public function closed(): static
    {
        return $this->state(fn () => ['status' => 'closed', 'is_current' => false, 'closed_at' => now()]);
    }
}

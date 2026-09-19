<?php

namespace Database\Factories;

use App\Models\PaymentMethod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaymentMethod>
 */
class PaymentMethodFactory extends Factory
{
    protected $model = PaymentMethod::class;

    public function definition(): array
    {
        return [
            'institute_id' => 1,
            'branch_id' => null,
            'name' => ucfirst($this->faker->unique()->word()) . ' Pay',
            'coa_id' => null,
            'is_system' => false,
            'is_active' => true,
            'created_by' => null,
            'updated_by' => null,
        ];
    }

    public function system(): static
    {
        return $this->state(fn () => ['is_system' => true]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}

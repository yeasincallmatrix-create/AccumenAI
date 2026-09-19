<?php

namespace Database\Factories;

use App\Models\Party;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Party>
 */
class PartyFactory extends Factory
{
    protected $model = Party::class;

    public function definition(): array
    {
        return [
            'institute_id' => 1,
            'branch_id' => null,
            'type' => $this->faker->randomElement(['customer', 'supplier']),
            'customer_group_id' => null,
            'name' => $this->faker->company(),
            'phone' => $this->faker->unique()->numerify('01#########'),
            'email' => $this->faker->unique()->safeEmail(),
            'address' => $this->faker->address(),
            'tin' => null,
            'billing_currency_id' => null,
            'credit_limit' => null,
            'is_active' => true,
            'party_meta' => null,
            'created_by' => null,
            'updated_by' => null,
        ];
    }

    public function customer(): static
    {
        return $this->state(fn () => ['type' => 'customer']);
    }

    public function supplier(): static
    {
        return $this->state(fn () => ['type' => 'supplier']);
    }
}

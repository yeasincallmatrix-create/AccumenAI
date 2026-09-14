<?php

namespace Database\Factories\Medical;

use App\Models\Medical\Prescription;
use Illuminate\Database\Eloquent\Factories\Factory;

class PrescriptionFactory extends Factory
{
    protected $model = Prescription::class;

    public function definition(): array
    {
        return [
            'institute_id'       => 1,
            'patient_id'         => 1,
            'doctor_id'          => 1,
            'prescription_number' => 'RX-' . strtoupper(uniqid()),
            'prescription_date'  => $this->faker->dateTimeThisYear(),
            'diagnosis'          => $this->faker->sentence(3),
            'is_finalized'       => 0,
            'version'            => 1,
            'parent_prescription_id' => null,
            'amendment_reason'   => null,
            'amended_by'         => null,
            'amended_at'         => null,
        ];
    }

    public function finalized(): static
    {
        return $this->state(fn () => ['is_finalized' => 1]);
    }

    public function draft(): static
    {
        return $this->state(fn () => ['is_finalized' => 0]);
    }

    public function version(int $v): static
    {
        return $this->state(fn () => ['version' => $v]);
    }

    public function amended(string $reason = 'Dose adjustment needed'): static
    {
        return $this->state(fn () => [
            'amendment_reason' => $reason,
            'amended_by'       => 1,
            'amended_at'       => now(),
        ]);
    }
}

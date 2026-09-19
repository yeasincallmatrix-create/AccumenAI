<?php

namespace Database\Factories;

use App\Models\Invoice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;

    public function definition(): array
    {
        $total = $this->faker->randomFloat(2, 100, 50000);
        $discount = $this->faker->randomFloat(2, 0, $total / 2);
        $payable = round($total - $discount, 2);

        return [
            'institute_id' => 1,
            'student_id' => null,
            'party_id' => null,
            'enrollment_id' => null,
            'invoice_number' => 'INV-' . date('Y') . '-' . $this->faker->unique()->numberBetween(1000, 999999),
            'invoice_type' => 'course_fee',
            'total_amount' => $total,
            'discount' => $discount,
            'payable_amount' => $payable,
            'paid_amount' => 0,
            'due_amount' => $payable,
            'status' => 'unpaid',
            'due_date' => now()->addDays(30)->toDateString(),
            'currency_id' => null,
            'exchange_rate' => null,
            'base_payable_amount' => null,
            'tax_group_id' => null,
            'journal_id' => null,
            'sales_order_id' => null,
            'sales_delivery_id' => null,
            'invoice_meta' => null,
            'created_by' => null,
        ];
    }

    public function paid(): static
    {
        return $this->state(function (array $attributes) {
            $payable = $attributes['payable_amount'] ?? 0;

            return ['paid_amount' => $payable, 'due_amount' => 0, 'status' => 'paid'];
        });
    }

    public function partial(float $paidAmount): static
    {
        return $this->state(function (array $attributes) use ($paidAmount) {
            $payable = $attributes['payable_amount'] ?? 0;

            return [
                'paid_amount' => $paidAmount,
                'due_amount' => round($payable - $paidAmount, 2),
                'status' => 'partial',
            ];
        });
    }
}

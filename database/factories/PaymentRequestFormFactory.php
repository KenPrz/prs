<?php

namespace Database\Factories;

use App\Enums\PaymentRequestFormStatus;
use App\Models\PaymentRequestForm;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaymentRequestForm>
 */
class PaymentRequestFormFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'requestor_id' => User::factory(),
            'supplier_id' => Supplier::factory(),
            'description' => fake()->sentence(),
            'amount' => fake()->randomFloat(2, 1000, 500000),
            'invoice_number' => fake()->optional()->numerify('INV-####'),
            'due_date' => fake()->optional()->dateTimeBetween('now', '+60 days'),
            'stamp_date' => fake()->optional()->dateTimeBetween('-30 days', 'now'),
            'status' => PaymentRequestFormStatus::DRAFT,
        ];
    }

    /**
     * Indicate that the PRF is in reviewing status.
     */
    public function reviewing(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PaymentRequestFormStatus::REVIEWING,
        ]);
    }

    /**
     * Indicate that the PRF is approved.
     */
    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PaymentRequestFormStatus::APPROVED,
        ]);
    }

    /**
     * Indicate that the PRF is rejected.
     */
    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PaymentRequestFormStatus::REJECTED,
        ]);
    }
}

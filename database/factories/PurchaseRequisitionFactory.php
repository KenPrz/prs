<?php

namespace Database\Factories;

use App\Enums\PurchaseRequisitionStatus;
use App\Models\PurchaseRequisition;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseRequisition>
 */
class PurchaseRequisitionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'number' => fake()->unique()->numerify('PR-####'),
            'title' => fake()->sentence(3),
            'description' => fake()->paragraph(),
            'status' => PurchaseRequisitionStatus::DRAFT->value,
            'created_by' => User::inRandomOrder()->first()->id,
        ];
    }
}

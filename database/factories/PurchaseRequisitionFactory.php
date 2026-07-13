<?php

namespace Database\Factories;

use App\Enums\PriceType;
use App\Enums\PurchaseRequisitionStatus;
use App\Enums\PurposeType;
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
            'requestor_id' => User::factory(),
            'title' => fake()->sentence(3),
            'description' => fake()->optional()->paragraph(),
            'status' => PurchaseRequisitionStatus::DRAFT,
            'price_type' => fake()->randomElement(PriceType::cases())->value,
            'purpose_type' => fake()->randomElement(PurposeType::cases())->value,
            'expected_useful_life' => fake()->optional()->numerify('# years'),
        ];
    }
}

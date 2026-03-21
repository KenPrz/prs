<?php

namespace Database\Factories;

use App\Models\LineItem;
use App\Models\LineItemUnit;
use App\Models\PurchaseRequisition;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LineItem>
 */
class LineItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {

        $names = json_decode(file_get_contents(database_path('seeders/data/line-items.json')), true);

        return [
            // Lazy: only persists a PR when pr_id is not overridden (e.g. in PurchaseRequisitionSeeder).
            'pr_id' => PurchaseRequisition::factory(),
            'code' => fake()->unique()->numerify('PR-LI-####'),
            'unit_id' => LineItemUnit::inRandomOrder()->first()->id,
            'name' => fake()->randomElement($names),
            'description' => fake()->sentence(),
            'quantity' => fake()->numberBetween(1, 250),
            'price' => fake()->randomFloat(2, 1, 10000),
        ];
    }
}

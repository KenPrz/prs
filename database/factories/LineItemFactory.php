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
            'pr_id' => PurchaseRequisition::factory(),
            'unit_id' => LineItemUnit::inRandomOrder()->first()->id,
            'name' => fake()->randomElement($names),
            'description' => fake()->sentence(),
            'quantity' => fake()->numberBetween(1, 250),
            'price' => fake()->randomFloat(2, 1, 10000),
        ];
    }
}

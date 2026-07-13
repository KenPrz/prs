<?php

namespace Database\Factories;

use App\Models\CompanyProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CompanyProfile>
 */
class CompanyProfileFactory extends Factory
{
    protected $model = CompanyProfile::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'mobile_no' => fake()->phoneNumber(),
            'email' => fake()->optional()->companyEmail(),
            'tin' => fake()->numerify('###-###-###-#####'),
            'currency' => 'PESO',
        ];
    }
}

<?php

namespace Database\Factories;

use App\Models\Address;
use App\Models\CompanyProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Address>
 */
class AddressFactory extends Factory
{
    protected $model = Address::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_profile_id' => CompanyProfile::factory(),
            'recipient_name' => fake()->name(),
            'street' => fake()->streetAddress(),
            'barangay' => fake()->optional()->city(),
            'city' => fake()->city(),
            'province' => fake()->state(),
            'zip_code' => fake()->postcode(),
            'mobile_no' => fake()->phoneNumber(),
        ];
    }
}

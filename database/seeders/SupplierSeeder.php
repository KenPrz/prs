<?php

namespace Database\Seeders;

use App\Models\Supplier;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class SupplierSeeder extends Seeder
{
    public function run(): void
    {
        $companyNames = [
            'Acme Industrial Supply Co.',
            'Blue Harbor Trading',
            'Cedar & Co. Manufacturing',
            'Nimbus Components Ltd.',
            'Northbridge Materials',
            'Orchid Paper & Packaging',
            'Pioneer Fasteners',
            'Redwood Safety Solutions',
            'Summit Tools & Equipment',
            'Veridian Logistics Partners',
            'Westlake Chemicals',
            'Zenith Office Furnishings',
        ];

        foreach ($companyNames as $name) {
            $slug = Str::slug($name);
            $email = $slug === '' ? fake()->unique()->safeEmail() : "{$slug}@example.test";

            Supplier::query()->updateOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'phone' => fake()->phoneNumber(),
                    'address' => fake()->address(),
                    'contact_person_1' => fake()->name(),
                    'contact_person_2' => fake()->boolean(60) ? fake()->name() : null,
                    'contact_person_3' => fake()->boolean(35) ? fake()->name() : null,
                ],
            );
        }
    }
}

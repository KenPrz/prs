<?php

namespace Database\Seeders;

use App\Models\Department;
use Illuminate\Database\Seeder;

class DepartmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $departments = json_decode(file_get_contents(database_path('seeders/data/departments.json')), true);

        foreach ($departments as $department) {
            Department::create([
                'name' => $department['name'],
                'code' => $department['code'],
                'description' => fake()->paragraph(),
            ]);
        }
    }
}

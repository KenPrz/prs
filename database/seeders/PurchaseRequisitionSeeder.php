<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\LineItem;
use App\Models\PurchaseRequisition;
use App\Models\User;
use Illuminate\Database\Seeder;

class PurchaseRequisitionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $pr = PurchaseRequisition::factory()->count(250)->create([
            'created_by' => User::inRandomOrder()->first()->id,
        ]);

        $departments = Department::inRandomOrder()->take(rand(1, 3))->pluck('id');

        foreach ($pr as $p) {
            LineItem::factory()->count(rand(50, 250))->create([
                'pr_id' => $p->id,
            ]);

            $p->requestingDepartments()->sync($departments);
        }
    }
}

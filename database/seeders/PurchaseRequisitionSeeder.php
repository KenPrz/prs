<?php

namespace Database\Seeders;

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
        $pr = PurchaseRequisition::factory()->count(10)->create([
            'created_by' => User::inRandomOrder()->first()->id,
        ]);

        foreach ($pr as $p) {
            LineItem::factory()->count(rand(1, 30))->create([
                'pr_id' => $p->id,
            ]);
        }
    }
}

<?php

namespace Database\Seeders;

use App\Models\LineItemUnit;
use Illuminate\Database\Seeder;

class LineItemUnitSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $lineItemUnits = [
            // Default unit
            ['name' => 'default', 'code' => '-'],

            // Basic counts
            ['name' => 'Piece', 'code' => 'pc'],
            ['name' => 'Dozen', 'code' => 'dz'],
            ['name' => 'Set', 'code' => 'set'],

            // Packaging units
            ['name' => 'Box', 'code' => 'bx'],
            ['name' => 'Carton', 'code' => 'ctn'],
            ['name' => 'Pack', 'code' => 'pk'],
            ['name' => 'Packet', 'code' => 'pkt'],
            ['name' => 'Bag', 'code' => 'bag'],
            ['name' => 'Pallet', 'code' => 'plt'],
            ['name' => 'Bundle', 'code' => 'bndl'],
            ['name' => 'Crate', 'code' => 'crate'],

            // Bottles / Cans / Jars
            ['name' => 'Bottle', 'code' => 'btl'],
            ['name' => 'Can', 'code' => 'can'],
            ['name' => 'Jar', 'code' => 'jar'],
            ['name' => 'Flask', 'code' => 'flsk'],
            ['name' => 'Vial', 'code' => 'vl'],

            // Sheets / Rolls / Tubes
            ['name' => 'Sheet', 'code' => 'sh'],
            ['name' => 'Roll', 'code' => 'roll'],
            ['name' => 'Tube', 'code' => 'tube'],

            // Weight-based (common for raw materials)
            ['name' => 'Gram', 'code' => 'g'],
            ['name' => 'Kilogram', 'code' => 'kg'],
            ['name' => 'Pound', 'code' => 'lb'],
            ['name' => 'Ounce', 'code' => 'oz'],

            // Volume-based (liquids)
            ['name' => 'Milliliter', 'code' => 'ml'],
            ['name' => 'Liter', 'code' => 'l'],
            ['name' => 'Gallon', 'code' => 'gal'],

            // Misc practical units
            ['name' => 'Piece per Box', 'code' => 'pc/bx'],
            ['name' => 'Piece per Pack', 'code' => 'pc/pk'],
            ['name' => 'Bundle of 10', 'code' => 'bndl10'],
            ['name' => 'Sheet per Pack', 'code' => 'sh/pk'],
            ['name' => 'Roll per Pack', 'code' => 'roll/pk'],
        ];

        foreach ($lineItemUnits as $lineItemUnit) {
            LineItemUnit::create($lineItemUnit);
        }
    }
}

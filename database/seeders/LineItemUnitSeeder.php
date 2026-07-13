<?php

namespace Database\Seeders;

use App\Models\ItemUnit;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

class LineItemUnitSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $path = database_path('seeders/data/line-item-units.json');
        $units = json_decode(File::get($path), true, 512, JSON_THROW_ON_ERROR);

        foreach ($units as $unit) {
            ItemUnit::query()->updateOrCreate(
                ['code' => $unit['code']],
                ['name' => $unit['name']]
            );
        }
    }
}

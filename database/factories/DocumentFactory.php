<?php

namespace Database\Factories;

use App\Models\Document;
use App\Models\PurchaseOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Document>
 */
class DocumentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'resource_class' => PurchaseOrder::class,
            'blade_file' => 'documents.purchase-order',
        ];
    }
}

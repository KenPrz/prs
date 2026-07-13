<?php

namespace Database\Factories;

use App\Enums\PriceType;
use App\Enums\PurchaseOrderStatus;
use App\Models\Address;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequisition;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseOrder>
 */
class PurchaseOrderFactory extends Factory
{
    protected $model = PurchaseOrder::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'purchase_requisition_id' => PurchaseRequisition::factory(),
            'supplier_id' => Supplier::factory(),
            'expected_delivery_date' => fake()->dateTimeBetween('+1 week', '+3 months'),
            'status' => PurchaseOrderStatus::DRAFT,
            'bill_to_id' => Address::factory(),
            'ship_to_id' => Address::factory(),
            'payment_terms' => fake()->randomElement(['Net 30', 'Net 60', 'Cash on Delivery', 'Prepaid']),
            'currency' => fake()->randomElement(['PHP', 'USD', 'JPY']),
            'price_type' => fake()->randomElement(PriceType::cases())->value,
        ];
    }

    /**
     * Indicate that the purchase order is approved.
     */
    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PurchaseOrderStatus::APPROVED,
        ]);
    }

    /**
     * Indicate that the purchase order has been released.
     */
    public function released(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PurchaseOrderStatus::RELEASED,
        ]);
    }

    /**
     * Indicate that the purchase order has been fully received.
     */
    public function fullyReceived(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PurchaseOrderStatus::FULLY_RECEIVED,
        ]);
    }
}

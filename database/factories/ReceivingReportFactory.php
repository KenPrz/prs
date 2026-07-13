<?php

namespace Database\Factories;

use App\Enums\ReceivingReportStatus;
use App\Models\PurchaseOrder;
use App\Models\ReceivingReport;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReceivingReport>
 */
class ReceivingReportFactory extends Factory
{
    protected $model = ReceivingReport::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'purchase_order_id' => PurchaseOrder::factory(),
            'received_by_id' => User::factory(),
            'received_date' => fake()->date(),
            'status' => ReceivingReportStatus::PENDING,
        ];
    }

    /**
     * Indicate that the receiving report has been verified.
     */
    public function verified(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ReceivingReportStatus::VERIFIED,
        ]);
    }
}

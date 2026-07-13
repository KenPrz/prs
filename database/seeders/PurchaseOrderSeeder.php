<?php

namespace Database\Seeders;

use App\Models\Address;
use App\Models\Document;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequisition;
use App\Models\Supplier;
use App\Services\PurchaseOrderService;
use Illuminate\Database\Seeder;

class PurchaseOrderSeeder extends Seeder
{
    public function run(): void
    {
        $supplierIds = Supplier::query()->pluck('id');
        if ($supplierIds->isEmpty()) {
            return;
        }

        // Nothing seeds addresses, so fall back to creating a few when none exist.
        $addressIds = Address::query()->pluck('id');
        if ($addressIds->isEmpty()) {
            $addressIds = Address::factory()->count(3)->create()->pluck('id');
        }

        $poDocument = Document::query()
            ->where('resource_class', PurchaseOrder::class)
            ->where('blade_file', 'documents.purchase-order')
            ->first();

        $service = app(PurchaseOrderService::class);

        PurchaseRequisition::query()->with('lineItems')->orderBy('id')->each(function (PurchaseRequisition $requisition) use ($supplierIds, $addressIds, $poDocument, $service): void {
            if ($requisition->purchaseOrders()->exists()) {
                return;
            }

            $purchaseOrder = PurchaseOrder::query()->create([
                'purchase_requisition_id' => $requisition->id,
                'supplier_id' => $supplierIds->random(),
                'bill_to_id' => $addressIds->random(),
                'ship_to_id' => $addressIds->random(),
                'payment_terms' => fake()->randomElement(['Net 30', 'Net 60', 'Cash on Delivery']),
                'currency' => fake()->randomElement(['PHP', 'USD', 'JPY']),
                'expected_delivery_date' => now()->addWeeks(fake()->numberBetween(1, 8)),
                'status' => 'DRAFT',
                'document_id' => $poDocument?->id,
                'price_type' => $requisition->price_type->value,
            ]);

            // Allocate the full PR line items so the PR's fulfillment status reflects a real order.
            $purchaseOrder->items()->createMany(
                $requisition->lineItems->map(fn ($lineItem) => [
                    'line_item_id' => $lineItem->id,
                    'quantity' => $lineItem->quantity,
                    'unit_id' => $lineItem->unit_id,
                    'price' => $lineItem->price,
                ])->all(),
            );

            $service->syncPurchaseRequisitionFulfillmentStatus($requisition);
        });
    }
}

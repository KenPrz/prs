<?php

namespace Database\Seeders;

use App\Enums\PriceType;
use App\Enums\PurchaseRequisitionStatus;
use App\Models\Document;
use App\Models\ItemUnit;
use App\Models\LineItem;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequisition;
use App\Models\User;
use App\Models\WorkflowDefinition;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PurchaseRequisitionSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(DocumentSeeder::class);

        DB::transaction(function () {
            $workflow = WorkflowDefinition::query()
                ->where('key', 'pr.default')
                ->where('document_type', 'PR')
                ->where('is_active', true)
                ->firstOrFail();

            $poDocument = Document::query()
                ->where('resource_class', PurchaseOrder::class)
                ->where('blade_file', 'documents.purchase-order')
                ->firstOrFail();

            $prDocument = Document::query()
                ->where('resource_class', PurchaseRequisition::class)
                ->where('blade_file', 'documents.purchase-requisition')
                ->firstOrFail();

            $requestorId = User::query()->value('id') ?? User::factory()->create()->id;

            $units = ItemUnit::query()->pluck('id');
            $priceTypes = array_map(static fn (PriceType $type) => $type->value, PriceType::cases());

            // Seed PRs already approved and marked ready for PO so the PO seeder can order against them.
            // ponytail: status only — the real final-document PDF snapshot is produced by the mark-ready action
            // (needs Carbone) and is skipped here to keep seeding dependency-free.
            $purchaseRequisitions = PurchaseRequisition::factory()
                ->count(5)
                ->create([
                    'requestor_id' => $requestorId,
                    'to_be_ordered_by_id' => $requestorId,
                    'workflow_id' => $workflow->id,
                    'status' => PurchaseRequisitionStatus::READY_FOR_PO,
                    'document_id' => $poDocument->id,
                    'requisition_document_id' => $prDocument->id,
                ]);

            foreach ($purchaseRequisitions as $purchaseRequisition) {
                $purchaseRequisition->update(['price_type' => fake()->randomElement($priceTypes)]);

                $lineItemsCount = fake()->numberBetween(2, 3);

                for ($i = 0; $i < $lineItemsCount; $i++) {
                    LineItem::query()->create([
                        'purchase_requisition_id' => $purchaseRequisition->id,
                        'name' => fake()->words(3, true),
                        'quantity' => fake()->numberBetween(1, 25),
                        'unit_id' => $units->random(),
                        'price' => fake()->randomFloat(2, 10, 5000),
                    ]);
                }
            }
        });
    }
}

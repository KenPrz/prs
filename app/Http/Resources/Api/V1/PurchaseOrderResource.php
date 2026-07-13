<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'po_number' => $this->po_number,
            'status' => $this->status->value,
            'expected_delivery_date' => $this->expected_delivery_date?->toDateString(),
            'payment_terms' => $this->payment_terms,
            'currency' => $this->currency,
            'price_type' => $this->price_type->value,
            'net_total' => $this->whenLoaded('items', fn () => $this->net_total),
            'gross_total' => $this->whenLoaded('items', fn () => $this->gross_total),
            'vat_total' => $this->whenLoaded('items', fn () => $this->vat_total),
            'remarks' => $this->remarks,
            'terms_and_conditions' => $this->terms_and_conditions,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'supplier' => new SupplierResource($this->whenLoaded('supplier')),
            'purchase_requisition' => new PurchaseRequisitionResource($this->whenLoaded('purchaseRequisition')),
            'items' => PurchaseOrderItemResource::collection($this->whenLoaded('items')),
            'workflow_instance' => new WorkflowInstanceResource($this->whenLoaded('workflowInstance')),
            'attachments' => $this->when(
                $this->resource->relationLoaded('media') || $this->getMedia('attachments')->isNotEmpty(),
                fn () => $this->getMedia('attachments')->map(fn ($m) => [
                    'id' => $m->id,
                    'name' => $m->file_name,
                    'url' => route('attachments.download', $m),
                    'mime_type' => $m->mime_type,
                    'size' => $m->size,
                ])
            ),
        ];
    }
}

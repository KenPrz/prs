<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReceivingReportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'rr_number' => $this->rr_number,
            'status' => $this->status->value,
            'received_date' => $this->received_date?->toDateString(),
            'remarks' => $this->remarks,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'received_by' => new UserResource($this->whenLoaded('receivedBy')),
            'purchase_order' => new PurchaseOrderResource($this->whenLoaded('purchaseOrder')),
            'items' => ReceivingReportItemResource::collection($this->whenLoaded('items')),
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

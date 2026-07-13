<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseRequisitionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'pr_number' => $this->pr_number,
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status->value,
            'delivery_date' => $this->delivery_date?->toDateString(),
            'price_type' => $this->price_type->value,
            'net_total' => $this->whenLoaded('lineItems', fn () => $this->net_total),
            'gross_total' => $this->whenLoaded('lineItems', fn () => $this->gross_total),
            'vat_total' => $this->whenLoaded('lineItems', fn () => $this->vat_total),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'requestor' => new UserResource($this->whenLoaded('requestor')),
            'departments' => DepartmentResource::collection($this->whenLoaded('departments')),
            'line_items' => LineItemResource::collection($this->whenLoaded('lineItems')),
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

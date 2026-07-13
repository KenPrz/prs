<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentRequestFormResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'prf_number' => $this->prf_number,
            'status' => $this->status->value,
            'description' => $this->description,
            'amount' => $this->amount,
            'invoice_number' => $this->invoice_number,
            'due_date' => $this->due_date?->toDateString(),
            'stamp_date' => $this->stamp_date?->toDateString(),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'requestor' => new UserResource($this->whenLoaded('requestor')),
            'supplier' => new SupplierResource($this->whenLoaded('supplier')),
            'departments' => DepartmentResource::collection($this->whenLoaded('departments')),
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

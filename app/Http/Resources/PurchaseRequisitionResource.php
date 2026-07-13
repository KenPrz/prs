<?php

namespace App\Http\Resources;

use App\Models\PurchaseRequisition;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PurchaseRequisition
 */
class PurchaseRequisitionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'details' => [
                'id' => $this->id,
                'status' => $this->status->value,
                'delivery_date' => $this->delivery_date?->toDateString(),
                'requestor_id' => $this->requestor_id,
                'workflow_id' => $this->workflow_id,
                'workflow_instance_id' => $this->workflow_instance_id,
                'created_at' => $this->created_at?->toIso8601String(),
                'updated_at' => $this->updated_at?->toIso8601String(),
            ],
            'pr_number' => $this->pr_number,
            'title' => $this->title,
            'description' => $this->description,
            'price_type' => $this->price_type->value,
            'net_total' => $this->whenLoaded('lineItems', fn () => $this->net_total),
            'gross_total' => $this->whenLoaded('lineItems', fn () => $this->gross_total),
            'vat_total' => $this->whenLoaded('lineItems', fn () => $this->vat_total),
            'requesting_departments' => $this->whenLoaded(
                'departments',
                fn (): array => $this->departments->map(fn ($department): array => [
                    'department_name' => $department->name,
                    'code' => $department->code,
                ])->values()->all(),
            ),
            'line_items' => $this->whenLoaded(
                'lineItems',
                fn () => LineItemResource::collection($this->lineItems)->toArray($request),
            ),
            'notes' => $this->whenLoaded(
                'notes',
                fn () => NoteResource::collection($this->notes)->toArray($request),
            ),
        ];
    }
}

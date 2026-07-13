<?php

namespace App\Http\Resources;

use App\Models\LineItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin LineItem
 */
class LineItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'purchase_requisition_id' => $this->purchase_requisition_id,
            'name' => $this->name,
            'quantity' => $this->quantity,
            'unit_id' => $this->unit_id,
            'price' => $this->price,
            'unit' => $this->whenLoaded('unit', fn () => [
                'id' => $this->unit->id,
                'name' => $this->unit->name,
                'code' => $this->unit->code,
            ]),
        ];
    }
}

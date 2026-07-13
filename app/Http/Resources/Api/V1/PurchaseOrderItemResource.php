<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseOrderItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'quantity' => $this->quantity,
            'price' => $this->price,
            'quantity_received' => $this->quantity_received,
            'quantity_remaining' => $this->quantity_remaining,
            'line_item' => new LineItemResource($this->whenLoaded('lineItem')),
        ];
    }
}

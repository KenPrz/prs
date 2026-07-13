<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LineItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'quantity' => $this->quantity,
            'price' => $this->price,
            'quantity_allocated' => $this->quantity_allocated,
            'quantity_unallocated' => $this->quantity_unallocated,
            'unit' => new ItemUnitResource($this->whenLoaded('unit')),
        ];
    }
}

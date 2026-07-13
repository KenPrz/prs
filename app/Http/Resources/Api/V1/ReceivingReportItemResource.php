<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReceivingReportItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'quantity_received' => $this->quantity_received,
            'quantity_rejected' => $this->quantity_rejected,
            'remarks' => $this->remarks,
            'purchase_order_item' => new PurchaseOrderItemResource($this->whenLoaded('purchaseOrderItem')),
        ];
    }
}

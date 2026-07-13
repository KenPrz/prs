<?php

namespace App\Events\Procurement;

use App\Models\PurchaseOrder;
use Illuminate\Foundation\Events\Dispatchable;

class PurchaseOrderReleased
{
    use Dispatchable;

    public function __construct(public PurchaseOrder $purchaseOrder) {}
}

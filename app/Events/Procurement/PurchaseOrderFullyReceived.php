<?php

namespace App\Events\Procurement;

use App\Models\PurchaseOrder;
use Illuminate\Foundation\Events\Dispatchable;

class PurchaseOrderFullyReceived
{
    use Dispatchable;

    public function __construct(public PurchaseOrder $purchaseOrder) {}
}

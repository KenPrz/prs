<?php

namespace App\Events\Procurement;

use App\Models\PurchaseRequisition;
use Illuminate\Foundation\Events\Dispatchable;

class PurchaseRequisitionReadyForPo
{
    use Dispatchable;

    public function __construct(public PurchaseRequisition $purchaseRequisition) {}
}

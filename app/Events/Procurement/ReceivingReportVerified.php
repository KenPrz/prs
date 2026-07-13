<?php

namespace App\Events\Procurement;

use App\Models\ReceivingReport;
use Illuminate\Foundation\Events\Dispatchable;

class ReceivingReportVerified
{
    use Dispatchable;

    public function __construct(public ReceivingReport $receivingReport) {}
}

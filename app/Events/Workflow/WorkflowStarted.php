<?php

namespace App\Events\Workflow;

use App\Models\WorkflowInstance;
use Illuminate\Foundation\Events\Dispatchable;

class WorkflowStarted
{
    use Dispatchable;

    public function __construct(public WorkflowInstance $instance) {}
}

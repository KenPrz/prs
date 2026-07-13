<?php

namespace App\Events\Workflow;

use App\Models\WorkflowAction;
use App\Models\WorkflowInstance;
use Illuminate\Foundation\Events\Dispatchable;

class WorkflowDecisionRecorded
{
    use Dispatchable;

    public function __construct(
        public WorkflowInstance $instance,
        public WorkflowAction $action,
    ) {}
}

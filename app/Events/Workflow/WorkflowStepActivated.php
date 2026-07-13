<?php

namespace App\Events\Workflow;

use App\Models\WorkflowInstance;
use App\Models\WorkflowStepInstance;
use Illuminate\Foundation\Events\Dispatchable;

class WorkflowStepActivated
{
    use Dispatchable;

    public function __construct(
        public WorkflowInstance $instance,
        public WorkflowStepInstance $step,
    ) {}
}

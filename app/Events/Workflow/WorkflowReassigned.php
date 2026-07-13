<?php

namespace App\Events\Workflow;

use App\Models\User;
use App\Models\WorkflowInstance;
use App\Models\WorkflowStepInstance;
use Illuminate\Foundation\Events\Dispatchable;

class WorkflowReassigned
{
    use Dispatchable;

    public function __construct(
        public WorkflowInstance $instance,
        public WorkflowStepInstance $step,
        public User $toUser,
    ) {}
}

<?php

namespace App\Events\Workflow;

use App\Models\WorkflowInstance;
use Illuminate\Foundation\Events\Dispatchable;

class WorkflowCancelled
{
    use Dispatchable;

    /**
     * @param  array<int, int>  $pendingUserIds  Users whose assignments were still pending when cancelled.
     */
    public function __construct(
        public WorkflowInstance $instance,
        public array $pendingUserIds,
    ) {}
}

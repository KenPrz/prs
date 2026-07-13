<?php

namespace App\Enums;

enum WorkflowStepStatus: string
{
    /** Snapshotted but not yet the current step. */
    case Pending = 'PENDING';

    /** The current step awaiting its participants' decisions. */
    case Active = 'ACTIVE';

    /** Completed via approval. */
    case Approved = 'APPROVED';

    /** Completed via the receiver acknowledging (Receive step). */
    case Received = 'RECEIVED';

    /** A participant rejected; the step (and workflow) failed. */
    case Rejected = 'REJECTED';

    /** Bypassed because its condition was not met. */
    case Skipped = 'SKIPPED';

    /** All pending assignments overridden by a Super Admin. */
    case Overridden = 'OVERRIDDEN';

    /** Terminated due to a downstream rejection or cancellation. */
    case Stopped = 'STOPPED';

    /** Whether the step has finished (no longer actionable). */
    public function isTerminal(): bool
    {
        return $this !== self::Pending && $this !== self::Active;
    }

    /** Whether the step completed successfully. */
    public function isComplete(): bool
    {
        return $this === self::Approved || $this === self::Received || $this === self::Overridden;
    }
}

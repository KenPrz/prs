<?php

namespace App\Policies;

use App\Enums\ReceivingReportStatus;
use App\Models\ReceivingReport;
use App\Models\User;

class ReceivingReportPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('rr.view');
    }

    public function view(User $user, ReceivingReport $receivingReport): bool
    {
        return $user->can('rr.view');
    }

    public function create(User $user): bool
    {
        return $user->can('rr.prepare');
    }

    public function update(User $user, ReceivingReport $receivingReport): bool
    {
        return $user->can('rr.prepare')
            && $receivingReport->status === ReceivingReportStatus::DRAFT;
    }

    public function delete(User $user, ReceivingReport $receivingReport): bool
    {
        return $user->can('rr.prepare')
            && $receivingReport->status === ReceivingReportStatus::DRAFT;
    }

    public function submit(User $user, ReceivingReport $receivingReport): bool
    {
        return $user->can('rr.prepare')
            && $receivingReport->status === ReceivingReportStatus::DRAFT
            && $receivingReport->workflow_instance_id === null;
    }

    /**
     * Allow a preparer to cancel an RR mid verification flow.
     */
    public function cancelWorkflow(User $user, ReceivingReport $receivingReport): bool
    {
        return $user->can('rr.cancel')
            && $receivingReport->status === ReceivingReportStatus::PENDING;
    }

    /**
     * Coarse gate only: step assignment (enforced by the engine) decides who may act.
     */
    public function decide(User $user, ReceivingReport $receivingReport): bool
    {
        return $receivingReport->workflow_instance_id !== null;
    }
}

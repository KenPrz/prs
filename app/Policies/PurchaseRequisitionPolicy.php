<?php

namespace App\Policies;

use App\Enums\PurchaseRequisitionStatus;
use App\Models\PurchaseRequisition;
use App\Models\User;

class PurchaseRequisitionPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('pr.view');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, PurchaseRequisition $purchaseRequisition): bool
    {
        return $user->can('pr.view');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('pr.prepare');
    }

    /**
     * Determine whether the user can update the model. The owning preparer only,
     * while the requisition is still a draft.
     */
    public function update(User $user, PurchaseRequisition $purchaseRequisition): bool
    {
        return $user->can('pr.prepare')
            && $user->id === $purchaseRequisition->requestor_id
            && $purchaseRequisition->status === PurchaseRequisitionStatus::DRAFT;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, PurchaseRequisition $purchaseRequisition): bool
    {
        return $user->can('pr.prepare')
            && $user->id === $purchaseRequisition->requestor_id
            && $purchaseRequisition->status === PurchaseRequisitionStatus::DRAFT;
    }

    /**
     * Allow the owning preparer to submit a draft PR into the approval workflow.
     */
    public function submit(User $user, PurchaseRequisition $purchaseRequisition): bool
    {
        return $user->can('pr.prepare')
            && $user->id === $purchaseRequisition->requestor_id
            && $purchaseRequisition->status === PurchaseRequisitionStatus::DRAFT;
    }

    /**
     * Allow the owning preparer to cancel their PR mid approval flow.
     */
    public function cancelWorkflow(User $user, PurchaseRequisition $purchaseRequisition): bool
    {
        return $user->can('pr.cancel')
            && $user->id === $purchaseRequisition->requestor_id
            && $purchaseRequisition->status === PurchaseRequisitionStatus::REVIEWING;
    }

    /**
     * Allow omitting/restoring a line item while the requisition is still being ordered.
     */
    public function omitLineItem(User $user, PurchaseRequisition $purchaseRequisition): bool
    {
        return $user->can('pr.omit')
            && \in_array($purchaseRequisition->status, [
                PurchaseRequisitionStatus::READY_FOR_PO,
                PurchaseRequisitionStatus::PARTIALLY_ORDERED,
                // The completed states are included so an omission that finishes the
                // requisition can still be restored. Omitting an allocated line is a
                // partial short-close: only its unallocated remainder is waived.
                PurchaseRequisitionStatus::FULLY_ALLOCATED,
                PurchaseRequisitionStatus::FULLY_ORDERED,
                PurchaseRequisitionStatus::CLOSED,
            ], true);
    }

    /**
     * Allow the designated orderer to snapshot an approved PR's final document and unlock PO creation.
     */
    public function markReadyForPo(User $user, PurchaseRequisition $purchaseRequisition): bool
    {
        return $user->can('pr.finalize')
            && $user->id === $purchaseRequisition->to_be_ordered_by_id
            && $purchaseRequisition->status === PurchaseRequisitionStatus::APPROVED;
    }

    /**
     * Allow the requestor or designated orderer to cancel an approved requisition
     * that has no active purchase orders (cancel those first).
     */
    public function cancel(User $user, PurchaseRequisition $purchaseRequisition): bool
    {
        return $user->can('pr.cancel')
            && \in_array($user->id, [$purchaseRequisition->requestor_id, $purchaseRequisition->to_be_ordered_by_id], true)
            && \in_array($purchaseRequisition->status, [
                PurchaseRequisitionStatus::APPROVED,
                PurchaseRequisitionStatus::READY_FOR_PO,
            ], true)
            && ! $purchaseRequisition->purchaseOrders()->active()->exists();
    }

    /**
     * Allow a user to attempt a decision on the current workflow step.
     *
     * Coarse gate only: eligibility to approve is expressed through workflow step
     * assignment (role / `pr.approve` permission / department-head / named user),
     * which the engine enforces (403 if not assigned). Kept permissive so
     * department-head and named-user approvers are not blocked by a role check.
     */
    public function decide(User $user, PurchaseRequisition $purchaseRequisition): bool
    {
        return $purchaseRequisition->workflow_instance_id !== null;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, PurchaseRequisition $purchaseRequisition): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, PurchaseRequisition $purchaseRequisition): bool
    {
        return false;
    }
}

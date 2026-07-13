<?php

namespace App\Policies;

use App\Enums\PurchaseOrderStatus;
use App\Models\PurchaseOrder;
use App\Models\User;

class PurchaseOrderPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('po.view');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $user->can('po.view');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('po.prepare');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $user->can('po.prepare')
            && $purchaseOrder->status === PurchaseOrderStatus::DRAFT;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $user->can('po.prepare')
            && $purchaseOrder->status === PurchaseOrderStatus::DRAFT;
    }

    /**
     * Allow the user to submit a draft PO into the approval workflow.
     */
    public function submit(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $user->can('po.prepare')
            && $purchaseOrder->status === PurchaseOrderStatus::DRAFT;
    }

    /**
     * Allow a preparer to cancel a PO mid approval flow.
     */
    public function cancelWorkflow(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $user->can('po.cancel')
            && $purchaseOrder->status === PurchaseOrderStatus::PENDING_APPROVAL;
    }

    /**
     * Allow a preparer to seal an approved PO as ordered: snapshots the final
     * PDF and opens the order for receiving.
     */
    public function markAsOrdered(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $user->can('po.finalize')
            && $purchaseOrder->status === PurchaseOrderStatus::APPROVED;
    }

    /**
     * Allow a preparer to cancel an approved (not yet ordered) PO instead of
     * sealing it, returning its allocations to the pool.
     */
    public function cancel(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $user->can('po.cancel')
            && $purchaseOrder->status === PurchaseOrderStatus::APPROVED;
    }

    /**
     * Allow omitting/restoring a PO item while the order is still open for receiving.
     */
    public function omitItem(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $user->can('po.omit')
            && \in_array($purchaseOrder->status, [
                PurchaseOrderStatus::APPROVED,
                PurchaseOrderStatus::RELEASED,
                PurchaseOrderStatus::PARTIALLY_RECEIVED,
                // Included so an omission that completes receiving can still be restored.
                PurchaseOrderStatus::FULLY_RECEIVED,
            ], true);
    }

    /**
     * Allow a user to attempt a decision on the current workflow step.
     *
     * Coarse gate only: step assignment (enforced by the engine) decides who may act.
     */
    public function decide(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $purchaseOrder->workflow_instance_id !== null;
    }
}

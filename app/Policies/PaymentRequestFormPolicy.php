<?php

namespace App\Policies;

use App\Enums\PaymentRequestFormStatus;
use App\Models\PaymentRequestForm;
use App\Models\User;

class PaymentRequestFormPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('prf.view');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, PaymentRequestForm $paymentRequestForm): bool
    {
        return $user->can('prf.view');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('prf.prepare');
    }

    /**
     * Determine whether the user can update the model. The owning preparer only,
     * and only while the form is still a draft.
     */
    public function update(User $user, PaymentRequestForm $paymentRequestForm): bool
    {
        return $user->can('prf.prepare')
            && $user->id === $paymentRequestForm->requestor_id
            && $paymentRequestForm->status === PaymentRequestFormStatus::DRAFT;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, PaymentRequestForm $paymentRequestForm): bool
    {
        return $user->can('prf.prepare')
            && $user->id === $paymentRequestForm->requestor_id
            && $paymentRequestForm->status === PaymentRequestFormStatus::DRAFT;
    }

    /**
     * Allow the owning preparer to submit a draft PRF into the approval workflow.
     */
    public function submit(User $user, PaymentRequestForm $paymentRequestForm): bool
    {
        return $user->can('prf.prepare')
            && $user->id === $paymentRequestForm->requestor_id
            && $paymentRequestForm->status === PaymentRequestFormStatus::DRAFT;
    }

    /**
     * Allow the owning preparer to cancel their PRF mid approval flow.
     */
    public function cancelWorkflow(User $user, PaymentRequestForm $paymentRequestForm): bool
    {
        return $user->can('prf.cancel')
            && $user->id === $paymentRequestForm->requestor_id
            && $paymentRequestForm->status === PaymentRequestFormStatus::REVIEWING;
    }

    /**
     * Allow the owning preparer to cancel an approved PRF that has not yet been paid out.
     */
    public function cancel(User $user, PaymentRequestForm $paymentRequestForm): bool
    {
        return $user->can('prf.cancel')
            && $user->id === $paymentRequestForm->requestor_id
            && $paymentRequestForm->status === PaymentRequestFormStatus::APPROVED;
    }

    /**
     * Allow a user to attempt a decision on the current workflow step.
     *
     * Step-level assignment checks are enforced in the workflow engine (403 if not assigned).
     */
    public function decide(User $user, PaymentRequestForm $paymentRequestForm): bool
    {
        if ($paymentRequestForm->workflow_instance_id === null) {
            return false;
        }

        return true;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, PaymentRequestForm $paymentRequestForm): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, PaymentRequestForm $paymentRequestForm): bool
    {
        return false;
    }
}

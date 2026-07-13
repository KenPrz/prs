<?php

namespace App\Listeners;

use App\Events\Workflow\WorkflowCompleted;
use App\Models\PaymentRequestForm;
use App\Pdf\BuildPaymentRequestFormPdf;

/**
 * When a payment request form's approval workflow completes, bind its final PDF
 * to the document. Approval is the PRF's seal — there is no separate mark action,
 * so the completed workflow is the finalization point.
 */
class SnapshotPaymentRequestFormFinalDocument
{
    public function __construct(
        private BuildPaymentRequestFormPdf $builder,
    ) {}

    public function handle(WorkflowCompleted $event): void
    {
        $subject = $event->instance->subject;

        if (! $subject instanceof PaymentRequestForm) {
            return;
        }

        try {
            $subject->clearMediaCollection('final_document');

            $pdf = $this->builder->build($subject);

            if ($pdf === null) {
                return;
            }

            $filename = $subject->prf_number
                ? sprintf('payment-request-%s.pdf', $subject->prf_number)
                : sprintf('payment-request-%d.pdf', $subject->id);

            $subject->addMediaFromString($pdf)
                ->usingFileName($filename)
                ->toMediaCollection('final_document');
        } catch (\Throwable $e) {
            // A failed snapshot must not fail the approval itself.
            report($e);
        }
    }
}

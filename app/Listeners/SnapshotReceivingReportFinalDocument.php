<?php

namespace App\Listeners;

use App\Events\Workflow\WorkflowCompleted;
use App\Models\ReceivingReport;
use App\Pdf\BuildReceivingReportPdf;

/**
 * When a receiving report's verification workflow completes, bind its final
 * PDF to the document. Purchase requisitions and orders snapshot at their
 * explicit "mark" actions instead; receiving reports have no such action —
 * verification is the seal.
 */
class SnapshotReceivingReportFinalDocument
{
    public function __construct(
        private BuildReceivingReportPdf $builder,
    ) {}

    public function handle(WorkflowCompleted $event): void
    {
        $subject = $event->instance->subject;

        if (! $subject instanceof ReceivingReport) {
            return;
        }

        try {
            $subject->clearMediaCollection('final_document');

            $pdf = $this->builder->build($subject);

            if ($pdf === null) {
                return;
            }

            $filename = $subject->rr_number
                ? sprintf('receiving-report-%s.pdf', $subject->rr_number)
                : sprintf('receiving-report-%d.pdf', $subject->id);

            $subject->addMediaFromString($pdf)
                ->usingFileName($filename)
                ->toMediaCollection('final_document');
        } catch (\Throwable $e) {
            // A failed snapshot must not fail the approval itself.
            report($e);
        }
    }
}

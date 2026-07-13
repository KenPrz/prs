<?php

namespace App\Http\Controllers;

use App\Models\ReceivingReport;
use App\Pdf\BuildPurchaseOrderPdf;
use App\Pdf\BuildPurchaseRequisitionPdf;
use App\Pdf\BuildReceivingReportPdf;
use App\Pdf\MergeMediaIntoPdf;
use Illuminate\Http\Response;

class RenderReceivingReportPdfController extends Controller
{
    public function __invoke(
        ReceivingReport $receivingReport,
        BuildReceivingReportPdf $rrBuilder,
        BuildPurchaseOrderPdf $poBuilder,
        BuildPurchaseRequisitionPdf $prBuilder,
        MergeMediaIntoPdf $mergeService,
    ): Response {
        $this->authorize('view', $receivingReport);

        // ?standalone=1 renders just the RR, without attachments or the upstream PO/PR.
        $withAttachments = ! request()->boolean('standalone');

        $rrPdf = $rrBuilder->build($receivingReport, $withAttachments);

        abort_unless($rrPdf !== null, 404);

        // Append the upstream PO then PR documents + attachments after the RR.
        $purchaseOrder = $receivingReport->purchaseOrder;
        $poPdf = $withAttachments && $purchaseOrder ? $poBuilder->build($purchaseOrder) : null;
        $prPdf = $withAttachments && $purchaseOrder?->purchaseRequisition
            ? $prBuilder->build($purchaseOrder->purchaseRequisition)
            : null;

        $merged = $mergeService->concat([$rrPdf, $poPdf, $prPdf]);

        $filename = $receivingReport->rr_number
            ? sprintf('receiving-report-%s.pdf', $receivingReport->rr_number)
            : sprintf('receiving-report-%d.pdf', $receivingReport->id);

        return response($merged, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
        ]);
    }
}

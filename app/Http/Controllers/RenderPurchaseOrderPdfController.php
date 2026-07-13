<?php

namespace App\Http\Controllers;

use App\Models\PurchaseOrder;
use App\Pdf\BuildPurchaseOrderPdf;
use App\Pdf\BuildPurchaseRequisitionPdf;
use App\Pdf\MergeMediaIntoPdf;
use Illuminate\Http\Response;

class RenderPurchaseOrderPdfController extends Controller
{
    public function __invoke(
        PurchaseOrder $purchaseOrder,
        BuildPurchaseOrderPdf $poBuilder,
        BuildPurchaseRequisitionPdf $prBuilder,
        MergeMediaIntoPdf $mergeService,
    ): Response {
        $this->authorize('view', $purchaseOrder);

        // ?standalone=1 renders just the PO, without attachments or the upstream PR.
        $withAttachments = ! request()->boolean('standalone');

        $poPdf = $poBuilder->build($purchaseOrder, $withAttachments);

        abort_unless($poPdf !== null, 404);

        // Append the upstream PR document + attachments after the PO.
        $prPdf = $withAttachments && $purchaseOrder->purchaseRequisition
            ? $prBuilder->build($purchaseOrder->purchaseRequisition)
            : null;

        $merged = $mergeService->concat([$poPdf, $prPdf]);

        $filename = $purchaseOrder->po_number
            ? sprintf('purchase-order-%s.pdf', $purchaseOrder->po_number)
            : sprintf('purchase-order-%d.pdf', $purchaseOrder->id);

        return response($merged, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
        ]);
    }
}
